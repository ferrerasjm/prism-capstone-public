#!/usr/bin/env python3
"""Build deterministic model splits from the reviewed training CSV."""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
from collections import defaultdict
from pathlib import Path
from typing import Any


ML_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DATA_DIR = ML_ROOT / "data" / "processed"
LANGUAGE_MAP = {"english": "en", "tagalog": "tl", "taglish": "taglish"}
MANUAL_ONLY_LABELS = {"MAJOR-19"}
INAPPLICABLE_LABELS = {"MAJOR-16", "MAJOR-22"}
SPLITS = ("train", "validation", "test")

CUSTOM_GROUPS = {
    "the student wore the costume needed during an approved presentation rehearsal and changed afterward.": "NO_MATCH-CUSTOM-COSTUME",
    "nagsuot ang student ng costume that's needed during an approved presentation rehearsal at nag-change afterward.": "NO_MATCH-CUSTOM-COSTUME",
    "isinuot ng estudyante ang costume na kinakailangan sa aprubadong presentation rehearsal at nagpalit pagkatapos.": "NO_MATCH-CUSTOM-COSTUME",
}


class SplitError(RuntimeError):
    """Raised when the training data cannot be split safely."""


def normalized_text(value: str) -> str:
    return " ".join(value.casefold().split())


def generated_group_id(label: str, normalized_description: str) -> str:
    """Return a stable ID in a namespace that cannot collide with legacy IDs."""
    digest = hashlib.sha256(normalized_description.encode()).hexdigest()[:12]
    return f"ADDED-{label}-{digest}"


def reviewed_statuses(path: Path | None) -> dict[str, str]:
    if path is None:
        return {}
    if not path.is_file():
        raise SplitError(f"Review-status CSV does not exist: {path}")

    statuses: dict[str, str] = {}
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        required = {"description", "review_status"}
        if reader.fieldnames is None or not required.issubset(reader.fieldnames):
            raise SplitError(f"{path} is missing description/review_status columns")
        for row_number, row in enumerate(reader, start=2):
            key = normalized_text(row["description"])
            status = row["review_status"].strip()
            if not key or not status:
                raise SplitError(f"{path}:{row_number} has an empty review value")
            previous = statuses.setdefault(key, status)
            if previous != status:
                raise SplitError(f"{path}:{row_number} has conflicting review statuses")
    return statuses


def policy_case_metadata(path: Path | None) -> dict[str, dict[str, str]]:
    if path is None or not path.is_file():
        return {}

    metadata: dict[str, dict[str, str]] = {}
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        required = {
            "description",
            "label",
            "scenario_group_id",
            "review_status",
        }
        if reader.fieldnames is None or not required.issubset(reader.fieldnames):
            raise SplitError(f"{path} is missing policy-case metadata columns")
        for row_number, row in enumerate(reader, start=2):
            key = normalized_text(row["description"])
            value = {
                "label": row["label"].strip(),
                "scenario_group_id": row["scenario_group_id"].strip(),
                "review_status": row["review_status"].strip(),
                "required_split": (row.get("required_split") or "").strip(),
                "source_type": (
                    row.get("source_type") or "approved_policy_boundary_synthetic"
                ).strip(),
            }
            if not key or not all(
                value[field]
                for field in ("label", "scenario_group_id", "review_status")
            ):
                raise SplitError(f"{path}:{row_number} has an empty policy value")
            if value["required_split"] not in {"", *SPLITS}:
                raise SplitError(f"{path}:{row_number} has an invalid required split")
            if key in metadata:
                raise SplitError(f"{path}:{row_number} duplicates a policy narrative")
            metadata[key] = value
    return metadata


