#!/usr/bin/env python3
"""Reusable inference for PRISM's frozen advisory offense-ranking model."""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import torch
from transformers import AutoModelForSequenceClassification, AutoTokenizer

from policy_guardrails import GUARDRAIL_VERSION, policy_demotion, policy_guardrail


PROJECT_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_ROBERTA_MODEL_DIR = (
    PROJECT_ROOT / "ml" / "artifacts" / "roberta-prism-offense-v6" / "model"
)
DEFAULT_TFIDF_MODEL = (
    PROJECT_ROOT / "ml" / "artifacts" / "tfidf-prism-offense-v7" / "model.joblib"
)
DEFAULT_HYBRID_MODEL = (
    PROJECT_ROOT / "ml" / "artifacts" / "hybrid-prism-offense-v7" / "model.joblib"
)
DEFAULT_HYBRID_MANIFEST = DEFAULT_HYBRID_MODEL.with_name("model_manifest.json")


class HybridInferenceError(RuntimeError):
    """Raised when frozen artifacts are absent or mutually incompatible."""


def _softmax(values: np.ndarray) -> np.ndarray:
    shifted = values - np.max(values, axis=-1, keepdims=True)
    exponentiated = np.exp(shifted)
    return exponentiated / np.sum(exponentiated, axis=-1, keepdims=True)


