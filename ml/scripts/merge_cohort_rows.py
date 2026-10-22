#!/usr/bin/env python3
"""Merge an authored cohort CSV into the reviewed training corpus.

A cohort file carries the narrative plus the metadata the split builder needs
(`scenario_group_id`, `required_split`, `source_type`). This script appends the
narrative columns to `data/processed/prism_training.csv` and the metadata to
`data/classification_boundary_cases.csv`, which is where
`build_training_splits.py` looks for grouped, split-pinned rows.

Keeping the two writes together is what stops a multilingual scenario group from
being split across train and evaluation: without a `scenario_group_id`, the
builder derives a per-narrative group and the translations of one incident can
leak into different splits.

The merge is idempotent and refuses to run if a narrative already exists under a
different label or group.
"""

from __future__ import annotations

import argparse
import csv
import sys
from pathlib import Path


ML_ROOT = Path(__file__).resolve().parents[1]
TRAINING_CSV = ML_ROOT / "data" / "processed" / "prism_training.csv"
BOUNDARY_CSV = ML_ROOT / "data" / "classification_boundary_cases.csv"
POLICY_CSV = ML_ROOT / "data" / "major30_policy_cases.csv"

TRAINING_FIELDS = ["description", "label", "language"]
BOUNDARY_FIELDS = [
    "description",
    "label",
    "language",
    "scenario_group_id",
    "review_status",
    "required_split",
    "source_type",
]
SPLITS = ("train", "validation", "test")


class MergeError(RuntimeError):
    """Raised when a cohort cannot be merged safely."""


def normalized(value: str) -> str:
    return " ".join(value.casefold().split())


def read_csv(path: Path) -> list[dict[str, str]]:
    if not path.is_file():
        raise MergeError(f"Missing CSV: {path}")
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("cohort_csv", type=Path)
    parser.add_argument("--training-csv", type=Path, default=TRAINING_CSV)
    parser.add_argument("--boundary-csv", type=Path, default=BOUNDARY_CSV)
    parser.add_argument("--policy-csv", type=Path, default=POLICY_CSV)
    parser.add_argument("--dry-run", action="store_true")
    arguments = parser.parse_args()

    try:
        cohort = read_csv(arguments.cohort_csv)
        if not cohort:
            raise MergeError("Cohort file contains no rows")
        missing = set(BOUNDARY_FIELDS) - set(cohort[0])
        if missing:
            raise MergeError(f"Cohort is missing columns: {sorted(missing)}")

        training = read_csv(arguments.training_csv)
        boundary = read_csv(arguments.boundary_csv)
        policy = read_csv(arguments.policy_csv)

        training_by_text = {normalized(row["description"]): row for row in training}
        # A narrative may only carry metadata in one file; the split builder
        # raises if a boundary case duplicates an approved policy case.
        metadata_by_text = {
            normalized(row["description"]): row for row in boundary + policy
        }

        new_training: list[dict[str, str]] = []
        new_boundary: list[dict[str, str]] = []
        skipped = 0

        for index, row in enumerate(cohort, start=2):
            description = row["description"].strip()
            label = row["label"].strip()
            language = row["language"].strip().casefold()
            group_id = row["scenario_group_id"].strip()
            required_split = row["required_split"].strip()
            if not description or not label or not group_id:
                raise MergeError(f"{arguments.cohort_csv}:{index} has an empty value")
            if language not in {"english", "tagalog", "taglish"}:
                raise MergeError(f"{arguments.cohort_csv}:{index} has an unknown language")
            if required_split not in SPLITS:
                raise MergeError(f"{arguments.cohort_csv}:{index} has an invalid split")

            key = normalized(description)
            existing_training = training_by_text.get(key)
            existing_metadata = metadata_by_text.get(key)

            if existing_training is not None:
                if existing_training["label"].strip() != label:
                    raise MergeError(
                        f"{arguments.cohort_csv}:{index} relabels an existing narrative "
                        f"({existing_training['label']} -> {label})"
                    )
                skipped += 1
            else:
                new_training.append(
                    {
                        "description": description,
                        "label": label,
                        "language": language,
                    }
                )
                training_by_text[key] = {"description": description, "label": label}

            if existing_metadata is not None:
                if existing_metadata.get("scenario_group_id", "").strip() != group_id:
                    raise MergeError(
                        f"{arguments.cohort_csv}:{index} regroups an existing narrative"
                    )
                continue

            entry = {
                "description": description,
                "label": label,
                "language": language,
                "scenario_group_id": group_id,
                "review_status": (row.get("review_status") or "pending_human_review").strip(),
                "required_split": required_split,
                "source_type": row["source_type"].strip(),
            }
            new_boundary.append(entry)
            metadata_by_text[key] = entry

        print(
            f"Cohort rows={len(cohort)}; new narratives={len(new_training)}; "
            f"new metadata rows={len(new_boundary)}; already present={skipped}."
        )
        if arguments.dry_run:
            return 0

        if new_training:
            with arguments.training_csv.open(
                "a", encoding="utf-8", newline=""
            ) as handle:
                writer = csv.DictWriter(
                    handle,
                    fieldnames=TRAINING_FIELDS,
                    quoting=csv.QUOTE_ALL,
                    lineterminator="\n",
                )
                writer.writerows(new_training)
        if new_boundary:
            with arguments.boundary_csv.open(
                "a", encoding="utf-8", newline=""
            ) as handle:
                writer = csv.DictWriter(
                    handle,
                    fieldnames=BOUNDARY_FIELDS,
                    quoting=csv.QUOTE_ALL,
                    lineterminator="\n",
                )
                writer.writerows(new_boundary)
    except (OSError, ValueError, MergeError) as error:
        print(f"Cohort merge failed: {error}", file=sys.stderr)
        return 1

    print("Merged. Rebuild the splits with build_training_splits.py --overwrite.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