def reviewed_group_overrides(path: Path) -> dict[str, str]:
    if not path.is_file():
        return {}

    parent: dict[str, str] = {}
    label_by_text: dict[str, str] = {}

    def find(item: str) -> str:
        parent.setdefault(item, item)
        while parent[item] != item:
            parent[item] = parent[parent[item]]
            item = parent[item]
        return item

    def union(left: str, right: str) -> None:
        left_root = find(left)
        right_root = find(right)
        if left_root != right_root:
            parent[right_root] = left_root

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        required = {
            "label",
            "validation_text",
            "training_text",
            "review_action",
        }
        if reader.fieldnames is None or not required.issubset(reader.fieldnames):
            raise SplitError(f"{path} is missing grouping-review columns")
        for row_number, row in enumerate(reader, start=2):
            if row["review_action"] != "SAME_GROUP":
                continue
            label = row["label"].strip()
            left = normalized_text(row["validation_text"])
            right = normalized_text(row["training_text"])
            if not label or not left or not right:
                raise SplitError(f"{path}:{row_number} has an empty grouping value")
            for text in (left, right):
                previous = label_by_text.setdefault(text, label)
                if previous != label:
                    raise SplitError(
                        f"{path}:{row_number} groups one narrative across labels"
                    )
            union(left, right)

    components: dict[str, list[str]] = defaultdict(list)
    for text in parent:
        components[find(text)].append(text)

    overrides: dict[str, str] = {}
    for texts in components.values():
        label = label_by_text[texts[0]]
        digest = hashlib.sha256("\x1f".join(sorted(texts)).encode()).hexdigest()[:12]
        group_id = f"REVIEWED-{label}-{digest}"
        for text in texts:
            overrides[text] = group_id
    return overrides


def read_jsonl(path: Path) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    with path.open("r", encoding="utf-8") as handle:
        for line_number, line in enumerate(handle, start=1):
            if not line.strip():
                continue
            value = json.loads(line)
            if not isinstance(value, dict):
                raise SplitError(f"{path}:{line_number} is not a JSON object")
            rows.append(value)
    return rows


def stable_rank(seed: int, label: str, split: str, group_id: str) -> str:
    value = f"{seed}\x1f{label}\x1f{split}\x1f{group_id}".encode()
    return hashlib.sha256(value).hexdigest()


def label_sort_key(label: str) -> tuple[int, int]:
    if label == "NO_MATCH":
        return (0, 0)
    prefix, number = label.split("-", maxsplit=1)
    return (1 if prefix == "MINOR" else 2, int(number))


