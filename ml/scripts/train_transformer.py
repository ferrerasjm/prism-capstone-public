#!/usr/bin/env python3
"""Fine-tune an optional Hugging Face sequence classifier for the ML pilot."""

from __future__ import annotations

import argparse
import importlib.metadata
import inspect
import re
import sys
import time
from collections import Counter
from pathlib import Path
from typing import Any

import numpy as np
from sklearn.metrics import accuracy_score, f1_score

from pilot_common import (
    DEFAULT_ARTIFACTS_DIR,
    DEFAULT_DATA_DIR,
    PilotDataError,
    code_provenance,
    evaluate_rankings,
    load_dataset,
    print_summary,
    python_environment,
    require_test_override,
    runtime_versions,
    write_evaluation_artifacts,
    write_json,
)


DEFAULT_MODEL = "jcblaise/roberta-tagalog-base"


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Fine-tune a cased Hugging Face encoder to rank handbook offense "
            "codes. Model downloads are disabled unless --allow-download is used."
        )
    )
    parser.add_argument("--model-name", default=DEFAULT_MODEL)
    parser.add_argument(
        "--revision",
        default="main",
        help="Hugging Face revision or commit hash; prefer a commit for final runs.",
    )
    parser.add_argument(
        "--allow-download",
        action="store_true",
        help="Allow Hugging Face to download a model that is not already cached.",
    )
    parser.add_argument(
        "--cache-dir",
        type=Path,
        default=None,
        help="Optional repository-local Hugging Face cache directory.",
    )
    parser.add_argument(
        "--data-dir",
        type=Path,
        default=DEFAULT_DATA_DIR,
    )
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=None,
    )
    parser.add_argument(
        "--overwrite-output-dir",
        action="store_true",
        help="Allow Trainer to replace an existing run directory.",
    )
    parser.add_argument(
        "--resume-from-checkpoint",
        type=Path,
        default=None,
        help="Resume an interrupted run from a Trainer checkpoint directory.",
    )
    parser.add_argument(
        "--eval-split",
        choices=("validation", "test"),
        default="validation",
    )
    parser.add_argument(
        "--allow-test-evaluation",
        action="store_true",
        help="Required acknowledgement before reading the reserved test split.",
    )
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--top-k", type=int, default=3)
    parser.add_argument("--epochs", type=float, default=8.0)
    parser.add_argument("--learning-rate", type=float, default=2.0e-5)
    parser.add_argument("--weight-decay", type=float, default=0.01)
    parser.add_argument("--warmup-ratio", type=float, default=0.1)
    parser.add_argument("--max-length", type=int, default=256)
    parser.add_argument("--train-batch-size", type=int, default=8)
    parser.add_argument("--eval-batch-size", type=int, default=16)
    parser.add_argument("--gradient-accumulation-steps", type=int, default=1)
    parser.add_argument("--early-stopping-patience", type=int, default=2)
    parser.add_argument(
        "--class-weight",
        choices=("balanced", "none"),
        default="balanced",
    )
    parser.add_argument(
        "--mixed-precision",
        choices=("none", "fp16", "bf16"),
        default="none",
    )
    return parser.parse_args()


def safe_model_name(model_name: str) -> str:
    return re.sub(r"[^A-Za-z0-9._-]+", "-", model_name).strip("-").lower()


def import_transformer_dependencies() -> dict[str, Any]:
    try:
        import torch
        import torch.nn.functional as torch_functional
        import transformers
        from transformers import (
            AutoModelForSequenceClassification,
            AutoTokenizer,
            DataCollatorWithPadding,
            EarlyStoppingCallback,
            Trainer,
            TrainingArguments,
            set_seed,
        )
    except ModuleNotFoundError as error:
        missing_name = error.name or "transformer dependency"
        raise PilotDataError(
            f"Missing {missing_name!r}. Install the isolated transformer "
            "dependencies with `python -m pip install -r "
            "ml/requirements-transformer.txt`."
        ) from error

    return {
        "torch": torch,
        "torch_functional": torch_functional,
        "transformers": transformers,
        "AutoModelForSequenceClassification": AutoModelForSequenceClassification,
        "AutoTokenizer": AutoTokenizer,
        "DataCollatorWithPadding": DataCollatorWithPadding,
        "EarlyStoppingCallback": EarlyStoppingCallback,
        "Trainer": Trainer,
        "TrainingArguments": TrainingArguments,
        "set_seed": set_seed,
    }


