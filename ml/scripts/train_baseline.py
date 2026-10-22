#!/usr/bin/env python3
"""Train and evaluate a reproducible TF-IDF offense-classification baseline."""

from __future__ import annotations

import argparse
import sys
import time
from collections import Counter
from pathlib import Path
from typing import Any

import joblib
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import FeatureUnion, Pipeline

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


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Train a word-and-character TF-IDF logistic-regression baseline on "
            "the frozen model-ready dataset."
        )
    )
    parser.add_argument(
        "--data-dir",
        type=Path,
        default=DEFAULT_DATA_DIR,
        help="Directory containing train/validation/test JSONL and label_map.json.",
    )
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=None,
        help=(
            "Artifact directory. Defaults to "
            "ml/artifacts/tfidf-logreg-<evaluation split>."
        ),
    )
    parser.add_argument(
        "--overwrite-output-dir",
        action="store_true",
        help="Allow replacement of an existing non-empty run directory.",
    )
    parser.add_argument(
        "--eval-split",
        choices=("validation", "test"),
        default="validation",
        help="Use validation during development; reserve test for the final gate.",
    )
    parser.add_argument(
        "--allow-test-evaluation",
        action="store_true",
        help="Required acknowledgement before reading the reserved test split.",
    )
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--top-k", type=int, default=3)
    parser.add_argument(
        "--regularization-c",
        type=float,
        default=4.0,
        help="Inverse L2 regularization strength for logistic regression.",
    )
    parser.add_argument(
        "--max-iterations",
        type=int,
        default=3000,
        help="Maximum logistic-regression iterations.",
    )
    return parser.parse_args()


def build_pipeline(arguments: argparse.Namespace) -> Pipeline:
    features = FeatureUnion(
        [
            (
                "word_tfidf",
                TfidfVectorizer(
                    analyzer="word",
                    ngram_range=(1, 2),
                    min_df=1,
                    max_df=1.0,
                    sublinear_tf=True,
                    lowercase=True,
                    strip_accents=None,
                ),
            ),
            (
                "character_tfidf",
                TfidfVectorizer(
                    analyzer="char_wb",
                    ngram_range=(3, 5),
                    min_df=2,
                    max_df=1.0,
                    sublinear_tf=True,
                    lowercase=True,
                    strip_accents=None,
                ),
            ),
        ],
        transformer_weights={
            "word_tfidf": 1.0,
            "character_tfidf": 1.0,
        },
    )
    classifier = LogisticRegression(
        C=arguments.regularization_c,
        class_weight="balanced",
        max_iter=arguments.max_iterations,
        random_state=arguments.seed,
        solver="lbfgs",
    )
    return Pipeline([("features", features), ("classifier", classifier)])


def ranked_predictions(
    probabilities: np.ndarray,
    model_labels: list[str],
) -> list[list[dict[str, float | str]]]:
    rankings: list[list[dict[str, float | str]]] = []
    for probability_row in probabilities:
        indices = np.argsort(-probability_row, kind="stable")
        rankings.append(
            [
                {
                    "label": model_labels[int(index)],
                    "score": float(probability_row[int(index)]),
                }
                for index in indices
            ]
        )
    return rankings


