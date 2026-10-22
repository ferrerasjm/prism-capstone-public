#!/usr/bin/env python3
"""Evaluate the RoBERTa/TF-IDF/SVM hybrid with narrow policy guardrails."""

from __future__ import annotations

import argparse
import csv
import json
import sys
import time
from pathlib import Path

import joblib
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.pipeline import FeatureUnion, Pipeline
from sklearn.svm import LinearSVC

from pilot_common import (
    DEFAULT_ARTIFACTS_DIR,
    DEFAULT_DATA_DIR,
    PilotDataError,
    code_provenance,
    evaluate_rankings,
    load_dataset,
    print_summary,
    require_test_override,
    runtime_versions,
    write_evaluation_artifacts,
    write_json,
)
from policy_guardrails import GUARDRAIL_VERSION, policy_demotion, policy_guardrail


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--data-dir", type=Path, default=DEFAULT_DATA_DIR)
    parser.add_argument("--roberta-artifact-dir", type=Path, required=True)
    parser.add_argument("--tfidf-artifact-dir", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, default=None)
    parser.add_argument("--overwrite-output-dir", action="store_true")
    parser.add_argument("--eval-split", choices=("validation", "test"), default="validation")
    parser.add_argument("--allow-test-evaluation", action="store_true")
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--top-k", type=int, default=3)
    parser.add_argument("--svm-c", type=float, default=8.0)
    parser.add_argument("--svm-temperature", type=float, default=0.25)
    parser.add_argument("--roberta-weight", type=float, default=0.25)
    parser.add_argument("--tfidf-weight", type=float, default=0.05)
    parser.add_argument("--svm-weight", type=float, default=0.70)
    return parser.parse_args()


def build_svm(arguments: argparse.Namespace) -> Pipeline:
    features = FeatureUnion(
        [
            (
                "word_tfidf",
                TfidfVectorizer(
                    ngram_range=(1, 2),
                    sublinear_tf=True,
                    lowercase=True,
                    strip_accents="unicode",
                ),
            ),
            (
                "character_tfidf",
                TfidfVectorizer(
                    analyzer="char_wb",
                    ngram_range=(3, 5),
                    sublinear_tf=True,
                    lowercase=True,
                    strip_accents="unicode",
                ),
            ),
        ]
    )
    classifier = LinearSVC(
        C=arguments.svm_c,
        class_weight="balanced",
        random_state=arguments.seed,
    )
    return Pipeline([("features", features), ("classifier", classifier)])


def load_probabilities(
    artifact_dir: Path,
    rows: list[dict[str, object]],
    labels: list[str],
) -> np.ndarray:
    path = artifact_dir / "predictions.jsonl"
    if not path.is_file():
        raise PilotDataError(f"Missing prediction artifact: {path}")
    loaded: dict[str, dict[str, object]] = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        item = json.loads(line)
        loaded[str(item["id"])] = item
    expected_ids = {str(row["id"]) for row in rows}
    if set(loaded) != expected_ids:
        raise PilotDataError(f"Prediction IDs do not match {path}")

    probabilities = np.zeros((len(rows), len(labels)), dtype=np.float64)
    label_index = {label: index for index, label in enumerate(labels)}
    for row_index, row in enumerate(rows):
        item = loaded[str(row["id"])]
        for prediction in item["top_predictions"]:  # type: ignore[index]
            label = str(prediction["label"])
            if label not in label_index:
                raise PilotDataError(f"Unknown label {label} in {path}")
            probabilities[row_index, label_index[label]] = float(
                prediction["score"]
            )
        if not np.isclose(probabilities[row_index].sum(), 1.0, atol=1e-5):
            raise PilotDataError(f"Prediction scores do not sum to one in {path}")
    return probabilities


def softmax(values: np.ndarray) -> np.ndarray:
    shifted = values - values.max(axis=1, keepdims=True)
    exponentiated = np.exp(shifted)
    return exponentiated / exponentiated.sum(axis=1, keepdims=True)


def ranked_predictions(
    probabilities: np.ndarray, labels: list[str]
) -> list[list[dict[str, float | str]]]:
    output: list[list[dict[str, float | str]]] = []
    for row in probabilities:
        order = np.argsort(-row, kind="stable")
        output.append(
            [
                {"label": labels[int(index)], "score": float(row[int(index)])}
                for index in order
            ]
        )
    return output