def validate_arguments(arguments: argparse.Namespace) -> None:
    require_test_override(
        arguments.eval_split,
        arguments.allow_test_evaluation,
    )
    positive_values = {
        "--top-k": arguments.top_k,
        "--epochs": arguments.epochs,
        "--learning-rate": arguments.learning_rate,
        "--max-length": arguments.max_length,
        "--train-batch-size": arguments.train_batch_size,
        "--eval-batch-size": arguments.eval_batch_size,
        "--gradient-accumulation-steps": arguments.gradient_accumulation_steps,
    }
    invalid = [name for name, value in positive_values.items() if value <= 0]
    if invalid:
        raise PilotDataError(
            f"{', '.join(invalid)} must contain values greater than zero"
        )
    if not 0.0 <= arguments.warmup_ratio < 1.0:
        raise PilotDataError("--warmup-ratio must be at least 0 and less than 1")
    if arguments.weight_decay < 0:
        raise PilotDataError("--weight-decay cannot be negative")
    if arguments.early_stopping_patience < 0:
        raise PilotDataError("--early-stopping-patience cannot be negative")
    if (
        arguments.resume_from_checkpoint is not None
        and not arguments.resume_from_checkpoint.is_dir()
    ):
        raise PilotDataError(
            "--resume-from-checkpoint must point to an existing directory"
        )


def softmax(logits: np.ndarray) -> np.ndarray:
    shifted = logits - np.max(logits, axis=1, keepdims=True)
    exponentials = np.exp(shifted)
    return exponentials / np.sum(exponentials, axis=1, keepdims=True)


def tokenization_audit(
    tokenizer: Any,
    rows: list[dict[str, Any]],
    max_length: int,
) -> dict[str, Any]:
    by_language: dict[str, list[int]] = {}
    for language in sorted({str(row["language"]) for row in rows}):
        texts = [str(row["text"]) for row in rows if row["language"] == language]
        encoded = tokenizer(
            texts,
            add_special_tokens=True,
            truncation=False,
            padding=False,
        )
        lengths = [len(token_ids) for token_ids in encoded["input_ids"]]
        by_language[language] = lengths

    return {
        "max_length": max_length,
        "by_language": {
            language: {
                "rows": len(lengths),
                "mean_tokens": float(np.mean(lengths)),
                "median_tokens": float(np.median(lengths)),
                "p95_tokens": float(np.percentile(lengths, 95)),
                "max_tokens": max(lengths),
                "rows_exceeding_max_length": sum(
                    length > max_length for length in lengths
                ),
            }
            for language, lengths in by_language.items()
        },
    }