def main() -> int:
    arguments = parse_arguments()
    try:
        require_test_override(
            arguments.eval_split,
            arguments.allow_test_evaluation,
        )
        if arguments.top_k < 1:
            raise PilotDataError("--top-k must be at least 1")
        if arguments.regularization_c <= 0:
            raise PilotDataError("--regularization-c must be greater than 0")
        if arguments.max_iterations < 1:
            raise PilotDataError("--max-iterations must be at least 1")

        bundle = load_dataset(arguments.data_dir)
        train_rows = bundle.rows_by_split["train"]
        evaluation_rows = bundle.rows_by_split[arguments.eval_split]
        output_dir = (
            arguments.output_dir
            if arguments.output_dir is not None
            else DEFAULT_ARTIFACTS_DIR
            / f"tfidf-logreg-{arguments.eval_split}-seed-{arguments.seed}"
        )
        if (
            output_dir.exists()
            and any(output_dir.iterdir())
            and not arguments.overwrite_output_dir
        ):
            raise PilotDataError(
                f"Output directory is not empty: {output_dir.resolve()}. Select "
                "a new directory or acknowledge replacement with "
                "--overwrite-output-dir."
            )

        train_texts = [str(row["text"]) for row in train_rows]
        train_labels = [str(row["primary_offense_code"]) for row in train_rows]
        evaluation_texts = [str(row["text"]) for row in evaluation_rows]

        pipeline = build_pipeline(arguments)
        started_at = time.perf_counter()
        pipeline.fit(train_texts, train_labels)
        training_seconds = time.perf_counter() - started_at

        classifier: LogisticRegression = pipeline.named_steps["classifier"]
        model_labels = [str(label) for label in classifier.classes_]
        missing_model_labels = set(bundle.labels) - set(model_labels)
        if missing_model_labels:
            raise PilotDataError(
                "Training did not produce classifier weights for labels "
                f"{sorted(missing_model_labels)}"
            )

        inference_started_at = time.perf_counter()
        probabilities = pipeline.predict_proba(evaluation_texts)
        inference_seconds = time.perf_counter() - inference_started_at
        rankings = ranked_predictions(probabilities, model_labels)
        prediction_map = {
            str(row["id"]): ranking
            for row, ranking in zip(evaluation_rows, rankings, strict=True)
        }

        train_groups = {
            str(row["scenario_group_id"]) for row in train_rows
        }
        evaluation_groups = {
            str(row["scenario_group_id"]) for row in evaluation_rows
        }
        train_group_counts = Counter(
            (
                str(row["primary_offense_code"]),
                str(row["scenario_group_id"]),
            )
            for row in train_rows
        )
        independent_groups_per_label = Counter(
            label for label, _group_id in train_group_counts
        )

        hyperparameters: dict[str, Any] = {
            "seed": arguments.seed,
            "regularization_c": arguments.regularization_c,
            "class_weight": "balanced",
            "solver": "lbfgs",
            "max_iterations": arguments.max_iterations,
            "word_ngram_range": [1, 2],
            "character_ngram_range": [3, 5],
            "character_min_df": 2,
            "lowercase": True,
        }
        run_metadata = {
            "name": "tfidf-logreg",
            "model_family": "tfidf_logistic_regression",
            "evaluation_split": arguments.eval_split,
            "top_k": arguments.top_k,
            "dataset_fingerprint": bundle.fingerprint,
            "dataset_files": bundle.file_manifest,
            "train_rows": len(train_rows),
            "train_scenario_groups": len(train_groups),
            "evaluation_rows": len(evaluation_rows),
            "evaluation_scenario_groups": len(evaluation_groups),
            "independent_train_groups_per_label": dict(
                sorted(independent_groups_per_label.items())
            ),
            "training_seconds": training_seconds,
            "inference_seconds": inference_seconds,
            "hyperparameters": hyperparameters,
            "runtime": runtime_versions({"joblib": joblib.__version__}),
            "python_environment": python_environment(),
            "code_provenance": code_provenance(
                [
                    Path(__file__),
                    Path(__file__).with_name("pilot_common.py"),
                    Path(__file__).with_name("build_dataset.py"),
                    Path(__file__).resolve().parents[1]
                    / "requirements-baseline.txt",
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

        model_artifact = {
            "pipeline": pipeline,
            "label_to_id": bundle.label_to_id,
            "id_to_label": bundle.id_to_label,
            "label_titles": bundle.label_titles,
            "dataset_fingerprint": bundle.fingerprint,
            "hyperparameters": hyperparameters,
        }
        joblib.dump(model_artifact, output_dir / "model.joblib")
        write_json(
            output_dir / "model_manifest.json",
            {
                **run_metadata,
                "artifact": {
                    "file": "model.joblib",
                    "purpose": (
                        "Offline pilot artifact only; not approved for production "
                        "disciplinary decisions."
                    ),
                },
                "label_to_id": bundle.label_to_id,
                "label_titles": bundle.label_titles,
            },
        )
    except (PilotDataError, OSError, ValueError) as error:
        print(f"Baseline failed: {error}", file=sys.stderr)
        return 1

    print_summary(metrics, output_dir)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
