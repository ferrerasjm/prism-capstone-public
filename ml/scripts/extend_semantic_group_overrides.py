#!/usr/bin/env python3
"""Extend conservative semantic-group links after reviewed data augmentation."""

from __future__ import annotations

import argparse
import csv
from itertools import combinations
from pathlib import Path

import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.preprocessing import normalize


def normalized_text(value: str) -> str:
    return " ".join(value.casefold().split())


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input-csv", type=Path, required=True)
    parser.add_argument("--existing-overrides", type=Path, required=True)
    parser.add_argument("--existing-embeddings", type=Path, required=True)
    parser.add_argument("--model-dir", type=Path, required=True)
    parser.add_argument("--output-overrides", type=Path, required=True)
    parser.add_argument("--output-embeddings", type=Path, required=True)
    parser.add_argument("--human-review-csv", type=Path)
    parser.add_argument("--lexical-threshold", type=float, default=0.60)
    parser.add_argument("--semantic-threshold", type=float, default=0.97)
    parser.add_argument("--batch-size", type=int, default=16)
    parser.add_argument("--max-length", type=int, default=192)
    return parser.parse_args()


def read_training(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames != ["description", "label", "language"]:
            raise ValueError("Training CSV must contain description,label,language")
        rows = list(reader)
    keys = [normalized_text(row["description"]) for row in rows]
    if len(keys) != len(set(keys)):
        raise ValueError("Training CSV contains duplicate normalized descriptions")
    return rows


def encode_new_texts(
    texts: list[str], model_dir: Path, batch_size: int, max_length: int
) -> np.ndarray:
    if not texts:
        return np.empty((0, 768), dtype=np.float32)

    import torch
    from transformers import AutoModelForSequenceClassification, AutoTokenizer

    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    tokenizer = AutoTokenizer.from_pretrained(model_dir, local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(
        model_dir, local_files_only=True
    ).to(device)
    model.eval()
    batches: list[np.ndarray] = []
    for start in range(0, len(texts), batch_size):
        inputs = tokenizer(
            texts[start : start + batch_size],
            padding=True,
            truncation=True,
            max_length=max_length,
            return_tensors="pt",
        ).to(device)
        with torch.no_grad():
            hidden = model(**inputs, output_hidden_states=True).hidden_states[-1]
            mask = inputs["attention_mask"].unsqueeze(-1)
            pooled = (hidden * mask).sum(dim=1) / mask.sum(dim=1)
            pooled = torch.nn.functional.normalize(pooled, p=2, dim=1)
        batches.append(pooled.cpu().numpy().astype(np.float32))
    return np.concatenate(batches, axis=0)


def main() -> None:
    args = parse_args()
    rows = read_training(args.input_csv)
    texts = [row["description"] for row in rows]
    labels = [row["label"] for row in rows]
    normalized = [normalized_text(text) for text in texts]

    old = np.load(args.existing_embeddings, allow_pickle=True)
    old_by_text = {
        normalized_text(str(text)): embedding
        for text, embedding in zip(old["texts"], old["embeddings"], strict=True)
    }
    unseen_indices = [i for i, key in enumerate(normalized) if key not in old_by_text]
    unseen_embeddings = encode_new_texts(
        [texts[i] for i in unseen_indices],
        args.model_dir,
        args.batch_size,
        args.max_length,
    )
    unseen_by_index = dict(zip(unseen_indices, unseen_embeddings, strict=True))
    embeddings = np.stack(
        [unseen_by_index.get(i, old_by_text.get(key)) for i, key in enumerate(normalized)]
    ).astype(np.float32)
    embeddings = normalize(embeddings, norm="l2", axis=1).astype(np.float32)
    args.output_embeddings.parent.mkdir(parents=True, exist_ok=True)
    np.savez_compressed(
        args.output_embeddings,
        embeddings=embeddings,
        labels=np.asarray(labels),
        texts=np.asarray(texts),
    )

    index_by_key = {key: i for i, key in enumerate(normalized)}
    existing: dict[frozenset[str], dict[str, str]] = {}
    with args.existing_overrides.open("r", encoding="utf-8-sig", newline="") as handle:
        for row in csv.DictReader(handle):
            left = normalized_text(row["validation_text"])
            right = normalized_text(row["training_text"])
            if left in index_by_key and right in index_by_key:
                existing[frozenset((left, right))] = row

    blocked: set[frozenset[str]] = set()
    if args.human_review_csv and args.human_review_csv.is_file():
        with args.human_review_csv.open(
            "r", encoding="utf-8-sig", newline=""
        ) as handle:
            for row in csv.DictReader(handle):
                if row.get("review_action") == "DIFFERENT_GROUP":
                    blocked.add(
                        frozenset(
                            (
                                normalized_text(row["validation_text"]),
                                normalized_text(row["training_text"]),
                            )
                        )
                    )

    vectorizer = TfidfVectorizer(
        analyzer="char_wb",
        ngram_range=(3, 5),
        lowercase=True,
        strip_accents="unicode",
        sublinear_tf=True,
    )
    lexical = vectorizer.fit_transform(texts)
    new_index_set = set(unseen_indices)
    output = list(existing.values())
    added = 0
    for label in sorted(set(labels)):
        indices = [i for i, value in enumerate(labels) if value == label]
        for left_index, right_index in combinations(indices, 2):
            if left_index not in new_index_set and right_index not in new_index_set:
                continue
            key = frozenset((normalized[left_index], normalized[right_index]))
            if key in existing or key in blocked:
                continue
            lexical_similarity = float(
                lexical[left_index].multiply(lexical[right_index]).sum()
            )
            semantic_similarity = float(
                embeddings[left_index] @ embeddings[right_index]
            )
            if (
                lexical_similarity < args.lexical_threshold
                and semantic_similarity < args.semantic_threshold
            ):
                continue
            output.append(
                {
                    "label": label,
                    "validation_text": texts[left_index],
                    "training_text": texts[right_index],
                    "review_action": "SAME_GROUP",
                    "basis": "conservative_global_similarity",
                    "lexical_similarity": f"{lexical_similarity:.4f}",
                    "semantic_similarity": f"{semantic_similarity:.4f}",
                }
            )
            existing[key] = output[-1]
            added += 1

    fieldnames = [
        "label",
        "validation_text",
        "training_text",
        "review_action",
        "basis",
        "lexical_similarity",
        "semantic_similarity",
    ]
    output.sort(
        key=lambda row: (
            row["label"],
            normalized_text(row["validation_text"]),
            normalized_text(row["training_text"]),
        )
    )
    args.output_overrides.parent.mkdir(parents=True, exist_ok=True)
    with args.output_overrides.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(output)

    print(f"Rows: {len(rows)}")
    print(f"Reused embeddings: {len(rows) - len(unseen_indices)}")
    print(f"New embeddings: {len(unseen_indices)}")
    print(f"Preserved grouping links: {len(output) - added}")
    print(f"New grouping links: {added}")
    print(f"Total grouping links: {len(output)}")


if __name__ == "__main__":
    main()
