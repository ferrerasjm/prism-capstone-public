#!/usr/bin/env python3
"""Shared data-contract and evaluation helpers for the ML pilot."""

from __future__ import annotations

import csv
import hashlib
import importlib.metadata
import json
import math
import platform
import subprocess
import sys
from collections import Counter, defaultdict
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable, Mapping, Sequence

import numpy as np
import sklearn
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
)


ML_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DATA_DIR = ML_ROOT / "data" / "processed"
DEFAULT_ARTIFACTS_DIR = ML_ROOT / "artifacts"
SPLITS = ("train", "validation", "test")
REQUIRED_ROW_FIELDS = {
    "id",
    "text",
    "primary_offense_code",
    "language",
    "split",
    "scenario_group_id",
    "difficulty",
    "model_eligible",
    "label_id",
}


class PilotDataError(RuntimeError):
    """Raised when pilot input or predictions violate the frozen contract."""


@dataclass(frozen=True)
class DatasetBundle:
    """Validated model-ready rows and their stable label mapping."""

    data_dir: Path
    rows_by_split: dict[str, list[dict[str, Any]]]
    label_to_id: dict[str, int]
    id_to_label: dict[int, str]
    label_titles: dict[str, str]
    file_manifest: dict[str, dict[str, Any]]
    fingerprint: str
    warnings: tuple[str, ...]

    @property
    def labels(self) -> list[str]:
        return [self.id_to_label[index] for index in sorted(self.id_to_label)]


def read_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except OSError as error:
        raise PilotDataError(f"Unable to read {path}: {error}") from error
    except json.JSONDecodeError as error:
        raise PilotDataError(f"{path} is not valid JSON: {error}") from error


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    try:
        with path.open("r", encoding="utf-8") as handle:
            for line_number, raw_line in enumerate(handle, start=1):
                line = raw_line.strip()
                if not line:
                    continue
                try:
                    row = json.loads(line)
                except json.JSONDecodeError as error:
                    raise PilotDataError(
                        f"{path}:{line_number} is not valid JSON: {error}"
                    ) from error
                if not isinstance(row, dict):
                    raise PilotDataError(
                        f"{path}:{line_number} must contain a JSON object"
                    )
                rows.append(row)
    except OSError as error:
        raise PilotDataError(f"Unable to read {path}: {error}") from error

    if not rows:
        raise PilotDataError(f"{path} does not contain any rows")

    return rows


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    try:
        with path.open("rb") as handle:
            for chunk in iter(lambda: handle.read(1024 * 1024), b""):
                digest.update(chunk)
    except OSError as error:
        raise PilotDataError(f"Unable to hash {path}: {error}") from error
    return digest.hexdigest().upper()


def build_file_manifest(paths: Iterable[Path], base_dir: Path) -> dict[str, dict[str, Any]]:
    manifest: dict[str, dict[str, Any]] = {}
    for path in sorted(paths):
        try:
            relative_path = path.relative_to(base_dir).as_posix()
        except ValueError:
            relative_path = str(path.resolve())
        manifest[relative_path] = {
            "sha256": sha256_file(path),
            "bytes": path.stat().st_size,
        }
    return manifest


