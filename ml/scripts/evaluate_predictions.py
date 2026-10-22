#!/usr/bin/env python3
"""Evaluate ranked predictions from the keyword engine or another pilot model."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any

from pilot_common import (
    DEFAULT_ARTIFACTS_DIR,
    DEFAULT_DATA_DIR,
    PilotDataError,
    code_provenance,
    evaluate_rankings,
    load_dataset,
    print_summary,
    python_environment,
    read_json,
    read_jsonl,
    require_test_override,
    runtime_versions,
    sha256_file,
    write_evaluation_artifacts,
)


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Score a JSONL file containing ranked offense-code predictions "
            "against one frozen dataset split."
        )
    )
    parser.add_argument(
        "--predictions",
        type=Path,
        required=True,
        help="JSONL with one id/top_predictions record per evaluation row.",
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
        help="Defaults to ml/artifacts/evaluated-<prediction filename>-<split>.",
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
    parser.add_argument("--top-k", type=int, default=3)
    parser.add_argument(
        "--run-name",
        default=None,
        help="Human-readable model/run name stored with the metrics.",
    )
    return parser.parse_args()


def load_predictions(
    path: Path,
    expected_by_id: dict[str, str],
) -> tuple[dict[str, list[dict[str, Any]]], set[str]]:
    prediction_rows = read_jsonl(path)
    predictions: dict[str, list[dict[str, Any]]] = {}
    score_kinds: set[str] = set()

    for row_number, row in enumerate(prediction_rows, start=1):
        context = f"{path}:{row_number}"
        row_id = row.get("id")
        ranking = row.get("top_predictions")
        if not isinstance(row_id, str) or not row_id:
            raise PilotDataError(f"{context}: id must be a non-empty string")
        if row_id in predictions:
            raise PilotDataError(f"{context}: duplicate prediction ID {row_id!r}")
        if not isinstance(ranking, list) or not ranking:
            raise PilotDataError(
                f"{context}: top_predictions must be a non-empty array"
            )

        recorded_truth = row.get("expected_label")
        if recorded_truth is not None:
            expected_truth = expected_by_id.get(row_id)
            if expected_truth is not None and recorded_truth != expected_truth:
                raise PilotDataError(
                    f"{context}: expected_label {recorded_truth!r} disagrees with "
                    f"the frozen dataset label {expected_truth!r}"
                )

        score_kind = row.get("score_kind")
        if score_kind is not None:
            if not isinstance(score_kind, str) or not score_kind:
                raise PilotDataError(f"{context}: score_kind must be a string")
            score_kinds.add(score_kind)

        predictions[row_id] = ranking

    return predictions, score_kinds


def main() -> int:
    arguments = parse_arguments()
    try:
        require_test_override(
            arguments.eval_split,
            arguments.allow_test_evaluation,
        )
        if arguments.top_k < 1:
            raise PilotDataError("--top-k must be at least 1")
        if not arguments.predictions.is_file():
            raise PilotDataError(
                f"Prediction file does not exist: {arguments.predictions}"
            )

        bundle = load_dataset(arguments.data_dir)
        evaluation_rows = bundle.rows_by_split[arguments.eval_split]
        expected_by_id = {
            str(row["id"]): str(row["primary_offense_code"])
            for row in evaluation_rows
        }
        predictions, score_kinds = load_predictions(
            arguments.predictions,
            expected_by_id,
        )
        prediction_sha256 = sha256_file(arguments.predictions)
        sidecar_path = Path(str(arguments.predictions) + ".manifest.json")
        sidecar = read_json(sidecar_path) if sidecar_path.is_file() else None
        if isinstance(sidecar, dict):
            recorded_hash = (
                sidecar.get("predictions", {}).get("sha256")
                if isinstance(sidecar.get("predictions"), dict)
                else None
            )
            if (
                isinstance(recorded_hash, str)
                and recorded_hash.upper() != prediction_sha256
            ):
                raise PilotDataError(
                    f"Prediction provenance is stale: {sidecar_path}"
                )
        output_dir = (
            arguments.output_dir
            if arguments.output_dir is not None
            else DEFAULT_ARTIFACTS_DIR
            / (
                f"evaluated-{arguments.predictions.stem}-"
                f"{arguments.eval_split}"
            )
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

        run_metadata = {
            "name": arguments.run_name or arguments.predictions.stem,
            "model_family": "external_ranked_predictions",
            "evaluation_split": arguments.eval_split,
            "top_k": arguments.top_k,
            "score_kinds": sorted(score_kinds),
            "prediction_file": {
                "path": str(arguments.predictions.resolve()),
                "sha256": prediction_sha256,
                "bytes": arguments.predictions.stat().st_size,
            },
            "prediction_provenance": (
                {
                    "path": str(sidecar_path.resolve()),
                    "sha256": sha256_file(sidecar_path),
                    "manifest": sidecar,
                }
                if sidecar_path.is_file()
                else None
            ),
            "dataset_fingerprint": bundle.fingerprint,
            "dataset_files": bundle.file_manifest,
            "evaluation_rows": len(evaluation_rows),
            "evaluation_scenario_groups": len(
                {str(row["scenario_group_id"]) for row in evaluation_rows}
            ),
            "runtime": runtime_versions(),
            "python_environment": python_environment(),
            "code_provenance": code_provenance(
                [
                    Path(__file__),
                    Path(__file__).with_name("pilot_common.py"),
                    Path(__file__).resolve().parents[1]
                    / "requirements-baseline.txt",
                ]
            ),
        }
        metrics, detailed_rows = evaluate_rankings(
            evaluation_rows,
            predictions,
            bundle.labels,
            top_k=arguments.top_k,
            run_metadata=run_metadata,
            warnings=bundle.warnings,
        )
        write_evaluation_artifacts(output_dir, metrics, detailed_rows)
    except (PilotDataError, OSError, ValueError, json.JSONDecodeError) as error:
        print(f"Evaluation failed: {error}", file=sys.stderr)
        return 1

    print_summary(metrics, output_dir)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