class HybridOffensePredictor:
    """Load the frozen ensemble once and rank handbook offense codes."""

    def __init__(
        self,
        roberta_model_dir: Path = DEFAULT_ROBERTA_MODEL_DIR,
        tfidf_model_path: Path = DEFAULT_TFIDF_MODEL,
        hybrid_model_path: Path = DEFAULT_HYBRID_MODEL,
        manifest_path: Path = DEFAULT_HYBRID_MANIFEST,
        max_length: int = 256,
    ) -> None:
        for artifact in (
            roberta_model_dir,
            tfidf_model_path,
            hybrid_model_path,
            manifest_path,
        ):
            if not artifact.exists():
                raise HybridInferenceError(f"Missing frozen artifact: {artifact}")

        self._hybrid = joblib.load(hybrid_model_path)
        self._tfidf = joblib.load(tfidf_model_path)
        self._manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        self._label_to_id = {
            str(label): int(label_id)
            for label, label_id in self._hybrid["label_to_id"].items()
        }
        self._labels = [
            label
            for label, _ in sorted(self._label_to_id.items(), key=lambda item: item[1])
        ]
        if self._tfidf.get("label_to_id") != self._hybrid.get("label_to_id"):
            raise HybridInferenceError("TF-IDF and hybrid label maps do not match")

        hyperparameters = self._hybrid["hyperparameters"]
        self._weights = hyperparameters["weights"]
        self._svm_temperature = float(hyperparameters["svm_temperature"])
        self._max_length = max_length
        self._tokenizer = AutoTokenizer.from_pretrained(
            roberta_model_dir,
            local_files_only=True,
        )
        self._roberta = AutoModelForSequenceClassification.from_pretrained(
            roberta_model_dir,
            local_files_only=True,
        )
        self._roberta.eval()
        self._lock = threading.Lock()

        roberta_labels = {
            str(label): int(label_id)
            for label, label_id in self._roberta.config.label2id.items()
        }
        if roberta_labels != self._label_to_id:
            raise HybridInferenceError("RoBERTa and hybrid label maps do not match")

    @property
    def model_metadata(self) -> dict[str, Any]:
        fingerprint = str(self._hybrid["dataset_fingerprint"])
        return {
            "name": str(self._manifest["name"]),
            "version": f"{fingerprint[:12]}-{GUARDRAIL_VERSION}",
            "dataset_fingerprint": fingerprint,
            "guardrail_version": GUARDRAIL_VERSION,
        }

    def predict(
        self,
        text: str,
        top_k: int = 3,
        excluded_labels: list[str] | None = None,
    ) -> dict[str, Any]:
        value = text.strip()
        if not value:
            raise ValueError("text must not be empty")
        if len(value) > 10_000:
            raise ValueError("text must not exceed 10,000 characters")
        if not 1 <= top_k <= 10:
            raise ValueError("top_k must be between 1 and 10")

        with self._lock, torch.inference_mode():
            encoded = self._tokenizer(
                value,
                return_tensors="pt",
                truncation=True,
                max_length=self._max_length,
            )
            logits = self._roberta(**encoded).logits
            roberta_probabilities = torch.softmax(logits, dim=-1).cpu().numpy()[0]

            tfidf_pipeline = self._tfidf["pipeline"]
            tfidf_native = tfidf_pipeline.predict_proba([value])[0]
            tfidf_probabilities = self._align_probabilities(
                tfidf_native,
                [str(label) for label in tfidf_pipeline.classes_],
            )

            svm_pipeline = self._hybrid["svm_pipeline"]
            svm_decisions = np.asarray(svm_pipeline.decision_function([value]))
            svm_native = _softmax(svm_decisions / self._svm_temperature)[0]
            svm_probabilities = self._align_probabilities(
                svm_native,
                [str(label) for label in svm_pipeline.classes_],
            )

        combined = (
            float(self._weights["roberta"]) * roberta_probabilities
            + float(self._weights["tfidf_logreg"]) * tfidf_probabilities
            + float(self._weights["linear_svm"]) * svm_probabilities
        )
        original_index = int(np.argmax(combined))
        original_label = self._labels[original_index]
        guardrail_action = None
        promoted_index = None
        guarded_label, guardrail_reason = policy_guardrail(value, original_label)
        if guardrail_reason is not None and guarded_label != original_label:
            # The rule decides the rank, not the score. Promotion is applied to
            # the ordering alone so every label keeps its own probability and the
            # labels below keep their relative order. Swapping the two scores -
            # the previous approach - handed the promoted label a number it never
            # earned and pushed the displaced label down to the promoted label's
            # score, distorting a second, often co-applicable signal that this
            # rule was never meant to touch.
            promoted_index = self._label_to_id[guarded_label]
            guardrail_action = "promoted"
        else:
            # A promotion can only ever raise a label, so a top-ranked offense
            # the handbook does not support is corrected here instead. The score
            # is zeroed rather than swapped: the runner-up rises on its own
            # merit, and the suppressed label stays in the distribution for the
            # officer rather than being hidden outright.
            demotion_reason = policy_demotion(value, original_label)
            if demotion_reason is not None:
                combined[original_index] = 0.0
                guardrail_reason = demotion_reason
                guardrail_action = "suppressed"

        excluded = set(excluded_labels or [])
        unknown_exclusions = excluded.difference(self._label_to_id)
        if unknown_exclusions:
            raise ValueError(
                "Unknown excluded label(s): " + ", ".join(sorted(unknown_exclusions))
            )

        order = np.argsort(-combined, kind="stable")
        if promoted_index is not None:
            order = np.concatenate(([promoted_index], order[order != promoted_index]))
        ranked = [
            {
                "rank": rank,
                "label": self._labels[int(index)],
                "score": round(float(combined[int(index)]), 8),
            }
            for rank, index in enumerate(
                (
                    index
                    for index in order
                    if self._labels[int(index)] not in excluded
                ),
                start=1,
            )
        ][:top_k]

        return {
            "advisory": True,
            "decision_authority": "Student Discipline Office",
            "model": self.model_metadata,
            "no_match": bool(ranked and ranked[0]["label"] == "NO_MATCH"),
            "guardrail": {
                "applied": guardrail_reason is not None,
                "action": guardrail_action,
                "reason": guardrail_reason,
                "original_label": original_label if guardrail_reason else None,
            },
            "predictions": ranked,
        }

    def _align_probabilities(
        self,
        native_probabilities: np.ndarray,
        native_labels: list[str],
    ) -> np.ndarray:
        aligned = np.zeros(len(self._labels), dtype=np.float64)
        if len(native_probabilities) != len(native_labels):
            raise HybridInferenceError("Classifier output does not match its label map")
        for index, label in enumerate(native_labels):
            if label not in self._label_to_id:
                raise HybridInferenceError(f"Unknown model label: {label}")
            aligned[self._label_to_id[label]] = float(native_probabilities[index])
        return aligned
