#!/usr/bin/env python3
"""Validate and expand the NU Clark offense-scenario source shards."""

from __future__ import annotations

import argparse
import csv
import difflib
import hashlib
import io
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any


ML_ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ML_ROOT / "data" / "source"
PROCESSED_DIR = ML_ROOT / "data" / "processed"
LANGUAGES = ("en", "tl", "taglish")
TAGALOG_MARKERS = {
    "ang",
    "ay",
    "bago",
    "bilang",
    "dahil",
    "dumalo",
    "ginamit",
    "habang",
    "hindi",
    "inamin",
    "iniulat",
    "isang",
    "ito",
    "kahit",
    "kanilang",
    "kaniya",
    "kaklase",
    "kinumpirma",
    "kung",
    "matapos",
    "mga",
    "mula",
    "nag",
    "nang",
    "nakita",
    "ng",
    "niya",
    "pagkatapos",
    "para",
    "pero",
    "pumasok",
    "sa",
    "saka",
    "siya",
    "upang",
    "wala",
    "walang",
}
ENGLISH_MARKERS = {
    "access",
    "activity",
    "after",
    "before",
    "campus",
    "class",
    "computer",
    "during",
    "event",
    "faculty",
    "group",
    "laboratory",
    "library",
    "member",
    "office",
    "officer",
    "online",
    "report",
    "reported",
    "security",
    "student",
    "the",
    "university",
    "while",
    "with",
    "without",
}
SOURCE_TYPES = {
    "violation_slip",
    "student_complaint",
    "faculty_report",
    "security_report",
    "direct_encoding",
}
OFFENSE_SPLITS = {"train": 3, "validation": 1, "test": 1}
NO_MATCH_SPLITS = {"train": 20, "validation": 4, "test": 4}
EXPECTED_OFFENSE_CODES = {
    *(f"MINOR-{number:02d}" for number in range(1, 16)),
    *(f"MAJOR-{number:02d}" for number in range(1, 48) if number != 22),
}
EXPECTED_CODES = EXPECTED_OFFENSE_CODES | {"NO_MATCH"}
MODEL_INELIGIBLE_CODES = {"MAJOR-19"}
CODE_PATTERN = re.compile(r"^(?:MINOR|MAJOR)-\d{2}$")
GROUP_PATTERN = re.compile(r"^(?P<code>(?:MINOR|MAJOR)-\d{2}|NO_MATCH)-G\d{2}$")


class DatasetError(RuntimeError):
    """Raised when a source shard violates the dataset contract."""


def normalized_text(value: str) -> str:
    return " ".join(re.findall(r"[a-z0-9]+", value.casefold()))


def language_tokens(value: str) -> set[str]:
    return set(re.findall(r"[a-záéíóúñ]+", value.casefold()))


def validate_language_content(text: str, language: str, context: str) -> None:
    tokens = language_tokens(text)
    if language == "tl" and not tokens.intersection(TAGALOG_MARKERS):
        raise DatasetError(
            f"{context}: Tagalog narrative does not contain a Tagalog language marker"
        )
    if language == "taglish":
        if not tokens.intersection(TAGALOG_MARKERS):
            raise DatasetError(
                f"{context}: Taglish narrative does not contain a Tagalog language marker"
            )
        if not tokens.intersection(ENGLISH_MARKERS):
            raise DatasetError(
                f"{context}: Taglish narrative does not contain an English language marker"
            )