def manifest_fingerprint(manifest: Mapping[str, Mapping[str, Any]]) -> str:
    canonical = json.dumps(manifest, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest().upper()


def _validate_label_map(raw_map: Any, path: Path) -> tuple[
    dict[str, int], dict[int, str], dict[str, str]
]:
    if not isinstance(raw_map, dict):
        raise PilotDataError(f"{path} must contain a JSON object")

    raw_label_to_id = raw_map.get("label_to_id")
    raw_id_to_label = raw_map.get("id_to_label")
    raw_titles = raw_map.get("label_titles")
    if not isinstance(raw_label_to_id, dict) or not isinstance(raw_id_to_label, dict):
        raise PilotDataError(
            f"{path} must contain label_to_id and id_to_label objects"
        )
    if not isinstance(raw_titles, dict):
        raise PilotDataError(f"{path} must contain a label_titles object")

    label_to_id: dict[str, int] = {}
    for label, label_id in raw_label_to_id.items():
        if not isinstance(label, str) or not label:
            raise PilotDataError(f"{path}: label_to_id contains an invalid label")
        if not isinstance(label_id, int) or isinstance(label_id, bool):
            raise PilotDataError(
                f"{path}: label ID for {label!r} must be an integer"
            )
        label_to_id[label] = label_id

    id_to_label: dict[int, str] = {}
    for raw_id, label in raw_id_to_label.items():
        try:
            label_id = int(raw_id)
        except (TypeError, ValueError) as error:
            raise PilotDataError(
                f"{path}: id_to_label key {raw_id!r} is not an integer"
            ) from error
        if not isinstance(label, str) or not label:
            raise PilotDataError(f"{path}: id_to_label contains an invalid label")
        id_to_label[label_id] = label

    expected_ids = list(range(len(label_to_id)))
    if sorted(label_to_id.values()) != expected_ids:
        raise PilotDataError(
            f"{path}: label IDs must be contiguous from 0 to {len(label_to_id) - 1}"
        )
    if sorted(id_to_label) != expected_ids:
        raise PilotDataError(f"{path}: id_to_label does not cover every label ID")

    for label, label_id in label_to_id.items():
        if id_to_label.get(label_id) != label:
            raise PilotDataError(
                f"{path}: label maps disagree for {label!r} and ID {label_id}"
            )

    label_titles = {
        str(label): str(title)
        for label, title in raw_titles.items()
        if isinstance(label, str) and isinstance(title, str)
    }
    missing_titles = set(label_to_id) - set(label_titles)
    if missing_titles:
        raise PilotDataError(
            f"{path}: missing titles for labels {sorted(missing_titles)}"
        )

    return label_to_id, id_to_label, label_titles


def _validate_rows(
    rows_by_split: Mapping[str, Sequence[Mapping[str, Any]]],
    label_to_id: Mapping[str, int],
) -> tuple[str, ...]:
    seen_ids: set[str] = set()
    group_splits: dict[str, set[str]] = defaultdict(set)
    group_labels: dict[str, set[str]] = defaultdict(set)
    group_languages: dict[str, set[str]] = defaultdict(set)
    group_row_counts: Counter[str] = Counter()
    labels_by_split: dict[str, set[str]] = defaultdict(set)
    train_groups_by_label: dict[str, set[str]] = defaultdict(set)
    review_statuses: Counter[str] = Counter()

    for split in SPLITS:
        rows = rows_by_split.get(split)
        if rows is None:
            raise PilotDataError(f"Missing required {split} rows")

        for row_number, row in enumerate(rows, start=1):
            context = f"{split} row {row_number}"
            missing_fields = REQUIRED_ROW_FIELDS - set(row)
            if missing_fields:
                raise PilotDataError(
                    f"{context}: missing fields {sorted(missing_fields)}"
                )

            row_id = row["id"]
            text = row["text"]
            label = row["primary_offense_code"]
            group_id = row["scenario_group_id"]
            language = row["language"]
            row_split = row["split"]
            label_id = row["label_id"]

            if not isinstance(row_id, str) or not row_id:
                raise PilotDataError(f"{context}: id must be a non-empty string")
            if row_id in seen_ids:
                raise PilotDataError(f"{context}: duplicate row ID {row_id!r}")
            seen_ids.add(row_id)

            if not isinstance(text, str) or not text.strip():
                raise PilotDataError(f"{context}: text must be a non-empty string")
            if not isinstance(group_id, str) or not group_id:
                raise PilotDataError(
                    f"{context}: scenario_group_id must be a non-empty string"
                )
            if row_split != split:
                raise PilotDataError(
                    f"{context}: embedded split {row_split!r} does not match file"
                )
            if row.get("model_eligible") is not True:
                raise PilotDataError(
                    f"{context}: model split contains an ineligible row"
                )
            if label not in label_to_id:
                raise PilotDataError(f"{context}: unknown label {label!r}")
            if label_id != label_to_id[label]:
                raise PilotDataError(
                    f"{context}: label_id {label_id!r} does not match {label!r}"
                )
            if language not in {"en", "tl", "taglish"}:
                raise PilotDataError(f"{context}: unsupported language {language!r}")

            group_splits[group_id].add(split)
            group_labels[group_id].add(label)
            group_languages[group_id].add(language)
            group_row_counts[group_id] += 1
            labels_by_split[split].add(label)
            if split == "train":
                train_groups_by_label[label].add(group_id)
            review_statuses[str(row.get("review_status", "unknown"))] += 1

    leaking_groups = sorted(
        group_id for group_id, splits in group_splits.items() if len(splits) != 1
    )
    if leaking_groups:
        raise PilotDataError(
            "Scenario groups cross data splits: " + ", ".join(leaking_groups[:10])
        )

    mixed_label_groups = sorted(
        group_id for group_id, labels in group_labels.items() if len(labels) != 1
    )
    if mixed_label_groups:
        raise PilotDataError(
            "Scenario groups contain multiple labels: "
            + ", ".join(mixed_label_groups[:10])
        )

    repeated_language_groups = sorted(
        group_id
        for group_id, languages in group_languages.items()
        if group_row_counts[group_id] != len(languages)
    )

    incomplete_groups = sorted(
        group_id
        for group_id, languages in group_languages.items()
        if languages != {"en", "tl", "taglish"}
    )

    expected_labels = set(label_to_id)
    for split in SPLITS:
        missing_labels = expected_labels - labels_by_split[split]
        if missing_labels:
            raise PilotDataError(
                f"{split} split is missing labels {sorted(missing_labels)}"
            )

    sparse_labels = sorted(
        label
        for label in expected_labels
        if len(train_groups_by_label[label]) < 14
    )
    warnings: list[str] = []
    if repeated_language_groups:
        warnings.append(
            f"{len(repeated_language_groups)} scenario groups contain multiple "
            "valid paraphrases in at least one language."
        )
    if incomplete_groups:
        warnings.append(
            f"{len(incomplete_groups)} scenario groups contain standalone or "
            "partial-language cases; group-aware splitting still prevents leakage."
        )
    if sparse_labels:
        smallest = min(len(train_groups_by_label[label]) for label in sparse_labels)
        warnings.append(
            f"{len(sparse_labels)} labels have fewer than the target 14 independent "
            f"training groups; the smallest has {smallest}. Pilot metrics are not "
            "production evidence."
        )
    if review_statuses and set(review_statuses) != {"approved"}:
        statuses = ", ".join(
            f"{status}={count}" for status, count in sorted(review_statuses.items())
        )
        warnings.append(
            "Dataset rows are not all reviewer-approved "
            f"({statuses}); results are experimental."
        )

    return tuple(warnings)


def load_dataset(data_dir: Path | str = DEFAULT_DATA_DIR) -> DatasetBundle:
    data_dir = Path(data_dir).resolve()
    label_map_path = data_dir / "label_map.json"
    split_paths = {split: data_dir / f"{split}.jsonl" for split in SPLITS}
    required_paths = [label_map_path, *split_paths.values()]
    missing_paths = [path for path in required_paths if not path.is_file()]
    if missing_paths:
        raise PilotDataError(
            "Missing processed dataset files: "
            + ", ".join(str(path) for path in missing_paths)
        )

    label_to_id, id_to_label, label_titles = _validate_label_map(
        read_json(label_map_path),
        label_map_path,
    )
    rows_by_split = {
        split: read_jsonl(path) for split, path in split_paths.items()
    }
    warnings = _validate_rows(rows_by_split, label_to_id)
    file_manifest = build_file_manifest(required_paths, data_dir)

    return DatasetBundle(
        data_dir=data_dir,
        rows_by_split=rows_by_split,
        label_to_id=label_to_id,
        id_to_label=id_to_label,
        label_titles=label_titles,
        file_manifest=file_manifest,
        fingerprint=manifest_fingerprint(file_manifest),
        warnings=warnings,
    )


def require_test_override(eval_split: str, allow_test_evaluation: bool) -> None:
    if eval_split == "test" and not allow_test_evaluation:
        raise PilotDataError(
            "The test split is reserved. Re-run with --allow-test-evaluation only "
            "for a documented final comparison after model selection."
        )


def _ranked_labels(
    ranking: Sequence[Mapping[str, Any]],
    row_id: str,
) -> list[str]:
    if not ranking:
        raise PilotDataError(f"Prediction for {row_id!r} has an empty ranking")

    labels: list[str] = []
    scores: list[float | None] = []
    for rank, prediction in enumerate(ranking, start=1):
        if not isinstance(prediction, Mapping):
            raise PilotDataError(
                f"Prediction {rank} for {row_id!r} must be an object"
            )
        label = prediction.get("label")
        if not isinstance(label, str) or not label:
            raise PilotDataError(
                f"Prediction {rank} for {row_id!r} has an invalid label"
            )
        if label in labels:
            raise PilotDataError(
                f"Prediction for {row_id!r} repeats label {label!r}"
            )
        score = prediction.get("score")
        if score is not None and (
            isinstance(score, bool) or not isinstance(score, (int, float))
        ):
            raise PilotDataError(
                f"Prediction score for {row_id!r}/{label!r} must be numeric"
            )
        if score is not None and not math.isfinite(float(score)):
            raise PilotDataError(
                f"Prediction score for {row_id!r}/{label!r} must be finite"
            )
        labels.append(label)
        scores.append(float(score) if score is not None else None)

    if all(score is not None for score in scores):
        numeric_scores = [float(score) for score in scores if score is not None]
        for left, right in zip(numeric_scores, numeric_scores[1:]):
            if right > left:
                raise PilotDataError(
                    f"Prediction scores for {row_id!r} are not in descending order"
                )

    return labels


def _summary_metrics(
    truth: Sequence[str],
    predicted: Sequence[str],
    ranked: Sequence[Sequence[str]],
    labels: Sequence[str],
    top_k: int,
) -> dict[str, float | int]:
    return {
        "rows": len(truth),
        "accuracy": float(accuracy_score(truth, predicted)),
        "macro_precision": float(
            precision_score(
                truth,
                predicted,
                labels=list(labels),
                average="macro",
                zero_division=0,
            )
        ),
        "macro_recall": float(
            recall_score(
                truth,
                predicted,
                labels=list(labels),
                average="macro",
                zero_division=0,
            )
        ),
        "macro_f1": float(
            f1_score(
                truth,
                predicted,
                labels=list(labels),
                average="macro",
                zero_division=0,
            )
        ),
        "weighted_f1": float(
            f1_score(
                truth,
                predicted,
                labels=list(labels),
                average="weighted",
                zero_division=0,
            )
        ),
        f"top_{top_k}_recall": float(
            np.mean(
                [
                    expected in candidates[:top_k]
                    for expected, candidates in zip(truth, ranked, strict=True)
                ]
            )
        ),
    }


def _expected_calibration_error(
    truth: Sequence[str],
    predicted: Sequence[str],
    confidences: Sequence[float],
    bins: int = 10,
) -> float:
    confidence_array = np.asarray(confidences, dtype=float)
    correctness = np.asarray(
        [expected == actual for expected, actual in zip(truth, predicted, strict=True)],
        dtype=float,
    )
    boundaries = np.linspace(0.0, 1.0, bins + 1)
    error = 0.0
    for index in range(bins):
        lower = boundaries[index]
        upper = boundaries[index + 1]
        if index == bins - 1:
            selected = (confidence_array >= lower) & (confidence_array <= upper)
        else:
            selected = (confidence_array >= lower) & (confidence_array < upper)
        if not np.any(selected):
            continue
        weight = float(np.mean(selected))
        error += weight * abs(
            float(np.mean(correctness[selected]))
            - float(np.mean(confidence_array[selected]))
        )
    return error


def _calibration_metrics(
    rows: Sequence[Mapping[str, Any]],
    predictions: Mapping[str, Sequence[Mapping[str, Any]]],
    labels: Sequence[str],
) -> dict[str, Any] | None:
    truth: list[str] = []
    predicted: list[str] = []
    confidences: list[float] = []
    probability_rows: list[list[float]] = []

    for row in rows:
        row_id = str(row["id"])
        ranking = predictions[row_id]
        score_map = {
            str(item.get("label")): item.get("score")
            for item in ranking
            if isinstance(item, Mapping)
        }
        if set(score_map) != set(labels):
            return None
        values = [score_map[label] for label in labels]
        if any(
            isinstance(value, bool) or not isinstance(value, (int, float))
            for value in values
        ):
            return None
        numeric_values = [float(value) for value in values]
        if any(value < 0.0 or value > 1.0 for value in numeric_values):
            return None
        if not math.isclose(sum(numeric_values), 1.0, rel_tol=1e-4, abs_tol=1e-4):
            return None

        ranked_labels = _ranked_labels(ranking, row_id)
        truth.append(str(row["primary_offense_code"]))
        predicted.append(ranked_labels[0])
        confidences.append(float(ranking[0]["score"]))
        probability_rows.append(numeric_values)

    coverage: list[dict[str, float | int | None]] = []
    for threshold in (0.20, 0.40, 0.60, 0.80):
        selected = [
            index
            for index, confidence in enumerate(confidences)
            if confidence >= threshold
        ]
        coverage.append(
            {
                "threshold": threshold,
                "covered_rows": len(selected),
                "coverage": len(selected) / len(rows),
                "accuracy_when_covered": (
                    float(
                        np.mean(
                            [truth[index] == predicted[index] for index in selected]
                        )
                    )
                    if selected
                    else None
                ),
            }
        )

    label_columns = {label: index for index, label in enumerate(labels)}
    clipped_probabilities = np.clip(
        np.asarray(probability_rows, dtype=float),
        1.0e-15,
        1.0,
    )
    true_probabilities = np.asarray(
        [
            clipped_probabilities[row_index, label_columns[expected]]
            for row_index, expected in enumerate(truth)
        ],
        dtype=float,
    )

    return {
        "log_loss": float(-np.mean(np.log(true_probabilities))),
        "expected_calibration_error_10_bins": _expected_calibration_error(
            truth,
            predicted,
            confidences,
        ),
        "mean_top_1_confidence": float(np.mean(confidences)),
        "threshold_coverage": coverage,
    }


def evaluate_rankings(
    rows: Sequence[Mapping[str, Any]],
    predictions: Mapping[str, Sequence[Mapping[str, Any]]],
    labels: Sequence[str],
    *,
    top_k: int = 3,
    run_metadata: Mapping[str, Any] | None = None,
    warnings: Sequence[str] = (),
) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    if top_k < 1:
        raise PilotDataError("top_k must be at least 1")
    if not rows:
        raise PilotDataError("Cannot evaluate an empty split")

    expected_ids = {str(row["id"]) for row in rows}
    prediction_ids = set(predictions)
    missing_ids = sorted(expected_ids - prediction_ids)
    extra_ids = sorted(prediction_ids - expected_ids)
    if missing_ids or extra_ids:
        raise PilotDataError(
            "Prediction IDs do not match the evaluation split; "
            f"missing={missing_ids[:10]}, extra={extra_ids[:10]}"
        )

    truth: list[str] = []
    predicted: list[str] = []
    ranked: list[list[str]] = []
    detailed_rows: list[dict[str, Any]] = []
    unexpected_labels: Counter[str] = Counter()

    for row in rows:
        row_id = str(row["id"])
        ranking = list(predictions[row_id])
        labels_for_row = _ranked_labels(ranking, row_id)
        expected = str(row["primary_offense_code"])
        prediction = labels_for_row[0]

        truth.append(expected)
        predicted.append(prediction)
        ranked.append(labels_for_row)
        unexpected_labels.update(label for label in labels_for_row if label not in labels)
        detailed_rows.append(
            {
                "id": row_id,
                "expected_label": expected,
                "predicted_label": prediction,
                "correct": expected == prediction,
                f"correct_in_top_{top_k}": expected in labels_for_row[:top_k],
                "language": row.get("language"),
                "difficulty": row.get("difficulty"),
                "scenario_group_id": row.get("scenario_group_id"),
                # Preserve the complete score vector when the predictor
                # provides one so calibration metrics can be independently
                # recomputed from predictions.jsonl.
                "top_predictions": ranking,
            }
        )

    summary = _summary_metrics(truth, predicted, ranked, labels, top_k)
    report = classification_report(
        truth,
        predicted,
        labels=list(labels),
        target_names=list(labels),
        output_dict=True,
        zero_division=0,
    )
    per_class = {
        label: {
            "precision": float(report[label]["precision"]),
            "recall": float(report[label]["recall"]),
            "f1": float(report[label]["f1-score"]),
            "support": int(report[label]["support"]),
        }
        for label in labels
    }

    slices: dict[str, dict[str, dict[str, float | int]]] = {}
    for field in ("language", "difficulty"):
        values = sorted({str(row.get(field, "unknown")) for row in rows})
        slices[field] = {}
        for value in values:
            selected = [
                index
                for index, row in enumerate(rows)
                if str(row.get(field, "unknown")) == value
            ]
            observed_label_set = {truth[index] for index in selected}
            observed_labels = [
                label
                for label in labels
                if label in observed_label_set
            ]
            slices[field][value] = _summary_metrics(
                [truth[index] for index in selected],
                [predicted[index] for index in selected],
                [ranked[index] for index in selected],
                observed_labels,
                top_k,
            )

    confusion_counts: Counter[tuple[str, str]] = Counter(
        (expected, actual)
        for expected, actual in zip(truth, predicted, strict=True)
        if expected != actual
    )
    top_confusions = [
        {
            "expected_label": expected,
            "predicted_label": actual,
            "count": count,
        }
        for (expected, actual), count in sorted(
            confusion_counts.items(),
            key=lambda item: (-item[1], item[0][0], item[0][1]),
        )
    ]

    all_confusion_labels = list(labels)
    if unexpected_labels:
        all_confusion_labels.extend(sorted(unexpected_labels))
    matrix = confusion_matrix(
        truth,
        predicted,
        labels=all_confusion_labels,
    ).tolist()

    result_warnings = list(warnings)
    if unexpected_labels:
        formatted = ", ".join(
            f"{label}={count}" for label, count in sorted(unexpected_labels.items())
        )
        result_warnings.append(
            "Predictions contained labels outside the model label map: " + formatted
        )

    metrics: dict[str, Any] = {
        "schema_version": 1,
        "run": dict(run_metadata or {}),
        "summary": summary,
        "slices": slices,
        "per_class": per_class,
        "top_confusions": top_confusions,
        "confusion_matrix": {
            "labels": all_confusion_labels,
            "values": matrix,
        },
        "calibration": _calibration_metrics(rows, predictions, labels),
        "warnings": result_warnings,
    }
    return metrics, detailed_rows


def runtime_versions(extra_packages: Mapping[str, str] | None = None) -> dict[str, str]:
    versions = {
        "python": platform.python_version(),
        "platform": platform.platform(),
        "numpy": np.__version__,
        "scikit_learn": sklearn.__version__,
    }
    if extra_packages:
        versions.update({str(key): str(value) for key, value in extra_packages.items()})
    return versions


def python_environment() -> dict[str, str]:
    """Return the complete installed Python distribution/version mapping."""

    distributions: dict[str, str] = {}
    for distribution in importlib.metadata.distributions():
        name = distribution.metadata.get("Name")
        if isinstance(name, str) and name:
            distributions[name] = distribution.version
    return dict(sorted(distributions.items(), key=lambda item: item[0].casefold()))


def code_provenance(
    source_paths: Iterable[Path],
    repository_root: Path | None = None,
) -> dict[str, Any]:
    """Hash run-defining source files and summarize the Git working state."""

    repository_root = (repository_root or ML_ROOT.parent).resolve()
    resolved_paths = [Path(path).resolve() for path in source_paths]
    missing_paths = [path for path in resolved_paths if not path.is_file()]
    if missing_paths:
        raise PilotDataError(
            "Cannot record code provenance; missing files: "
            + ", ".join(str(path) for path in missing_paths)
        )

    git: dict[str, Any] = {
        "head_commit": None,
        "dirty": None,
        "status_entry_count": None,
        "status_sha256": None,
    }
    try:
        head_result = subprocess.run(
            ["git", "rev-parse", "HEAD"],
            cwd=repository_root,
            check=False,
            capture_output=True,
            text=True,
            timeout=10,
        )
        status_result = subprocess.run(
            ["git", "status", "--porcelain", "--untracked-files=all"],
            cwd=repository_root,
            check=False,
            capture_output=True,
            text=True,
            timeout=15,
        )
        if head_result.returncode == 0:
            git["head_commit"] = head_result.stdout.strip() or None
        if status_result.returncode == 0:
            canonical_status = status_result.stdout.replace("\r\n", "\n")
            entries = [
                line for line in canonical_status.splitlines() if line.strip()
            ]
            git.update(
                {
                    "dirty": bool(entries),
                    "status_entry_count": len(entries),
                    "status_sha256": hashlib.sha256(
                        canonical_status.encode("utf-8")
                    ).hexdigest().upper(),
                }
            )
    except (OSError, subprocess.SubprocessError):
        pass

    return {
        "source_files": build_file_manifest(resolved_paths, repository_root),
        "git": git,
    }


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(value, ensure_ascii=False, indent=2, allow_nan=False) + "\n",
        encoding="utf-8",
        newline="\n",
    )