def main() -> int:
    arguments = parse_arguments()
    try:
        validate_arguments(arguments)
        dependencies = import_transformer_dependencies()
        torch = dependencies["torch"]
        torch_functional = dependencies["torch_functional"]
        AutoModelForSequenceClassification = dependencies[
            "AutoModelForSequenceClassification"
        ]
        AutoTokenizer = dependencies["AutoTokenizer"]
        DataCollatorWithPadding = dependencies["DataCollatorWithPadding"]
        EarlyStoppingCallback = dependencies["EarlyStoppingCallback"]
        Trainer = dependencies["Trainer"]
        TrainingArguments = dependencies["TrainingArguments"]
        set_seed = dependencies["set_seed"]

        set_seed(arguments.seed)
        bundle = load_dataset(arguments.data_dir)
        train_rows = bundle.rows_by_split["train"]
        selection_rows = bundle.rows_by_split["validation"]
        evaluation_rows = bundle.rows_by_split[arguments.eval_split]
        output_dir = (
            arguments.output_dir
            if arguments.output_dir is not None
            else DEFAULT_ARTIFACTS_DIR
            / (
                f"{safe_model_name(arguments.model_name)}-"
                f"{arguments.eval_split}-seed-{arguments.seed}"
            )
        )
        output_dir = output_dir.resolve()
        if (
            output_dir.exists()
            and any(output_dir.iterdir())
            and not arguments.overwrite_output_dir
            and arguments.resume_from_checkpoint is None
        ):
            raise PilotDataError(
                f"Output directory is not empty: {output_dir}. Select a new "
                "directory or acknowledge replacement with --overwrite-output-dir."
            )
        output_dir.mkdir(parents=True, exist_ok=True)

        pretrained_options = {
            "revision": arguments.revision,
            "local_files_only": not arguments.allow_download,
            "trust_remote_code": False,
        }
        if arguments.cache_dir is not None:
            pretrained_options["cache_dir"] = str(arguments.cache_dir.resolve())
        try:
            tokenizer = AutoTokenizer.from_pretrained(
                arguments.model_name,
                use_fast=True,
                **pretrained_options,
            )
            model = AutoModelForSequenceClassification.from_pretrained(
                arguments.model_name,
                num_labels=len(bundle.labels),
                id2label=bundle.id_to_label,
                label2id=bundle.label_to_id,
                **pretrained_options,
            )
        except (OSError, ValueError) as error:
            download_hint = (
                "Confirm the local path/cache and revision, or use "
                "--allow-download when network access and the model license have "
                "been approved."
            )
            raise PilotDataError(
                f"Unable to load {arguments.model_name!r}: {error}. {download_hint}"
            ) from error

        class EncodedDataset(torch.utils.data.Dataset):
            def __init__(self, rows: list[dict[str, Any]]) -> None:
                self.rows = rows
                self.encodings = tokenizer(
                    [str(row["text"]) for row in rows],
                    truncation=True,
                    max_length=arguments.max_length,
                    padding=False,
                )

            def __len__(self) -> int:
                return len(self.rows)

            def __getitem__(self, index: int) -> dict[str, Any]:
                item = {
                    key: values[index] for key, values in self.encodings.items()
                }
                item["labels"] = int(self.rows[index]["label_id"])
                return item

        train_dataset = EncodedDataset(train_rows)
        selection_dataset = EncodedDataset(selection_rows)
        evaluation_dataset = (
            selection_dataset
            if arguments.eval_split == "validation"
            else EncodedDataset(evaluation_rows)
        )
        data_collator = DataCollatorWithPadding(tokenizer=tokenizer)

        label_counts = Counter(
            str(row["primary_offense_code"]) for row in train_rows
        )
        if arguments.class_weight == "balanced":
            class_weights = [
                len(train_rows) / (len(bundle.labels) * label_counts[label])
                for label in bundle.labels
            ]
        else:
            class_weights = [1.0] * len(bundle.labels)
        class_weight_tensor = torch.tensor(class_weights, dtype=torch.float32)

        class WeightedTrainer(Trainer):
            def __init__(
                self,
                *trainer_args: Any,
                class_weights: Any,
                **trainer_kwargs: Any,
            ) -> None:
                self.class_weights = class_weights
                super().__init__(*trainer_args, **trainer_kwargs)
                # The custom mean-reduced cross-entropy does not consume
                # `num_items_in_batch`. Tell Trainer to perform its normal
                # gradient-accumulation scaling instead of treating this loss
                # as an already batch-normalized sum.
                self.model_accepts_loss_kwargs = False

            def compute_loss(
                self,
                model: Any,
                inputs: dict[str, Any],
                return_outputs: bool = False,
                num_items_in_batch: Any = None,
            ) -> Any:
                del num_items_in_batch
                labels = inputs["labels"]
                outputs = model(**inputs)
                logits = outputs.get("logits")
                loss = torch_functional.cross_entropy(
                    logits.view(-1, len(bundle.labels)),
                    labels.view(-1),
                    weight=self.class_weights.to(logits.device),
                )
                return (loss, outputs) if return_outputs else loss

        def compute_metrics(evaluation_prediction: Any) -> dict[str, float]:
            logits = evaluation_prediction.predictions
            if isinstance(logits, tuple):
                logits = logits[0]
            true_ids = np.asarray(evaluation_prediction.label_ids)
            predicted_ids = np.argmax(logits, axis=1)
            k = min(arguments.top_k, logits.shape[1])
            top_ids = np.argsort(-logits, axis=1, kind="stable")[:, :k]
            return {
                "accuracy": float(accuracy_score(true_ids, predicted_ids)),
                "macro_f1": float(
                    f1_score(
                        true_ids,
                        predicted_ids,
                        labels=list(range(len(bundle.labels))),
                        average="macro",
                        zero_division=0,
                    )
                ),
                f"top_{arguments.top_k}_recall": float(
                    np.mean(
                        [
                            int(expected) in candidates
                            for expected, candidates in zip(
                                true_ids,
                                top_ids,
                                strict=True,
                            )
                        ]
                    )
                ),
            }

        transformer_version = str(dependencies["transformers"].__version__)
        try:
            transformer_major_version = int(transformer_version.split(".", 1)[0])
        except ValueError:
            transformer_major_version = 4
        training_kwargs: dict[str, Any] = {
            "output_dir": str(output_dir / "checkpoints"),
            "num_train_epochs": arguments.epochs,
            "per_device_train_batch_size": arguments.train_batch_size,
            "per_device_eval_batch_size": arguments.eval_batch_size,
            "gradient_accumulation_steps": arguments.gradient_accumulation_steps,
            "learning_rate": arguments.learning_rate,
            "weight_decay": arguments.weight_decay,
            "save_strategy": "epoch",
            "logging_strategy": "steps",
            "logging_steps": max(
                1,
                len(train_rows)
                // (
                    arguments.train_batch_size
                    * arguments.gradient_accumulation_steps
                    * 2
                ),
            ),
            "load_best_model_at_end": True,
            "metric_for_best_model": "macro_f1",
            "greater_is_better": True,
            "save_total_limit": 2,
            "seed": arguments.seed,
            "data_seed": arguments.seed,
            "report_to": [],
            "dataloader_num_workers": 0,
            "fp16": arguments.mixed_precision == "fp16",
            "bf16": arguments.mixed_precision == "bf16",
        }
        if transformer_major_version >= 5:
            # Transformers 5 interprets a fractional warmup_steps value below
            # one as a ratio and deprecates warmup_ratio.
            training_kwargs["warmup_steps"] = arguments.warmup_ratio
        else:
            training_kwargs["warmup_ratio"] = arguments.warmup_ratio
        training_signature = inspect.signature(TrainingArguments.__init__)
        if "overwrite_output_dir" in training_signature.parameters:
            training_kwargs["overwrite_output_dir"] = (
                arguments.overwrite_output_dir
            )
        if "eval_strategy" in training_signature.parameters:
            training_kwargs["eval_strategy"] = "epoch"
        else:
            training_kwargs["evaluation_strategy"] = "epoch"
        training_arguments = TrainingArguments(**training_kwargs)

        trainer_kwargs: dict[str, Any] = {
            "model": model,
            "args": training_arguments,
            "train_dataset": train_dataset,
            "eval_dataset": selection_dataset,
            "data_collator": data_collator,
            "compute_metrics": compute_metrics,
            "class_weights": class_weight_tensor,
        }
        trainer_signature = inspect.signature(Trainer.__init__)
        if "processing_class" in trainer_signature.parameters:
            trainer_kwargs["processing_class"] = tokenizer
        else:
            trainer_kwargs["tokenizer"] = tokenizer
        if arguments.early_stopping_patience > 0:
            trainer_kwargs["callbacks"] = [
                EarlyStoppingCallback(
                    early_stopping_patience=arguments.early_stopping_patience
                )
            ]

        trainer = WeightedTrainer(**trainer_kwargs)
        training_started_at = time.perf_counter()
        train_result = trainer.train(
            resume_from_checkpoint=(
                str(arguments.resume_from_checkpoint.resolve())
                if arguments.resume_from_checkpoint is not None
                else None
            )
        )
        training_seconds = time.perf_counter() - training_started_at

        inference_started_at = time.perf_counter()
        prediction_output = trainer.predict(evaluation_dataset)
        inference_seconds = time.perf_counter() - inference_started_at
        logits = prediction_output.predictions
        if isinstance(logits, tuple):
            logits = logits[0]
        probabilities = softmax(np.asarray(logits))
        rankings: list[list[dict[str, float | str]]] = []
        for probability_row in probabilities:
            indices = np.argsort(-probability_row, kind="stable")
            rankings.append(
                [
                    {
                        "label": bundle.id_to_label[int(index)],
                        "score": float(probability_row[int(index)]),
                    }
                    for index in indices
                ]
            )
        prediction_map = {
            str(row["id"]): ranking
            for row, ranking in zip(evaluation_rows, rankings, strict=True)
        }

        model_dir = output_dir / "model"
        trainer.save_model(str(model_dir))
        tokenizer.save_pretrained(str(model_dir))
        resolved_revision = (
            getattr(model.config, "_commit_hash", None) or arguments.revision
        )
        token_audit = tokenization_audit(
            tokenizer,
            [
                *train_rows,
                *selection_rows,
                *(
                    evaluation_rows
                    if arguments.eval_split == "test"
                    else []
                ),
            ],
            arguments.max_length,
        )
        torch_version = str(torch.__version__)
        run_metadata = {
            "name": safe_model_name(arguments.model_name),
            "model_family": "huggingface_sequence_classification",
            "base_model": arguments.model_name,
            "requested_revision": arguments.revision,
            "resolved_revision": resolved_revision,
            "local_files_only": not arguments.allow_download,
            "cache_dir": (
                str(arguments.cache_dir.resolve())
                if arguments.cache_dir is not None
                else None
            ),
            "checkpoint_selection_split": "validation",
            "evaluation_split": arguments.eval_split,
            "top_k": arguments.top_k,
            "dataset_fingerprint": bundle.fingerprint,
            "dataset_files": bundle.file_manifest,
            "train_rows": len(train_rows),
            "train_scenario_groups": len(
                {str(row["scenario_group_id"]) for row in train_rows}
            ),
            "selection_rows": len(selection_rows),
            "selection_scenario_groups": len(
                {str(row["scenario_group_id"]) for row in selection_rows}
            ),
            "evaluation_rows": len(evaluation_rows),
            "evaluation_scenario_groups": len(
                {str(row["scenario_group_id"]) for row in evaluation_rows}
            ),
            "training_seconds": training_seconds,
            "resumed_from_checkpoint": (
                str(arguments.resume_from_checkpoint.resolve())
                if arguments.resume_from_checkpoint is not None
                else None
            ),
            "inference_seconds": inference_seconds,
            "hyperparameters": {
                "seed": arguments.seed,
                "epochs": arguments.epochs,
                "learning_rate": arguments.learning_rate,
                "weight_decay": arguments.weight_decay,
                "warmup_ratio": arguments.warmup_ratio,
                "max_length": arguments.max_length,
                "train_batch_size": arguments.train_batch_size,
                "eval_batch_size": arguments.eval_batch_size,
                "gradient_accumulation_steps": (
                    arguments.gradient_accumulation_steps
                ),
                "early_stopping_patience": arguments.early_stopping_patience,
                "class_weight": arguments.class_weight,
                "mixed_precision": arguments.mixed_precision,
            },
            "train_result": {
                key: (
                    float(value)
                    if isinstance(value, (int, float, np.integer, np.floating))
                    else value
                )
                for key, value in train_result.metrics.items()
            },
            "tokenization_audit": token_audit,
            "runtime": runtime_versions(
                {
                    "torch": torch_version,
                    "transformers": transformer_version,
                    "accelerate": importlib.metadata.version("accelerate"),
                }
            ),
            "python_environment": python_environment(),
            "code_provenance": code_provenance(
                [
                    Path(__file__),
                    Path(__file__).with_name("pilot_common.py"),
                    Path(__file__).with_name("build_dataset.py"),
                    Path(__file__).resolve().parents[1]
                    / "requirements-baseline.txt",
                    Path(__file__).resolve().parents[1]
                    / "requirements-transformer.txt",
                ]
            ),
        }
        metrics, detailed_rows = evaluate_rankings(
            evaluation_rows,
            prediction_map,
            bundle.labels,
            top_k=arguments.top_k,
            run_metadata=run_metadata,
            warnings=bundle.warnings,
        )
        write_evaluation_artifacts(output_dir, metrics, detailed_rows)
        write_json(
            output_dir / "model_manifest.json",
            {
                **run_metadata,
                "artifact": {
                    "directory": "model",
                    "purpose": (
                        "Offline pilot artifact only; not approved for production "
                        "disciplinary decisions."
                    ),
                },
                "label_to_id": bundle.label_to_id,
                "label_titles": bundle.label_titles,
            },
        )
    except (PilotDataError, OSError, ValueError, RuntimeError) as error:
        print(f"Transformer training failed: {error}", file=sys.stderr)
        return 1

    print_summary(metrics, output_dir)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
