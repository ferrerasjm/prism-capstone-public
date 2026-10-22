"""Unit tests (pytest) for HybridOffensePredictor, the Tagalog RoBERTa
offense recommendation component's ensemble inference logic.

torch and transformers are multi-gigabyte dependencies that need not be
installed to test this module's own logic: weighted-ensemble combination,
label alignment, exclusion handling, the policy-guardrail promotion and
suppression, and input validation. Minimal fakes are registered in
sys.modules only when the real frameworks are absent, because sys.modules
is process-wide and shadowing a real torch here would break the real-model
integration tests that run later in the same pytest session. Every test
additionally monkeypatches hybrid_inference's own torch reference, so the
deterministic fakes apply whether or not the real frameworks are present.
"""

from __future__ import annotations

import importlib.util
import json
import sys
import types
from pathlib import Path

import numpy as np
import pytest

SCRIPTS_DIR = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS_DIR))


def _softmax_np(values: np.ndarray) -> np.ndarray:
    shifted = values - np.max(values, axis=-1, keepdims=True)
    exponentiated = np.exp(shifted)
    return exponentiated / np.sum(exponentiated, axis=-1, keepdims=True)


class _FakeTensor:
    """Stands in for a torch.Tensor: only the .cpu().numpy() chain is used."""

    def __init__(self, array: np.ndarray) -> None:
        self._array = array

    def cpu(self) -> "_FakeTensor":
        return self

    def numpy(self) -> np.ndarray:
        return self._array


class _FakeContextManager:
    def __enter__(self):
        return None

    def __exit__(self, *exc_info):
        return False


def _build_fake_torch() -> types.ModuleType:
    fake_torch = types.ModuleType("torch")
    fake_torch.inference_mode = lambda: _FakeContextManager()
    fake_torch.softmax = lambda tensor, dim=-1: _FakeTensor(
        _softmax_np(tensor._array if isinstance(tensor, _FakeTensor) else np.asarray(tensor))
    )
    # scipy (imported transitively by other test modules via sklearn) probes
    # `torch.Tensor` when torch is present in sys.modules at all, so the stub
    # needs a harmless placeholder to avoid breaking unrelated test files
    # that run in the same pytest session.
    fake_torch.Tensor = type("Tensor", (), {})

    return fake_torch


FAKE_TORCH = _build_fake_torch()


def _is_really_installed(module_name: str) -> bool:
    try:
        return importlib.util.find_spec(module_name) is not None
    except (ImportError, ValueError):
        return False


def _install_fake_ml_dependencies() -> None:
    """Registers torch/transformers stand-ins only when the real ones are absent.

    sys.modules is process-wide. Registering a stub unconditionally would leave
    the real-model integration tests importing this file's fakes, so a stub is
    installed only when the framework genuinely cannot be imported.
    """
    if not _is_really_installed("torch"):
        sys.modules.setdefault("torch", FAKE_TORCH)

    if _is_really_installed("transformers"):
        return

    fake_transformers = types.ModuleType("transformers")

    class _UnconfiguredAutoClass:
        @staticmethod
        def from_pretrained(*args, **kwargs):
            raise AssertionError("test must monkeypatch from_pretrained before use")

    fake_transformers.AutoTokenizer = _UnconfiguredAutoClass
    fake_transformers.AutoModelForSequenceClassification = _UnconfiguredAutoClass
    sys.modules.setdefault("transformers", fake_transformers)


_install_fake_ml_dependencies()

import hybrid_inference  # noqa: E402
from hybrid_inference import HybridInferenceError, HybridOffensePredictor  # noqa: E402


LABELS = ["MAJOR-01", "MAJOR-02", "MINOR-04", "NO_MATCH"]
LABEL_TO_ID = {label: index for index, label in enumerate(LABELS)}


class _FakeTokenizer:
    def __call__(self, text, **kwargs):
        return {"input_ids": [[0]]}


class _FakeRobertaModel:
    def __init__(self, logits: np.ndarray, label_to_id: dict[str, int]) -> None:
        self._logits = logits
        self.config = types.SimpleNamespace(label2id=dict(label_to_id))

    def eval(self) -> None:
        return None

    def __call__(self, **kwargs):
        return types.SimpleNamespace(logits=_FakeTensor(self._logits))


class _FakeProbabilityPipeline:
    """Stands in for the scikit-learn TF-IDF + logistic-regression pipeline."""

    def __init__(self, probabilities: np.ndarray, classes: list[str]) -> None:
        self._probabilities = probabilities
        self.classes_ = classes

    def predict_proba(self, texts):
        return np.array([self._probabilities])


class _FakeSvmPipeline:
    def __init__(self, decision: np.ndarray, classes: list[str]) -> None:
        self._decision = decision
        self.classes_ = classes

    def decision_function(self, texts):
        return np.array([self._decision])