def write_jsonl(path: Path, rows: Iterable[Mapping[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="\n") as handle:
        for row in rows:
            handle.write(
                json.dumps(
                    row,
                    ensure_ascii=False,
                    separators=(",", ":"),
                    allow_nan=False,
                )
                + "\n"
            )


def write_evaluation_artifacts(
    output_dir: Path | str,
    metrics: Mapping[str, Any],
    detailed_rows: Sequence[Mapping[str, Any]],
) -> None:
    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    write_json(output_dir / "metrics.json", metrics)
    write_jsonl(output_dir / "predictions.jsonl", detailed_rows)

    per_class = metrics["per_class"]
    with (output_dir / "per_class.csv").open(
        "w", encoding="utf-8", newline=""
    ) as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=["label", "precision", "recall", "f1", "support"],
            lineterminator="\n",
        )
        writer.writeheader()
        for label, values in per_class.items():
            writer.writerow({"label": label, **values})

    matrix = metrics["confusion_matrix"]
    matrix_labels = matrix["labels"]
    with (output_dir / "confusion_matrix.csv").open(
        "w", encoding="utf-8", newline=""
    ) as handle:
        writer = csv.writer(handle, lineterminator="\n")
        writer.writerow(["actual\\predicted", *matrix_labels])
        for label, values in zip(
            matrix_labels,
            matrix["values"],
            strict=True,
        ):
            writer.writerow([label, *values])

    with (output_dir / "top_confusions.csv").open(
        "w", encoding="utf-8", newline=""
    ) as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=["expected_label", "predicted_label", "count"],
            lineterminator="\n",
        )
        writer.writeheader()
        writer.writerows(metrics["top_confusions"])


def print_summary(metrics: Mapping[str, Any], output_dir: Path | str) -> None:
    summary = metrics["summary"]
    top_keys = [key for key in summary if key.startswith("top_") and key.endswith("_recall")]
    top_metric = top_keys[0] if top_keys else "top_k_recall"
    top_value = summary.get(top_metric, 0.0)
    print(
        "Evaluation complete: "
        f"accuracy={summary['accuracy']:.4f}, "
        f"macro_f1={summary['macro_f1']:.4f}, "
        f"{top_metric}={top_value:.4f}."
    )
    print(f"Artifacts: {Path(output_dir).resolve()}")
    for warning in metrics.get("warnings", []):
        print(f"WARNING: {warning}", file=sys.stderr)