def choose_groups(
    groups: dict[str, list[dict[str, Any]]],
    target_rows: int,
    *,
    seed: int,
    label: str,
    split: str,
) -> set[str]:
    ordered = sorted(
        groups,
        key=lambda group_id: stable_rank(seed, label, split, group_id),
    )
    possibilities: dict[int, tuple[str, ...]] = {0: ()}
    for group_id in ordered:
        size = len(groups[group_id])
        additions = {
            total + size: selected + (group_id,)
            for total, selected in possibilities.items()
            if total + size <= target_rows + 3
        }
        for total, selected in additions.items():
            possibilities.setdefault(total, selected)

    positive = [total for total in possibilities if total > 0]
    if not positive:
        raise SplitError(f"{label} has no group available for {split}")
    best_total = min(
        positive,
        key=lambda total: (abs(total - target_rows), total > target_rows, total),
    )
    return set(possibilities[best_total])


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--input-csv",
        type=Path,
        default=DEFAULT_DATA_DIR / "prism_training.csv",
    )
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_DATA_DIR)
    parser.add_argument("--reference-dir", type=Path, default=DEFAULT_DATA_DIR)
    parser.add_argument(
        "--group-review-csv",
        type=Path,
        default=ML_ROOT / "data" / "semantic_group_review.csv",
    )
    parser.add_argument(
        "--review-status-csv",
        type=Path,
        default=None,
        help="Optional reviewed CSV containing description and review_status columns.",
    )
    parser.add_argument(
        "--policy-case-csv",
        type=Path,
        default=ML_ROOT / "data" / "major30_policy_cases.csv",
    )
    parser.add_argument(
        "--boundary-case-csv",
        type=Path,
        default=ML_ROOT / "data" / "classification_boundary_cases.csv",
    )
    parser.add_argument("--seed", type=int, default=20260731)
    parser.add_argument("--overwrite", action="store_true")
    parser.add_argument(
        "--check",
        action="store_true",
        help="Verify that the committed splits match the reviewed CSV without writing.",
    )
    arguments = parser.parse_args()

    if arguments.check and arguments.overwrite:
        parser.error("--check and --overwrite are mutually exclusive")

    output_paths = {
        split: arguments.output_dir / f"{split}.jsonl" for split in SPLITS
    }
    if (
        not arguments.check
        and not arguments.overwrite
        and any(path.exists() for path in output_paths.values())
    ):
        raise SplitError("Split files already exist; pass --overwrite to replace them")

    with arguments.input_csv.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames != ["description", "label", "language"]:
            raise SplitError(
                "Training CSV must contain description,label,language columns"
            )
        input_rows = list(reader)

    label_map_path = arguments.reference_dir / "label_map.json"
    label_map = json.loads(label_map_path.read_text(encoding="utf-8"))
    old_label_to_id = label_map["label_to_id"]
    label_titles = dict(label_map["label_titles"])
    label_titles["MAJOR-30"] = "Acts of subversion"

    reference_by_text: dict[str, dict[str, Any]] = {}
    for split in SPLITS:
        for row in read_jsonl(arguments.reference_dir / f"{split}.jsonl"):
            key = normalized_text(str(row["text"]))
            if key in reference_by_text:
                raise SplitError(f"Duplicate reference narrative: {row['text']}")
            reference_by_text[key] = row
    group_overrides = reviewed_group_overrides(arguments.group_review_csv)
    review_status_by_text = reviewed_statuses(arguments.review_status_csv)
    policy_metadata = policy_case_metadata(arguments.policy_case_csv)
    for key, value in policy_case_metadata(arguments.boundary_case_csv).items():
        if key in policy_metadata:
            raise SplitError(
                "Boundary-case narrative duplicates an existing policy case: "
                + key
            )
        policy_metadata[key] = value

    eligible = [
        row for row in input_rows if row["label"] not in MANUAL_ONLY_LABELS
    ]
    input_labels = {row["label"] for row in eligible}
    present_inapplicable = input_labels & INAPPLICABLE_LABELS
    if present_inapplicable:
        raise SplitError(
            f"Training CSV contains inapplicable labels: {sorted(present_inapplicable)}"
        )
    unknown_input_labels = input_labels - set(label_titles)
    if unknown_input_labels:
        raise SplitError(
            f"Training CSV contains labels missing from the handbook map: "
            f"{sorted(unknown_input_labels)}"
        )
    expected_labels = (set(old_label_to_id) | input_labels) - INAPPLICABLE_LABELS
    if input_labels != expected_labels:
        raise SplitError(
            "Eligible CSV labels do not match the active handbook labels: "
            f"missing={sorted(expected_labels - input_labels)}, "
            f"unexpected={sorted(input_labels - expected_labels)}"
        )
    ordered_labels = sorted(expected_labels, key=label_sort_key)
    label_to_id = {
        label: label_id for label_id, label in enumerate(ordered_labels)
    }

    grouped_by_label: dict[str, dict[str, list[dict[str, Any]]]] = defaultdict(
        lambda: defaultdict(list)
    )
    forced_group_splits: dict[str, str] = {}
    prepared: list[dict[str, Any]] = []

    for row_number, row in enumerate(eligible, start=2):
        description = row["description"].strip()
        label = row["label"].strip()
        language = LANGUAGE_MAP.get(row["language"].strip().casefold())
        if not description or not label or language is None:
            raise SplitError(f"Invalid training row {row_number}")

        key = normalized_text(description)
        reference = reference_by_text.get(key)
        policy_case = policy_metadata.get(key)
        if policy_case is not None:
            if policy_case["label"] != label:
                raise SplitError(
                    f"Policy metadata label {policy_case['label']} does not match {label}"
                )
            group_id = policy_case["scenario_group_id"]
            source_type = policy_case["source_type"]
            difficulty = "hard"
            review_status = policy_case["review_status"]
            required_split = policy_case["required_split"]
            if required_split:
                previous_split = forced_group_splits.setdefault(group_id, required_split)
                if previous_split != required_split:
                    raise SplitError(f"Policy group {group_id} has conflicting splits")
        elif key in group_overrides:
            group_id = group_overrides[key]
            source_type = "reviewed_multilingual_group"
            difficulty = (
                reference.get("difficulty", "unknown")
                if reference is not None
                else "unknown"
            )
            review_status = "grouping_reviewed"
        elif reference is not None:
            group_id = str(reference["scenario_group_id"])
            source_type = reference.get("source_type", "handbook_synthetic")
            difficulty = reference.get("difficulty", "unknown")
            review_status = reference.get("review_status", "draft_synthetic")
        elif key in CUSTOM_GROUPS:
            group_id = CUSTOM_GROUPS[key]
            source_type = "reviewed_training"
            difficulty = "medium"
            review_status = "pending_human_review"
        else:
            # Do not use a per-run sequence here. Reference datasets can already
            # contain LEGACY-<label>-NNN identifiers, so restarting at 001 would
            # silently merge an unrelated new narrative into an existing group.
            # A text-derived identifier is deterministic, order-independent, and
            # cannot collide with the legacy namespace.
            group_id = generated_group_id(label, key)
            source_type = "legacy_training_csv"
            difficulty = "unknown"
            review_status = "pending_human_review"

        if reference is not None:
            reference_split = str(reference["split"])
            previous_split = forced_group_splits.setdefault(group_id, reference_split)
            if previous_split != reference_split:
                raise SplitError(
                    f"Reference group {group_id} appears across multiple splits"
                )

        review_status = review_status_by_text.get(key, review_status)

        prepared_row = {
            "id": "",
            "text": description,
            "primary_offense_code": label,
            "offense_type": (
                "none"
                if label == "NO_MATCH"
                else "major"
                if label.startswith("MAJOR-")
                else "minor"
            ),
            "offense_title": label_titles[label],
            "language": language,
            "split": "",
            "scenario_group_id": group_id,
            "difficulty": difficulty,
            "source_type": source_type,
            "policy_version": "NU Clark Student Handbook 2025 Edition",
            "applicable_to_nu_clark": True,
            "model_eligible": True,
            "pii_checked": False,
            "review_status": review_status,
            "label_id": label_to_id[label],
        }
        grouped_by_label[label][group_id].append(prepared_row)
        prepared.append(prepared_row)

    for label, groups in grouped_by_label.items():
        total_rows = sum(len(rows) for rows in groups.values())
        target = max(1, round(total_rows * 0.15))
        forced_train = {
            group_id for group_id in groups if forced_group_splits.get(group_id) == "train"
        }
        forced_validation = {
            group_id
            for group_id in groups
            if forced_group_splits.get(group_id) == "validation"
        }
        forced_test = {
            group_id for group_id in groups if forced_group_splits.get(group_id) == "test"
        }
        available_for_test = {
            group_id: rows
            for group_id, rows in groups.items()
            if group_id not in forced_train | forced_validation | forced_test
        }
        remaining_test_target = max(
            0,
            target - sum(len(groups[group_id]) for group_id in forced_test),
        )
        selected_test = (
            choose_groups(
                available_for_test,
                remaining_test_target,
                seed=arguments.seed,
                label=label,
                split="test",
            )
            if remaining_test_target > 0 and available_for_test
            else set()
        )
        test_groups = forced_test | selected_test
        remaining = {
            group_id: rows
            for group_id, rows in groups.items()
            if group_id not in test_groups | forced_train | forced_validation
        }
        remaining_validation_target = max(
            0,
            target - sum(len(groups[group_id]) for group_id in forced_validation),
        )
        selected_validation = (
            choose_groups(
                remaining,
                remaining_validation_target,
                seed=arguments.seed,
                label=label,
                split="validation",
            )
            if remaining_validation_target > 0 and remaining
            else set()
        )
        validation_groups = forced_validation | selected_validation

        for group_id, rows in groups.items():
            split = (
                "test"
                if group_id in test_groups
                else "validation"
                if group_id in validation_groups
                else "train"
            )
            for row in rows:
                row["split"] = split

    split_rows: dict[str, list[dict[str, Any]]] = defaultdict(list)
    counters: defaultdict[str, int] = defaultdict(int)
    for row in sorted(
        prepared,
        key=lambda item: (
            item["split"],
            item["primary_offense_code"],
            item["scenario_group_id"],
            item["language"],
            item["text"],
        ),
    ):
        label = str(row["primary_offense_code"])
        counters[label] += 1
        row["id"] = f"PRISM-{label}-{counters[label]:03d}"
        split_rows[str(row["split"])].append(row)

    updated_label_map = dict(label_map)
    updated_label_map["id_to_label"] = {
        str(label_id): label for label, label_id in label_to_id.items()
    }
    updated_label_map["label_to_id"] = label_to_id
    updated_label_map["label_titles"] = label_titles
    excluded = dict(updated_label_map.get("excluded_labels", {}))
    excluded["MAJOR-16"] = (
        "Preventing circulation of a recognized student publication is not "
        "currently applicable because NU Clark has no campus publication "
        "distributed through campus newsstands."
    )
    excluded["MAJOR-22"] = (
        "Violation of Student Athlete Dormitory Rules and Regulations is not "
        "applicable because NU Clark has no on-campus dormitory."
    )
    updated_label_map["excluded_labels"] = dict(sorted(excluded.items()))
    updated_label_map.pop("policy_definition_required_labels", None)
    operational = dict(updated_label_map.get("operational_definition_labels", {}))
    operational["MAJOR-30"] = (
        "Deliberate, coordinated action intended to violently overthrow or "
        "replace lawful authority, incite serious illegal action against the "
        "school or state, or sabotage critical school operations to threaten "
        "institutional safety, security, or lawful governance. Peaceful criticism, "
        "lawful organizations and demonstrations, and good-faith complaints are excluded."
    )
    updated_label_map["operational_definition_labels"] = dict(sorted(operational.items()))
    label_map_output = arguments.output_dir / "label_map.json"
    split_outputs = {
        split: "".join(
            json.dumps(row, ensure_ascii=False) + "\n"
            for row in split_rows[split]
        )
        for split in SPLITS
    }
    label_map_text = json.dumps(
        updated_label_map,
        ensure_ascii=False,
        indent=2,
    ) + "\n"

    if arguments.check:
        stale = [
            path.name
            for split, path in output_paths.items()
            if not path.is_file()
            or path.read_text(encoding="utf-8") != split_outputs[split]
        ]
        if (
            not label_map_output.is_file()
            or label_map_output.read_text(encoding="utf-8") != label_map_text
        ):
            stale.append(label_map_output.name)
        if stale:
            raise SplitError(
                "Generated training splits are not current: " + ", ".join(stale)
            )
        print("Committed training splits match the reviewed CSV.")
        return 0

    arguments.output_dir.mkdir(parents=True, exist_ok=True)
    for split, path in output_paths.items():
        temporary = path.with_suffix(".jsonl.tmp")
        temporary.write_text(split_outputs[split], encoding="utf-8", newline="\n")
        temporary.replace(path)

    label_map_temporary = label_map_output.with_suffix(".json.tmp")
    label_map_temporary.write_text(
        label_map_text,
        encoding="utf-8",
        newline="\n",
    )
    label_map_temporary.replace(label_map_output)

    counts = {split: len(split_rows[split]) for split in SPLITS}
    print(
        f"Built training splits from {len(input_rows)} CSV rows; "
        f"eligible={len(prepared)}, excluded_manual_only="
        f"{len(input_rows) - len(prepared)}, split_rows={counts}."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