def main() -> int:
    arguments = parse_arguments()
    try:
        require_test_override(arguments.eval_split, arguments.allow_test_evaluation)
        weights = np.asarray(
            [arguments.roberta_weight, arguments.tfidf_weight, arguments.svm_weight]
        )
        if np.any(weights < 0) or not np.isclose(weights.sum(), 1.0):
            raise PilotDataError("Hybrid weights must be non-negative and sum to one")
        if arguments.svm_c <= 0 or arguments.svm_temperature <= 0:
            raise PilotDataError("SVM C and temperature must be positive")

        bundle = load_dataset(arguments.data_dir)
        train_rows = bundle.rows_by_split["train"]
        evaluation_rows = bundle.rows_by_split[arguments.eval_split]
        labels = list(bundle.labels)
        output_dir = arguments.output_dir or (
            DEFAULT_ARTIFACTS_DIR / f"hybrid-{arguments.eval_split}"
        )
        if output_dir.exists() and any(output_dir.iterdir()):
            if not arguments.overwrite_output_dir:
                raise PilotDataError(f"Output directory is not empty: {output_dir}")

        pipeline = build_svm(arguments)
        started = time.perf_counter()
        pipeline.fit(
            [str(row["text"]) for row in train_rows],
            [str(row["primary_offense_code"]) for row in train_rows],
        )
        training_seconds = time.perf_counter() - started
        classifier: LinearSVC = pipeline.named_steps["classifier"]
        svm_labels = [str(label) for label in classifier.classes_]
        if set(svm_labels) != set(labels):
            raise PilotDataError("SVM labels do not match the dataset label map")

        inference_started = time.perf_counter()
        decisions = pipeline.decision_function(
            [str(row["text"]) for row in evaluation_rows]
        )
        svm_native = softmax(decisions / arguments.svm_temperature)
        svm_by_label = np.zeros_like(svm_native)
        dataset_index = {label: index for index, label in enumerate(labels)}
        for native_index, label in enumerate(svm_labels):
            svm_by_label[:, dataset_index[label]] = svm_native[:, native_index]
        roberta = load_probabilities(
            arguments.roberta_artifact_dir, evaluation_rows, labels
        )
        tfidf = load_probabilities(arguments.tfidf_artifact_dir, evaluation_rows, labels)
        combined = (
            arguments.roberta_weight * roberta
            + arguments.tfidf_weight * tfidf
            + arguments.svm_weight * svm_by_label
        )

        guardrail_rows: list[dict[str, object]] = []
        for row_index, row in enumerate(evaluation_rows):
            original_index = int(np.argmax(combined[row_index]))
            original_label = labels[original_index]
            guarded_label, reason = policy_guardrail(
                str(row["text"]), original_label
            )
            if reason is None or guarded_label == original_label:
                # Serving also suppresses a top-ranked label the handbook does
                # not support, so evaluation has to apply the same rule or the
                # measured numbers understate the deployed behaviour.
                demotion_reason = policy_demotion(str(row["text"]), original_label)
                if demotion_reason is None:
                    continue
                combined[row_index, original_index] = 0.0
                guarded_label, reason = original_label, demotion_reason
            else:
                guarded_index = dataset_index[guarded_label]
                combined[row_index, original_index], combined[row_index, guarded_index] = (
                    combined[row_index, guarded_index],
                    combined[row_index, original_index],
                )
            guardrail_rows.append(
                {
                    "id": str(row["id"]),
                    "expected_label": str(row["primary_offense_code"]),
                    "original_label": original_label,
                    "guarded_label": guarded_label,
                    "reason": reason,
                    "corrected": guarded_label == str(row["primary_offense_code"]),
                }
            )
        inference_seconds = time.perf_counter() - inference_started

        rankings = ranked_predictions(combined, labels)
        prediction_map = {
            str(row["id"]): ranking
            for row, ranking in zip(evaluation_rows, rankings, strict=True)
        }
        hyperparameters = {
            "seed": arguments.seed,
            "svm_c": arguments.svm_c,
            "svm_temperature": arguments.svm_temperature,
            "weights": {
                "roberta": arguments.roberta_weight,
                "tfidf_logreg": arguments.tfidf_weight,
                "linear_svm": arguments.svm_weight,
            },
            "guardrail_version": GUARDRAIL_VERSION,
        }
        run_metadata = {
            "name": "roberta-tfidf-svm-policy-hybrid",
            "model_family": "hybrid_sequence_and_linear_classification",
            "evaluation_split": arguments.eval_split,
            "top_k": arguments.top_k,
            "dataset_fingerprint": bundle.fingerprint,
            "dataset_files": bundle.file_manifest,
            "train_rows": len(train_rows),
            "evaluation_rows": len(evaluation_rows),
            "training_seconds": training_seconds,
            "inference_seconds": inference_seconds,
            "guardrail_applications": len(guardrail_rows),
            "hyperparameters": hyperparameters,
            "development_tuned_on_validation": True,
            "runtime": runtime_versions({"joblib": joblib.__version__}),
            "code_provenance": code_provenance(
                [Path(__file__), Path(__file__).with_name("policy_guardrails.py")]
            ),
        }
        warnings = list(bundle.warnings) + [
            "Hybrid weights and policy guardrails were selected during validation "
            "development; only the untouched reserved test can provide a final estimate."
        ]
        metrics, detailed = evaluate_rankings(
            evaluation_rows,
            prediction_map,
            labels,
            top_k=arguments.top_k,
            run_metadata=run_metadata,
            warnings=warnings,
        )
        write_evaluation_artifacts(output_dir, metrics, detailed)
        joblib.dump(
            {
                "svm_pipeline": pipeline,
                "label_to_id": bundle.label_to_id,
                "dataset_fingerprint": bundle.fingerprint,
                "hyperparameters": hyperparameters,
                "roberta_artifact_dir": str(arguments.roberta_artifact_dir.resolve()),
                "tfidf_artifact_dir": str(arguments.tfidf_artifact_dir.resolve()),
            },
            output_dir / "model.joblib",
        )
        write_json(output_dir / "model_manifest.json", run_metadata)
        with (output_dir / "guardrail_audit.csv").open(
            "w", encoding="utf-8", newline=""
        ) as handle:
            writer = csv.DictWriter(
                handle,
                fieldnames=[
                    "id",
                    "expected_label",
                    "original_label",
                    "guarded_label",
                    "reason",
                    "corrected",
                ],
            )
            writer.writeheader()
            writer.writerows(guardrail_rows)
    except (OSError, ValueError, PilotDataError) as error:
        print(f"Hybrid evaluation failed: {error}", file=sys.stderr)
        return 1

    print_summary(metrics, output_dir)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
