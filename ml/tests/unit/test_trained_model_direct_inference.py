"""Module 3 - Offense Classification and Escalation Support.

Component: Trained RoBERTa Model Direct Inference
           (ml/scripts/hybrid_inference.py :: HybridOffensePredictor)
Test Cycle No. 1 - 5 test cases.

Unlike the Advisory Model Recommendation Client (which fakes the HTTP
transport on the Laravel side and never touches the model) and the Handbook
Policy Guardrails component (which is pure post-classification regex logic),
this component loads the real frozen RoBERTa, TF-IDF and linear-SVM
artifacts from ml/artifacts/ and performs genuine inference through
HybridOffensePredictor.predict(), with nothing mocked.

The whole module is skipped cleanly, rather than failing, when torch,
transformers, or the frozen artifacts are unavailable in the environment
running the suite - the same convention already used by the project's
pre-existing ml/tests/test_hybrid_inference_real_model_integration.py.
"""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

SCRIPTS_DIR = Path(__file__).resolve().parents[2] / "scripts"
if str(SCRIPTS_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPTS_DIR))

torch = pytest.importorskip("torch", reason="torch is not installed in this environment")
pytest.importorskip("transformers", reason="transformers is not installed in this environment")

import hybrid_inference  # noqa: E402

_ARTIFACTS_PRESENT = all(
    artifact.exists()
    for artifact in (
        hybrid_inference.DEFAULT_ROBERTA_MODEL_DIR,
        hybrid_inference.DEFAULT_TFIDF_MODEL,
        hybrid_inference.DEFAULT_HYBRID_MODEL,
        hybrid_inference.DEFAULT_HYBRID_MANIFEST,
    )
)

pytestmark = pytest.mark.skipif(
    not _ARTIFACTS_PRESENT,
    reason="Frozen model artifacts are not present under ml/artifacts/",
)


@pytest.fixture(scope="module")
def real_predictor() -> hybrid_inference.HybridOffensePredictor:
    # Loading the RoBERTa weights is expensive, so this is built once and
    # reused across every test in this module.
    return hybrid_inference.HybridOffensePredictor()


def test_the_real_frozen_artifacts_load_and_report_consistent_metadata(real_predictor) -> None:
    """Scenario 1 - Normal: the trained checkpoint loads and self-reports its identity."""
    metadata = real_predictor.model_metadata

    assert metadata["name"] == "roberta-tfidf-svm-policy-hybrid"
    assert isinstance(metadata["dataset_fingerprint"], str) and metadata["dataset_fingerprint"] != ""
    assert metadata["guardrail_version"] == hybrid_inference.GUARDRAIL_VERSION
    assert metadata["version"].endswith(hybrid_inference.GUARDRAIL_VERSION)


def test_real_inference_on_an_explicit_narrative_returns_a_valid_ranked_distribution(
    real_predictor,
) -> None:
    """Scenario 2 - Normal: genuine inference produces a properly bounded, sorted ranking."""
    result = real_predictor.predict(
        "Nahuling nag vape ang estudyante sa loob ng campus.",
        top_k=5,
    )

    predictions = result["predictions"]
    assert 1 <= len(predictions) <= 5
    for expected_rank, prediction in enumerate(predictions, start=1):
        assert prediction["rank"] == expected_rank
        assert 0.0 <= prediction["score"] <= 1.0
    scores = [prediction["score"] for prediction in predictions]
    assert scores == sorted(scores, reverse=True)
    assert result["advisory"] is True
    assert result["decision_authority"] == "Student Discipline Office"


def test_the_maximum_permitted_top_k_returns_up_to_ten_ranked_predictions(real_predictor) -> None:
    """Scenario 3 - Boundary: top_k at its upper bound of 10 is honoured against the real label set."""
    result = real_predictor.predict(
        "The student returned the borrowed umbrella to the librarian before leaving.",
        top_k=10,
    )

    predictions = result["predictions"]
    assert 1 <= len(predictions) <= 10
    labels = [prediction["label"] for prediction in predictions]
    assert len(labels) == len(set(labels))


def test_blank_input_is_rejected_before_any_real_inference_runs(real_predictor) -> None:
    """Scenario 4 - Invalid input: whitespace-only text is refused even with real weights loaded."""
    with pytest.raises(ValueError, match="text must not be empty"):
        real_predictor.predict("   ", top_k=3)


def test_an_out_of_range_top_k_is_rejected_against_the_real_model(real_predictor) -> None:
    """Scenario 5 - Boundary / invalid input: top_k outside 1-10 is refused by the loaded model."""
    with pytest.raises(ValueError, match="top_k must be between 1 and 10"):
        real_predictor.predict("Sinira ng estudyante ang pinto ng silid-aralan.", top_k=0)

    with pytest.raises(ValueError, match="top_k must be between 1 and 10"):
        real_predictor.predict("Sinira ng estudyante ang pinto ng silid-aralan.", top_k=11)
