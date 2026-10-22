#!/usr/bin/env python3
"""Re-evaluate a saved local Transformer checkpoint without retraining it."""

from __future__ import annotations

import argparse
import importlib.metadata
import sys
import time
from pathlib import Path
from typing import Any

import numpy as np

from pilot_common import (
    DEFAULT_DATA_DIR,
    PilotDataError,
    build_file_manifest,
    code_provenance,
    evaluate_rankings,
    load_dataset,
    print_summary,
    python_environment,
    read_json,
    require_test_override,
    runtime_versions,
    sha256_file,
    write_evaluation_artifacts,
)


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Run auditable inference from a saved local Hugging Face model "
            "directory and preserve the full label-probability distribution."
        )
    )
    parser.add_argument("--model-dir", type=Path, required=True)
    parser.add_argument("--data-dir", type=Path, default=DEFAULT_DATA_DIR)
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=None,
        help="Defaults to <model parent>/audited-<evaluation split>.",
    )
    parser.add_argument(
        "--overwrite-output-dir",
        action="store_true",
        help="Allow replacement of an existing non-empty evaluation directory.",
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
    parser.add_argument(
        "--allow-dataset-mismatch",
        action="store_true",
        help=(
            "Allow inference from a frozen checkpoint against a newer dataset "
            "without retraining that checkpoint."
        ),
    )
    parser.add_argument("--top-k", type=int, default=3)
    parser.add_argument("--max-length", type=int, default=256)
    parser.add_argument("--batch-size", type=int, default=16)
    return parser.parse_args()


def import_dependencies() -> dict[str, Any]:
    try:
        import torch
        import transformers
        from torch.utils.data import DataLoader
        from transformers import (
            AutoModelForSequenceClassification,
            AutoTokenizer,
            DataCollatorWithPadding,
        )
    except ModuleNotFoundError as error:
        raise PilotDataError(
            f"Missing {error.name!r}. Install `ml/requirements-transformer.txt` "
            "inside the isolated ML environment."
        ) from error

    return {
        "torch": torch,
        "transformers": transformers,
        "DataLoader": DataLoader,
        "AutoModelForSequenceClassification": AutoModelForSequenceClassification,
        "AutoTokenizer": AutoTokenizer,
        "DataCollatorWithPadding": DataCollatorWithPadding,
    }


def softmax(logits: np.ndarray) -> np.ndarray:
    shifted = logits - np.max(logits, axis=1, keepdims=True)
    exponentials = np.exp(shifted)
    return exponentials / np.sum(exponentials, axis=1, keepdims=True)


def main() -> int:
    arguments = parse_arguments()
    try:
        require_test_override(
            arguments.eval_split,
            arguments.allow_test_evaluation,
        )
        if arguments.top_k < 1:
            raise PilotDataError("--top-k must be at least 1")
        if arguments.max_length < 1 or arguments.batch_size < 1:
            raise PilotDataError("--max-length and --batch-size must be positive")

        model_dir = arguments.model_dir.resolve()
        if not model_dir.is_dir():
            raise PilotDataError(f"Model directory does not exist: {model_dir}")
        bundle = load_dataset(arguments.data_dir)
        evaluation_rows = bundle.rows_by_split[arguments.eval_split]
        output_dir = (
            arguments.output_dir.resolve()
            if arguments.output_dir is not None
            else model_dir.parent / f"audited-{arguments.eval_split}"
        )
        if (
            output_dir.exists()
            and any(output_dir.iterdir())
            and not arguments.overwrite_output_dir
        ):
            raise PilotDataError(
                f"Output directory is not empty: {output_dir}. Select a new "
                "directory or acknowledge replacement with "
                "--overwrite-output-dir."
            )

        dependencies = import_dependencies()
        torch = dependencies["torch"]
        DataLoader = dependencies["DataLoader"]
        AutoModelForSequenceClassification = dependencies[
            "AutoModelForSequenceClassification"
        ]
        AutoTokenizer = dependencies["AutoTokenizer"]
        DataCollatorWithPadding = dependencies["DataCollatorWithPadding"]

        tokenizer = AutoTokenizer.from_pretrained(
            model_dir,
            local_files_only=True,
            trust_remote_code=False,
            use_fast=True,
        )
        model = AutoModelForSequenceClassification.from_pretrained(
            model_dir,
            local_files_only=True,
            trust_remote_code=False,
        )
        config_id_to_label = {
            int(label_id): str(label)
            for label_id, label in model.config.id2label.items()
        }
        config_label_to_id = {
            str(label): int(label_id)
            for label, label_id in model.config.label2id.items()
        }
        if config_id_to_label != bundle.id_to_label:
            raise PilotDataError(
                "Saved model id2label mapping does not match the frozen dataset"
            )
        if config_label_to_id != bundle.label_to_id:
            raise PilotDataError(
                "Saved model label2id mapping does not match the frozen dataset"
            )

        encodings = tokenizer(
            [str(row["text"]) for row in evaluation_rows],
            truncation=True,
            max_length=arguments.max_length,
            padding=False,
        )

        class EncodedDataset(torch.utils.data.Dataset):
            def __len__(self) -> int:
                return len(evaluation_rows)

            def __getitem__(self, index: int) -> dict[str, Any]:
                return {
                    key: values[index] for key, values in encodings.items()
                }

        data_loader = DataLoader(
            EncodedDataset(),
            batch_size=arguments.batch_size,
            shuffle=False,
            num_workers=0,
            collate_fn=DataCollatorWithPadding(tokenizer=tokenizer),
        )
        device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        model.to(device)
        model.eval()

        logits_batches: list[np.ndarray] = []
        started_at = time.perf_counter()
        with torch.inference_mode():
            for batch in data_loader:
                batch = {
                    key: value.to(device) for key, value in batch.items()
                }
                output = model(**batch)
                logits_batches.append(output.logits.detach().cpu().numpy())
        inference_seconds = time.perf_counter() - started_at
        probabilities = softmax(np.concatenate(logits_batches, axis=0))

        prediction_map: dict[str, list[dict[str, float | str]]] = {}
        for row, probability_row in zip(
            evaluation_rows,
            probabilities,
            strict=True,
        ):
            indices = np.argsort(-probability_row, kind="stable")
            prediction_map[str(row["id"])] = [
                {
                    "label": bundle.id_to_label[int(index)],
                    "score": float(probability_row[int(index)]),
                }
                for index in indices
            ]

        training_manifest_path = model_dir.parent / "model_manifest.json"
        training_manifest = (
            read_json(training_manifest_path)
            if training_manifest_path.is_file()
            else None
        )
        if isinstance(training_manifest, dict):
            trained_fingerprint = training_manifest.get("dataset_fingerprint")
            if (
                isinstance(trained_fingerprint, str)
                and trained_fingerprint != bundle.fingerprint
                and not arguments.allow_dataset_mismatch
            ):
                raise PilotDataError(
                    "Saved model was trained against a different dataset "
                    f"fingerprint ({trained_fingerprint})"
                )

        model_files = [path for path in model_dir.rglob("*") if path.is_file()]
        run_metadata = {
            "name": "saved-transformer-checkpoint",
            "model_family": "huggingface_sequence_classification",
            "mode": "evaluation_only",
            "model_directory": str(model_dir),
            "model_files": build_file_manifest(model_files, model_dir),
            "training_manifest": (
                {
                    "path": str(training_manifest_path),
                    "sha256": sha256_file(training_manifest_path),
                }
                if training_manifest_path.is_file()
                else None
            ),
            "evaluation_split": arguments.eval_split,
            "top_k": arguments.top_k,
            "max_length": arguments.max_length,
            "batch_size": arguments.batch_size,
            "device": str(device),
            "dataset_fingerprint": bundle.fingerprint,
            "allow_dataset_mismatch": arguments.allow_dataset_mismatch,
            "trained_dataset_fingerprint": (
                training_manifest.get("dataset_fingerprint")
                if isinstance(training_manifest, dict)
                else None
            ),
            "dataset_files": bundle.file_manifest,
            "evaluation_rows": len(evaluation_rows),
            "evaluation_scenario_groups": len(
                {str(row["scenario_group_id"]) for row in evaluation_rows}
            ),
            "inference_seconds": inference_seconds,
            "runtime": runtime_versions(
                {
                    "torch": str(torch.__version__),
                    "transformers": str(
                        dependencies["transformers"].__version__
                    ),
                    "accelerate": importlib.metadata.version("accelerate"),
                }
            ),
            "python_environment": python_environment(),
            "code_provenance": code_provenance(
                [
                    Path(__file__),
                    Path(__file__).with_name("pilot_common.py"),
                    Path(__file__).with_name("train_transformer.py"),
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
    except (PilotDataError, OSError, ValueError, RuntimeError) as error:
        print(f"Transformer evaluation failed: {error}", file=sys.stderr)
        return 1

    print_summary(metrics, output_dir)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