def validate_cross_split_similarity(rows: list[dict[str, Any]]) -> None:
    by_language: dict[
        str, list[tuple[dict[str, Any], str, set[str]]]
    ] = defaultdict(list)
    for row in rows:
        normalized = normalized_text(row["text"])
        by_language[row["language"]].append(
            (row, normalized, set(normalized.split()))
        )

    for language, language_rows in by_language.items():
        for index, (left, left_text, left_words) in enumerate(language_rows):
            for right, right_text, right_words in language_rows[index + 1 :]:
                if left["split"] == right["split"]:
                    continue
                if left["scenario_group_id"] == right["scenario_group_id"]:
                    raise DatasetError(
                        f"{left['scenario_group_id']}: translations or paraphrases "
                        "from one group cannot cross data splits"
                    )

                length_ratio = min(len(left_text), len(right_text)) / max(
                    len(left_text), len(right_text)
                )
                if length_ratio < 0.8:
                    continue

                word_union = left_words | right_words
                word_overlap = len(left_words & right_words) / len(word_union)
                if word_overlap < 0.6:
                    continue

                matcher = difflib.SequenceMatcher(
                    None,
                    left_text,
                    right_text,
                    autojunk=False,
                )
                if matcher.quick_ratio() < 0.92:
                    continue
                similarity = matcher.ratio()
                if similarity >= 0.92:
                    raise DatasetError(
                        "Possible cross-split paraphrase leakage "
                        f"({similarity:.3f}, {language}): "
                        f"{left['id']} and {right['id']}"
                    )


def require_string(record: dict[str, Any], field: str, context: str) -> str:
    value = record.get(field)
    if not isinstance(value, str) or not value.strip():
        raise DatasetError(f"{context}: `{field}` must be a non-empty string")
    return value.strip()


def expected_handbook_page(code: str) -> str:
    number = int(code[-2:])
    if code.startswith("MINOR-"):
        if number <= 2:
            return "33"
        if number == 3:
            return "33-34"
        return "34"

    if number <= 3:
        return "35"
    if number <= 16:
        return "36"
    if number <= 30:
        return "37"
    if number <= 45:
        return "38"
    return "39"


def load_source_records() -> list[dict[str, Any]]:
    if not SOURCE_DIR.exists():
        raise DatasetError(f"Source directory does not exist: {SOURCE_DIR}")

    paths = sorted(SOURCE_DIR.glob("*.json"))
    if not paths:
        raise DatasetError(f"No JSON source shards found in {SOURCE_DIR}")

    records: list[dict[str, Any]] = []
    for path in paths:
        try:
            shard = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as error:
            raise DatasetError(f"{path}: unable to read valid JSON: {error}") from error

        if not isinstance(shard, list):
            raise DatasetError(f"{path}: top-level JSON value must be an array")

        for item in shard:
            if not isinstance(item, dict):
                raise DatasetError(f"{path}: every array item must be an object")
            item["_source_file"] = path.name
            records.append(item)

    return records


