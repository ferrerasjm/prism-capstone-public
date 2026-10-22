#!/usr/bin/env python3
"""Remove PDF-listed narratives from a legacy training CSV."""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import re
import shutil
import subprocess
import sys
import unicodedata
from collections import Counter
from pathlib import Path
from typing import Any


EXEMPT_ZERO_LABELS = {"MAJOR-16", "MAJOR-22"}
EXEMPT_ZERO_NOTES = {
    "MAJOR-16": (
        "Student-publication circulation offense is not currently applicable "
        "because NU Clark has no campus publication distributed through "
        "campus newsstands."
    ),
    "MAJOR-22": "Dormitory offense is not applicable to NU Clark.",
}
MANUAL_ONLY_LABELS = {
    "MAJOR-19": "Rule-derived habitual disregard; do not train from prose.",
}
REQUIRED_COLUMNS = {"description", "label", "language"}


class CleaningError(RuntimeError):
    """Raised when exclusions cannot be applied unambiguously."""


def normalized_text(value: str) -> str:
    value = unicodedata.normalize("NFKC", value).casefold()
    return " ".join(re.findall(r"[^\W_]+", value, flags=re.UNICODE))


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest().upper()


def read_csv(path: Path) -> tuple[list[str], list[dict[str, str]]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        fields = reader.fieldnames
        if fields is None or not REQUIRED_COLUMNS.issubset(fields):
            raise CleaningError(
                f"{path} must contain description, label, and language columns"
            )
        rows = list(reader)

    if not rows:
        raise CleaningError(f"{path} contains no training rows")
    for row_number, row in enumerate(rows, start=2):
        for field in REQUIRED_COLUMNS:
            if not isinstance(row.get(field), str) or not row[field].strip():
                raise CleaningError(f"{path}:{row_number} has an empty {field}")
    return fields, rows


def extract_pdf_items(pdf_path: Path, executable: str) -> list[str]:
    result = subprocess.run(
        [
            executable,
            "-layout",
            "-enc",
            "UTF-8",
            str(pdf_path),
            "-",
        ],
        check=False,
        capture_output=True,
    )
    if result.returncode != 0:
        message = result.stderr.decode("utf-8", errors="replace").strip()
        raise CleaningError(f"pdftotext failed: {message}")
    text = result.stdout.decode("utf-8")
    items = [
        item.strip()
        for item in re.split(r"●\u200b?", text)[1:]
        if item.strip()
    ]
    if not items:
        raise CleaningError(f"No bulleted exclusions were extracted from {pdf_path}")
    return items


def match_exclusions(
    rows: list[dict[str, str]],
    pdf_items: list[str],
) -> set[int]:
    normalized_rows: list[tuple[int, str]] = []
    seen_descriptions: dict[str, int] = {}
    for index, row in enumerate(rows):
        normalized = normalized_text(row["description"])
        if normalized in seen_descriptions:
            raise CleaningError(
                "Duplicate normalized descriptions at CSV rows "
                f"{seen_descriptions[normalized] + 2} and {index + 2}"
            )
        seen_descriptions[normalized] = index
        normalized_rows.append((index, normalized))

    matched_indexes: set[int] = set()
    for item_number, item in enumerate(pdf_items, start=1):
        normalized_item = normalized_text(item)
        matches = [
            index
            for index, description in normalized_rows
            if description and description in normalized_item
        ]
        if len(matches) != 1:
            raise CleaningError(
                f"PDF item {item_number} matched {len(matches)} CSV rows; "
                "cleaning stopped to avoid an incorrect removal"
            )
        index = matches[0]
        if index in matched_indexes:
            raise CleaningError(
                f"PDF item {item_number} repeats an already matched CSV row"
            )
        matched_indexes.add(index)
    return matched_indexes


def coverage_rows(
    original: list[dict[str, str]],
    removed: list[dict[str, str]],
    remaining: list[dict[str, str]],
) -> list[dict[str, Any]]:
    original_counts = Counter(row["label"] for row in original)
    removed_counts = Counter(row["label"] for row in removed)
    remaining_counts = Counter(row["label"] for row in remaining)
    coverage: list[dict[str, Any]] = []

    for label in sorted(original_counts):
        remaining_count = remaining_counts[label]
        if label in EXEMPT_ZERO_LABELS and remaining_count == 0:
            status = "EXEMPT_NOT_APPLICABLE"
            note = EXEMPT_ZERO_NOTES[label]
        elif remaining_count == 0:
            status = "MISSING_NO_CASES"
            note = (
                "Required abstention/manual-review class has no remaining cases."
                if label == "NO_MATCH"
                else "No cases remain after applying the PDF exclusions."
            )
        elif label in MANUAL_ONLY_LABELS:
            status = "MANUAL_ONLY"
            note = MANUAL_ONLY_LABELS[label]
        elif remaining_count < 20:
            status = "NEEDS_REPLACEMENT_CASES"
            note = "Below the original target of 20 cases."
        else:
            status = "AVAILABLE"
            note = ""

        coverage.append(
            {
                "label": label,
                "original_cases": original_counts[label],
                "removed_by_pdf": removed_counts[label],
                "remaining_cases": remaining_count,
                "status": status,
                "note": note,
            }
        )
    return coverage


def write_csv(
    path: Path,
    fields: list[str],
    rows: list[dict[str, Any]],
) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-csv", type=Path, required=True)
    parser.add_argument("--exclusion-pdf", type=Path, required=True)
    parser.add_argument("--output-csv", type=Path, required=True)
    parser.add_argument("--coverage-csv", type=Path, required=True)
    parser.add_argument("--report-json", type=Path, required=True)
    parser.add_argument("--pdftotext", default=None)
    parser.add_argument("--overwrite", action="store_true")
    arguments = parser.parse_args()

    outputs = [
        arguments.output_csv,
        arguments.coverage_csv,
        arguments.report_json,
    ]
    existing = [path for path in outputs if path.exists()]
    if existing and not arguments.overwrite:
        print(
            "Cleaning failed: output files already exist; use --overwrite: "
            + ", ".join(str(path) for path in existing),
            file=sys.stderr,
        )
        return 1

    try:
        executable = arguments.pdftotext or shutil.which("pdftotext")
        if executable is None:
            raise CleaningError("pdftotext is not available")
        fields, rows = read_csv(arguments.input_csv)
        pdf_items = extract_pdf_items(arguments.exclusion_pdf, executable)
        matched_indexes = match_exclusions(rows, pdf_items)
        removed = [
            row for index, row in enumerate(rows) if index in matched_indexes
        ]
        remaining = [
            row for index, row in enumerate(rows) if index not in matched_indexes
        ]
        coverage = coverage_rows(rows, removed, remaining)
        flagged_zero = [
            row["label"]
            for row in coverage
            if row["status"] == "MISSING_NO_CASES"
        ]
        exempt_zero = [
            row["label"]
            for row in coverage
            if row["status"] == "EXEMPT_NOT_APPLICABLE"
        ]

        write_csv(arguments.output_csv, fields, remaining)
        write_csv(
            arguments.coverage_csv,
            [
                "label",
                "original_cases",
                "removed_by_pdf",
                "remaining_cases",
                "status",
                "note",
            ],
            coverage,
        )
        report = {
            "schema_version": 1,
            "input": {
                "path": str(arguments.input_csv.resolve()),
                "sha256": sha256_file(arguments.input_csv),
                "rows": len(rows),
            },
            "exclusion_pdf": {
                "path": str(arguments.exclusion_pdf.resolve()),
                "sha256": sha256_file(arguments.exclusion_pdf),
                "bullets": len(pdf_items),
            },
            "output": {
                "path": str(arguments.output_csv.resolve()),
                "sha256": sha256_file(arguments.output_csv),
                "rows": len(remaining),
            },
            "removed_rows": len(removed),
            "matched_pdf_items": len(matched_indexes),
            "unmatched_pdf_items": 0,
            "ambiguous_pdf_items": 0,
            "flagged_zero_case_labels": flagged_zero,
            "exempt_zero_case_labels": exempt_zero,
            "manual_only_labels": MANUAL_ONLY_LABELS,
            "can_use_as_is_for_model_training": not flagged_zero
            and not any(
                row["status"] in {"MANUAL_ONLY", "NEEDS_REPLACEMENT_CASES"}
                for row in coverage
            ),
        }
        arguments.report_json.parent.mkdir(parents=True, exist_ok=True)
        arguments.report_json.write_text(
            json.dumps(report, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
            newline="\n",
        )
    except (CleaningError, OSError, csv.Error) as error:
        print(f"Cleaning failed: {error}", file=sys.stderr)
        return 1

    print(
        f"Cleaned dataset: {len(rows)} input rows, {len(removed)} removed, "
        f"{len(remaining)} remaining; flagged zero-case labels="
        f"{flagged_zero or 'none'}, exempt={exempt_zero or 'none'}."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