def _build_predictor(
    tmp_path: Path,
    monkeypatch: pytest.MonkeyPatch,
    *,
    roberta_logits: np.ndarray,
    tfidf_probabilities: np.ndarray,
    svm_decision: np.ndarray,
    weights: dict[str, float] | None = None,
    roberta_label_to_id: dict[str, int] | None = None,
    tfidf_label_to_id: dict[str, int] | None = None,
) -> HybridOffensePredictor:
    weights = weights or {"roberta": 1.0, "tfidf_logreg": 0.0, "linear_svm": 0.0}
    roberta_label_to_id = roberta_label_to_id or LABEL_TO_ID
    tfidf_label_to_id = tfidf_label_to_id or LABEL_TO_ID

    roberta_dir = tmp_path / "roberta_model"
    roberta_dir.mkdir()
    tfidf_path = tmp_path / "tfidf.joblib"
    tfidf_path.write_bytes(b"stub")
    hybrid_path = tmp_path / "hybrid.joblib"
    hybrid_path.write_bytes(b"stub")
    manifest_path = tmp_path / "model_manifest.json"
    manifest_path.write_text(json.dumps({"name": "prism-hybrid-test"}), encoding="utf-8")

    def fake_joblib_load(path):
        resolved = Path(path)
        if resolved == hybrid_path:
            return {
                "label_to_id": LABEL_TO_ID,
                "hyperparameters": {"weights": weights, "svm_temperature": 1.0},
                "svm_pipeline": _FakeSvmPipeline(svm_decision, LABELS),
                "dataset_fingerprint": "deadbeefcafe0000",
            }
        if resolved == tfidf_path:
            return {
                "label_to_id": tfidf_label_to_id,
                "pipeline": _FakeProbabilityPipeline(tfidf_probabilities, LABELS),
            }
        raise AssertionError(f"unexpected joblib.load path: {resolved}")

    # Applied per test rather than through sys.modules, so a real torch stays
    # available to every other module in the session.
    monkeypatch.setattr(hybrid_inference, "torch", FAKE_TORCH)
    monkeypatch.setattr(hybrid_inference.joblib, "load", fake_joblib_load)

    class _StubAutoTokenizer:
        @staticmethod
        def from_pretrained(*args, **kwargs):
            return _FakeTokenizer()

    class _StubAutoModel:
        @staticmethod
        def from_pretrained(*args, **kwargs):
            # The real model returns a batched tensor of shape (1, num_labels);
            # predict() squeezes the batch dimension with numpy()[0].
            return _FakeRobertaModel(np.atleast_2d(roberta_logits), roberta_label_to_id)

    monkeypatch.setattr(hybrid_inference, "AutoTokenizer", _StubAutoTokenizer)
    monkeypatch.setattr(hybrid_inference, "AutoModelForSequenceClassification", _StubAutoModel)

    return HybridOffensePredictor(
        roberta_model_dir=roberta_dir,
        tfidf_model_path=tfidf_path,
        hybrid_model_path=hybrid_path,
        manifest_path=manifest_path,
    )


def test_predict_combines_all_three_models_by_their_configured_weights(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([5.0, 0.0, 0.0, 0.0]),  # strongly favors MAJOR-01 alone
        tfidf_probabilities=np.array([0.0, 1.0, 0.0, 0.0]),  # fully favors MAJOR-02
        svm_decision=np.array([0.0, 5.0, 0.0, 0.0]),  # also favors MAJOR-02
        weights={"roberta": 0.2, "tfidf_logreg": 0.4, "linear_svm": 0.4},
    )

    result = predictor.predict("Unrelated narrative text.", top_k=4)

    assert result["predictions"][0]["label"] == "MAJOR-02"
    assert result["guardrail"]["applied"] is False


def test_predict_applies_policy_guardrail_and_reorders_the_top_prediction(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([5.0, 0.0, 0.0, 0.0]),  # model originally favors MAJOR-01
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
        weights={"roberta": 1.0, "tfidf_logreg": 0.0, "linear_svm": 0.0},
    )

    result = predictor.predict(
        "The student was seen wearing a shirt with an inappropriate graphic.",
        top_k=4,
    )

    assert result["predictions"][0]["label"] == "MINOR-04"
    assert result["guardrail"]["applied"] is True
    assert result["guardrail"]["reason"] == "inappropriate_message_or_graphic_on_clothing"
    assert result["guardrail"]["original_label"] == "MAJOR-01"


def test_a_promotion_reorders_without_rewriting_any_score(tmp_path, monkeypatch):
    """A promoted label keeps its own probability and the rest keep theirs.

    The rule speaks to rank, not to likelihood. Rewriting scores would both
    overstate the promoted label and demote the displaced one to a score it
    never had, which matters because the displaced label is often a genuinely
    co-applicable offense the officer still needs to see.
    """
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([5.0, 0.0, 0.0, 0.0]),  # model originally favors MAJOR-01
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
        weights={"roberta": 1.0, "tfidf_logreg": 0.0, "linear_svm": 0.0},
    )
    baseline = predictor.predict("Unrelated narrative text.", top_k=4)
    baseline_scores = {row["label"]: row["score"] for row in baseline["predictions"]}
    assert baseline["guardrail"]["applied"] is False

    result = predictor.predict(
        "The student was seen wearing a shirt with an inappropriate graphic.",
        top_k=4,
    )
    promoted_scores = {row["label"]: row["score"] for row in result["predictions"]}

    assert result["predictions"][0]["label"] == "MINOR-04"
    assert promoted_scores == baseline_scores

    # The displaced label is not pushed below the labels it genuinely outranks;
    # only MINOR-04 moves.
    order = [row["label"] for row in result["predictions"]]
    assert order[1] == "MAJOR-01"
    assert order == ["MINOR-04"] + [
        label
        for label in (row["label"] for row in baseline["predictions"])
        if label != "MINOR-04"
    ]