def validate_and_expand(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    seen_codes: set[str] = set()
    seen_group_ids: set[str] = set()
    seen_example_ids: set[str] = set()
    seen_texts: dict[str, str] = {}
    expanded: list[dict[str, Any]] = []

    for record in records:
        source_file = record["_source_file"]
        code = require_string(record, "offense_code", source_file)
        context = f"{source_file}:{code}"

        if code not in EXPECTED_CODES:
            raise DatasetError(f"{context}: unexpected or inapplicable offense code")
        if code == "MAJOR-22":
            raise DatasetError(f"{context}: dormitory offense must not be included")
        if code in seen_codes:
            raise DatasetError(f"{context}: duplicate offense code")
        seen_codes.add(code)

        offense_type = require_string(record, "offense_type", context)
        expected_type = (
            "none"
            if code == "NO_MATCH"
            else ("minor" if code.startswith("MINOR-") else "major")
        )
        if offense_type != expected_type:
            raise DatasetError(
                f"{context}: offense_type must be `{expected_type}`, got `{offense_type}`"
            )

        title = require_string(record, "offense_title", context)
        section = record.get("handbook_section")
        item = record.get("handbook_item")
        page = record.get("handbook_page")

        if code != "NO_MATCH":
            if not CODE_PATTERN.fullmatch(code):
                raise DatasetError(f"{context}: malformed offense code")
            if not isinstance(section, str) or section not in {"VI.D", "VI.E"}:
                raise DatasetError(f"{context}: handbook_section must be VI.D or VI.E")
            if not isinstance(item, int) or item < 1:
                raise DatasetError(f"{context}: handbook_item must be a positive integer")
            if not isinstance(page, (str, int)) or str(page).strip() == "":
                raise DatasetError(f"{context}: handbook_page is required")

            expected_item = int(code[-2:])
            expected_section = "VI.D" if code.startswith("MINOR-") else "VI.E"
            expected_page = expected_handbook_page(code)
            if item != expected_item:
                raise DatasetError(
                    f"{context}: handbook_item must be {expected_item}, got {item}"
                )
            if section != expected_section:
                raise DatasetError(
                    f"{context}: handbook_section must be {expected_section}"
                )
            if str(page) != expected_page:
                raise DatasetError(
                    f"{context}: handbook_page must be {expected_page}, got {page}"
                )
        elif any(value is not None for value in (section, item, page)):
            raise DatasetError(f"{context}: NO_MATCH must not cite a handbook item")

        groups = record.get("scenario_groups")
        expected_group_count = sum(
            NO_MATCH_SPLITS.values()
            if code == "NO_MATCH"
            else OFFENSE_SPLITS.values()
        )
        if not isinstance(groups, list) or len(groups) != expected_group_count:
            raise DatasetError(
                f"{context}: expected {expected_group_count} scenario_groups"
            )

        split_counts: Counter[str] = Counter()
        for group in groups:
            if not isinstance(group, dict):
                raise DatasetError(f"{context}: scenario group must be an object")

            group_id = require_string(group, "scenario_group_id", context)
            group_context = f"{context}:{group_id}"
            match = GROUP_PATTERN.fullmatch(group_id)
            if not match or match.group("code") != code:
                raise DatasetError(
                    f"{group_context}: scenario_group_id must begin with its offense code"
                )
            if group_id in seen_group_ids:
                raise DatasetError(f"{group_context}: duplicate scenario_group_id")
            seen_group_ids.add(group_id)

            split = require_string(group, "split", group_context)
            if split not in {"train", "validation", "test"}:
                raise DatasetError(f"{group_context}: invalid split `{split}`")
            split_counts[split] += 1

            difficulty = require_string(group, "difficulty", group_context)
            if difficulty not in {"easy", "medium", "hard"}:
                raise DatasetError(f"{group_context}: invalid difficulty `{difficulty}`")

            source_type = require_string(group, "source_type", group_context)
            if source_type not in SOURCE_TYPES:
                raise DatasetError(
                    f"{group_context}: source_type must match an application value"
                )
            location_context = require_string(group, "location_context", group_context)
            texts = group.get("texts")
            if not isinstance(texts, dict) or set(texts) != set(LANGUAGES):
                raise DatasetError(
                    f"{group_context}: texts must contain exactly en, tl, and taglish"
                )

            for language in LANGUAGES:
                text = texts[language]
                if not isinstance(text, str) or len(text.split()) < 6:
                    raise DatasetError(
                        f"{group_context}:{language}: narrative must have at least six words"
                    )
                text = " ".join(text.strip().split())
                validate_language_content(text, language, f"{group_context}:{language}")
                if re.search(r"\b(?:MINOR|MAJOR)-\d{2}\b", text, re.IGNORECASE):
                    raise DatasetError(
                        f"{group_context}:{language}: narrative leaks its offense code"
                    )
                if re.search(r"\b[\w.+-]+@[\w.-]+\.[a-z]{2,}\b", text, re.IGNORECASE):
                    raise DatasetError(
                        f"{group_context}:{language}: narrative contains an email address"
                    )
                example_id = f"{group_id}-{language.upper()}"
                if example_id in seen_example_ids:
                    raise DatasetError(f"{group_context}: duplicate example ID")
                seen_example_ids.add(example_id)

                normalized = normalized_text(text)
                if normalized in seen_texts:
                    raise DatasetError(
                        f"{group_context}:{language}: duplicate narrative also used by "
                        f"{seen_texts[normalized]}"
                    )
                seen_texts[normalized] = example_id

                expanded.append(
                    {
                        "id": example_id,
                        "text": text,
                        "primary_offense_code": code,
                        "offense_type": offense_type,
                        "offense_title": title,
                        "language": language,
                        "split": split,
                        "scenario_group_id": group_id,
                        "difficulty": difficulty,
                        "source_type": source_type,
                        "location_context": location_context,
                        "handbook_section": section,
                        "handbook_item": item,
                        "handbook_page": str(page) if page is not None else None,
                        "policy_version": "NU Clark Student Handbook 2025 Edition",
                        "applicable_to_nu_clark": code != "NO_MATCH",
                        "model_eligible": code not in MODEL_INELIGIBLE_CODES,
                        "pii_checked": True,
                        "review_status": (
                            "approved_policy_boundary"
                            if code == "MAJOR-30"
                            else "draft_synthetic"
                        ),
                    }
                )

        expected_splits = NO_MATCH_SPLITS if code == "NO_MATCH" else OFFENSE_SPLITS
        if dict(split_counts) != expected_splits:
            raise DatasetError(
                f"{context}: split distribution must be {expected_splits}, "
                f"got {dict(split_counts)}"
            )

    missing_codes = EXPECTED_CODES - seen_codes
    extra_codes = seen_codes - EXPECTED_CODES
    if missing_codes or extra_codes:
        raise DatasetError(
            f"Label coverage mismatch; missing={sorted(missing_codes)}, "
            f"extra={sorted(extra_codes)}"
        )

    validate_cross_split_similarity(expanded)

    model_codes = seen_codes - MODEL_INELIGIBLE_CODES
    labels = sorted(
        model_codes,
        key=lambda code: (
            0 if code == "NO_MATCH" else 1 if code.startswith("MINOR-") else 2,
            code,
        ),
    )
    label_ids = {code: index for index, code in enumerate(labels)}
    for row in expanded:
        row["label_id"] = label_ids.get(row["primary_offense_code"])

    return sorted(expanded, key=lambda row: row["id"])


def jsonl(rows: list[dict[str, Any]]) -> str:
    return "".join(
        json.dumps(row, ensure_ascii=False, separators=(",", ":")) + "\n"
        for row in rows
    )


def review_csv(rows: list[dict[str, Any]]) -> str:
    fields = [
        "id",
        "text",
        "primary_offense_code",
        "offense_title",
        "offense_type",
        "language",
        "split",
        "scenario_group_id",
        "difficulty",
        "source_type",
        "location_context",
        "handbook_section",
        "handbook_item",
        "handbook_page",
        "policy_version",
        "model_eligible",
        "pii_checked",
        "review_status",
    ]
    output = io.StringIO(newline="")
    writer = csv.DictWriter(output, fieldnames=fields, lineterminator="\n")
    writer.writeheader()
    for row in rows:
        writer.writerow({field: row[field] for field in fields})
    return output.getvalue()


def group_review_template_csv(rows: list[dict[str, Any]]) -> str:
    """Build a reviewer-friendly worksheet with translations side by side."""

    fields = [
        "scenario_group_id",
        "primary_offense_code",
        "offense_title",
        "offense_type",
        "split",
        "difficulty",
        "source_type",
        "location_context",
        "handbook_section",
        "handbook_item",
        "handbook_page",
        "model_eligible",
        "current_review_status",
        "english_text",
        "tagalog_text",
        "taglish_text",
        "reviewer_decision",
        "corrected_offense_code",
        "label_confidence_1_to_5",
        "university_realism_1_to_5",
        "language_notes",
        "reviewer_comments",
        "reviewer_name",
        "review_date",
    ]
    grouped: dict[str, dict[str, Any]] = {}
    for row in rows:
        group_id = row["scenario_group_id"]
        if group_id not in grouped:
            grouped[group_id] = {
                "scenario_group_id": group_id,
                "primary_offense_code": row["primary_offense_code"],
                "offense_title": row["offense_title"],
                "offense_type": row["offense_type"],
                "split": row["split"],
                "difficulty": row["difficulty"],
                "source_type": row["source_type"],
                "location_context": row["location_context"],
                "handbook_section": row["handbook_section"],
                "handbook_item": row["handbook_item"],
                "handbook_page": row["handbook_page"],
                "model_eligible": row["model_eligible"],
                "current_review_status": row["review_status"],
                "english_text": "",
                "tagalog_text": "",
                "taglish_text": "",
                "reviewer_decision": "",
                "corrected_offense_code": "",
                "label_confidence_1_to_5": "",
                "university_realism_1_to_5": "",
                "language_notes": "",
                "reviewer_comments": "",
                "reviewer_name": "",
                "review_date": "",
            }

        text_field = {
            "en": "english_text",
            "tl": "tagalog_text",
            "taglish": "taglish_text",
        }[row["language"]]
        grouped[group_id][text_field] = row["text"]

    output = io.StringIO(newline="")
    writer = csv.DictWriter(output, fieldnames=fields, lineterminator="\n")
    writer.writeheader()
    for group_id in sorted(grouped):
        writer.writerow(grouped[group_id])
    return output.getvalue()


def simple_training_csv(rows: list[dict[str, Any]]) -> str:
    """Export a simple three-column university offense dataset."""

    output = io.StringIO(newline="")
    writer = csv.DictWriter(
        output,
        fieldnames=[
            "incident_description",
            "offense_type",
            "offense_code",
        ],
        lineterminator="\n",
    )
    writer.writeheader()
    for row in rows:
        writer.writerow(
            {
                "incident_description": row["text"],
                "offense_type": row["offense_type"],
                "offense_code": row["primary_offense_code"],
            }
        )
    return output.getvalue()


def build_outputs(rows: list[dict[str, Any]]) -> dict[Path, str]:
    all_rows_jsonl = jsonl(rows)
    dataset_revision = hashlib.sha256(
        all_rows_jsonl.encode("utf-8")
    ).hexdigest()[:12].upper()
    review_filename = f"review_{dataset_revision}.csv"
    group_review_filename = f"group_review_template_{dataset_revision}.csv"
    simple_training_filename = f"simple_training_v2_{dataset_revision}.csv"
    labels = sorted(
        {
            row["primary_offense_code"]: row["offense_title"]
            for row in rows
            if row["model_eligible"]
        }.items(),
        key=lambda item: rows_by_code_order(item[0]),
    )
    label_map = {
        "id_to_label": {str(index): code for index, (code, _) in enumerate(labels)},
        "label_to_id": {code: index for index, (code, _) in enumerate(labels)},
        "label_titles": {code: title for code, title in labels},
        "excluded_labels": {
            "MAJOR-22": (
                "Violation of Student Athlete Dormitory Rules and Regulations "
                "is not applicable to NU Clark."
            )
        },
        "rule_derived_labels": {
            "MAJOR-19": (
                "Habitual disregard is derived from confirmed minor-offense "
                "history and is excluded from language-model training."
            )
        },
        "operational_definition_labels": {
            "MAJOR-30": (
                "Deliberate, coordinated action intended to violently overthrow "
                "or replace lawful authority, incite serious illegal action "
                "against the school or state, or sabotage critical school "
                "operations to threaten institutional safety, security, or lawful "
                "governance. Peaceful criticism, lawful organizations and "
                "demonstrations, and good-faith complaints are excluded."
            )
        },
    }

    model_rows = [row for row in rows if row["model_eligible"]]
    stats = {
        "dataset_revision": dataset_revision,
        "total_examples": len(rows),
        "model_eligible_examples": len(model_rows),
        "total_scenario_groups": len({row["scenario_group_id"] for row in rows}),
        "scenario_labels_including_no_match": len(
            {row["primary_offense_code"] for row in rows}
        ),
        "model_labels_including_no_match": len(labels),
        "applicable_handbook_offense_labels": len(EXPECTED_OFFENSE_CODES),
        "all_examples_by_split": dict(
            sorted(Counter(row["split"] for row in rows).items())
        ),
        "model_examples_by_split": dict(
            sorted(Counter(row["split"] for row in model_rows).items())
        ),
        "by_language": dict(
            sorted(Counter(row["language"] for row in rows).items())
        ),
        "by_offense_type": dict(
            sorted(Counter(row["offense_type"] for row in rows).items())
        ),
        "by_difficulty": dict(
            sorted(Counter(row["difficulty"] for row in rows).items())
        ),
        "examples_per_label": dict(
            sorted(
                Counter(row["primary_offense_code"] for row in rows).items(),
                key=lambda item: rows_by_code_order(item[0]),
            )
        ),
    }

    outputs = {
        PROCESSED_DIR / "all.jsonl": all_rows_jsonl,
        PROCESSED_DIR / review_filename: review_csv(rows),
        PROCESSED_DIR / group_review_filename: (
            group_review_template_csv(rows)
        ),
        PROCESSED_DIR / simple_training_filename: simple_training_csv(
            [row for row in rows if row["model_eligible"]]
        ),
        PROCESSED_DIR / "review_manifest.json": (
            json.dumps(
                {
                    "dataset_revision": dataset_revision,
                    "full_review_csv": review_filename,
                    "group_review_template_csv": group_review_filename,
                    "simple_training_csv": simple_training_filename,
                },
                ensure_ascii=False,
                indent=2,
            )
            + "\n"
        ),
        PROCESSED_DIR / "label_map.json": (
            json.dumps(label_map, ensure_ascii=False, indent=2) + "\n"
        ),
        PROCESSED_DIR / "stats.json": (
            json.dumps(stats, ensure_ascii=False, indent=2) + "\n"
        ),
    }
    for split in ("train", "validation", "test"):
        outputs[PROCESSED_DIR / f"{split}.jsonl"] = jsonl(
            [row for row in model_rows if row["split"] == split]
        )

    return outputs


def rows_by_code_order(code: str) -> tuple[int, str]:
    return (
        0 if code == "NO_MATCH" else 1 if code.startswith("MINOR-") else 2,
        code,
    )


def write_outputs(outputs: dict[Path, str]) -> None:
    PROCESSED_DIR.mkdir(parents=True, exist_ok=True)
    for path, content in outputs.items():
        if path.exists() and path.read_text(encoding="utf-8") == content:
            continue
        path.write_text(content, encoding="utf-8", newline="\n")


def check_outputs(outputs: dict[Path, str]) -> None:
    mismatches: list[str] = []
    for path, expected in outputs.items():
        if not path.exists():
            mismatches.append(f"missing {path.relative_to(ML_ROOT)}")
            continue
        actual = path.read_text(encoding="utf-8")
        if actual != expected:
            mismatches.append(f"stale {path.relative_to(ML_ROOT)}")

    if mismatches:
        raise DatasetError(
            "Generated dataset is not current: "
            + ", ".join(mismatches)
            + ". Run `python ml/scripts/build_dataset.py`."
        )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--check",
        action="store_true",
        help="Validate sources and confirm generated files are current.",
    )
    parser.add_argument(
        "--validate-only",
        action="store_true",
        help="Validate source shards without writing generated files.",
    )
    arguments = parser.parse_args()

    if arguments.check and arguments.validate_only:
        parser.error("--check and --validate-only are mutually exclusive")

    try:
        rows = validate_and_expand(load_source_records())
        outputs = build_outputs(rows)
        if arguments.check:
            check_outputs(outputs)
        elif not arguments.validate_only:
            write_outputs(outputs)
    except DatasetError as error:
        print(f"Dataset validation failed: {error}", file=sys.stderr)
        return 1

    model_rows = [row for row in rows if row["model_eligible"]]
    split_counts = Counter(row["split"] for row in model_rows)
    print(
        "Dataset valid: "
        f"{len(rows)} examples, "
        f"{len(model_rows)} model-eligible, "
        f"{len({row['scenario_group_id'] for row in rows})} groups, "
        f"{len({row['primary_offense_code'] for row in rows})} labels; "
        f"train={split_counts['train']}, "
        f"validation={split_counts['validation']}, "
        f"test={split_counts['test']}."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