def test_predict_excludes_requested_labels_from_the_ranking(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([1.0, 5.0, 1.0, 1.0]),  # favors MAJOR-02
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    result = predictor.predict("Unrelated narrative text.", top_k=4, excluded_labels=["MAJOR-02"])

    labels = [prediction["label"] for prediction in result["predictions"]]
    assert "MAJOR-02" not in labels
    assert len(labels) == 3


def test_predict_raises_for_an_unknown_excluded_label(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    with pytest.raises(ValueError, match="Unknown excluded label"):
        predictor.predict("Unrelated narrative text.", excluded_labels=["NOT-A-REAL-CODE"])


@pytest.mark.parametrize("blank_text", ["", "   ", "\n\t"])
def test_predict_rejects_blank_text(tmp_path, monkeypatch, blank_text):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    with pytest.raises(ValueError, match="text must not be empty"):
        predictor.predict(blank_text)


def test_predict_rejects_text_over_the_length_limit(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    with pytest.raises(ValueError, match="10,000 characters"):
        predictor.predict("x" * 10_001)


@pytest.mark.parametrize("invalid_top_k", [0, -1, 11])
def test_predict_rejects_top_k_outside_the_supported_range(tmp_path, monkeypatch, invalid_top_k):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    with pytest.raises(ValueError, match="top_k must be between"):
        predictor.predict("Unrelated narrative text.", top_k=invalid_top_k)


def test_predict_flags_no_match_when_it_is_the_top_ranked_label(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([0.0, 0.0, 0.0, 5.0]),  # strongly favors NO_MATCH
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    result = predictor.predict("Unrelated narrative text.")

    assert result["predictions"][0]["label"] == "NO_MATCH"
    assert result["no_match"] is True


def test_model_metadata_reports_name_fingerprint_and_guardrail_version(tmp_path, monkeypatch):
    predictor = _build_predictor(
        tmp_path,
        monkeypatch,
        roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
        tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
        svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
    )

    metadata = predictor.model_metadata

    assert metadata["name"] == "prism-hybrid-test"
    assert metadata["dataset_fingerprint"] == "deadbeefcafe0000"
    assert metadata["version"].startswith("deadbeefcafe")
    assert metadata["version"].endswith(hybrid_inference.GUARDRAIL_VERSION)


def test_init_raises_when_a_frozen_artifact_file_is_missing(tmp_path, monkeypatch):
    roberta_dir = tmp_path / "roberta_model"
    roberta_dir.mkdir()
    tfidf_path = tmp_path / "tfidf.joblib"
    tfidf_path.write_bytes(b"stub")
    hybrid_path = tmp_path / "hybrid.joblib"
    hybrid_path.write_bytes(b"stub")
    missing_manifest_path = tmp_path / "does_not_exist.json"

    with pytest.raises(HybridInferenceError, match="Missing frozen artifact"):
        HybridOffensePredictor(
            roberta_model_dir=roberta_dir,
            tfidf_model_path=tfidf_path,
            hybrid_model_path=hybrid_path,
            manifest_path=missing_manifest_path,
        )


def test_init_raises_when_tfidf_and_hybrid_label_maps_disagree(tmp_path, monkeypatch):
    mismatched_label_to_id = {"MAJOR-01": 0, "MAJOR-02": 1, "MINOR-04": 2, "SOMETHING-ELSE": 3}

    with pytest.raises(HybridInferenceError, match="label maps do not match"):
        _build_predictor(
            tmp_path,
            monkeypatch,
            roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
            tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
            svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
            tfidf_label_to_id=mismatched_label_to_id,
        )


def test_init_raises_when_roberta_and_hybrid_label_maps_disagree(tmp_path, monkeypatch):
    mismatched_label_to_id = {"MAJOR-01": 0, "MAJOR-02": 1, "MINOR-04": 2, "SOMETHING-ELSE": 3}

    with pytest.raises(HybridInferenceError, match="RoBERTa and hybrid label maps do not match"):
        _build_predictor(
            tmp_path,
            monkeypatch,
            roberta_logits=np.array([1.0, 1.0, 1.0, 1.0]),
            tfidf_probabilities=np.array([0.25, 0.25, 0.25, 0.25]),
            svm_decision=np.array([0.0, 0.0, 0.0, 0.0]),
            roberta_label_to_id=mismatched_label_to_id,
        )
