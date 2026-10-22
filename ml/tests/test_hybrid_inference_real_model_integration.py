"""Real-model integration test for the Tagalog RoBERTa offense
recommendation component (Offense Classification and Escalation Support
Module).

Unlike test_hybrid_inference.py (which mocks torch/transformers and the
model artifacts to unit-test HybridOffensePredictor's own ensemble logic
in isolation), this file loads the real frozen RoBERTa, TF-IDF, and
linear-SVM artifacts from ml/artifacts/ and runs genuine inference
through HybridOffensePredictor.predict() end to end, with no mocking.

This requires torch and transformers to be installed (see
ml/requirements-transformer.txt) and the frozen artifacts to be present
on disk. Both are heavy/optional, so the whole module is skipped
cleanly rather than failing when either is unavailable.
"""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

SCRIPTS_DIR = Path(__file__).resolve().parents[1] / "scripts"
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


def test_real_frozen_artifacts_load_and_report_metadata(real_predictor) -> None:
    metadata = real_predictor.model_metadata

    assert metadata["name"] == "roberta-tfidf-svm-policy-hybrid"
    assert isinstance(metadata["dataset_fingerprint"], str) and metadata["dataset_fingerprint"] != ""
    assert metadata["guardrail_version"] == hybrid_inference.GUARDRAIL_VERSION
    assert metadata["version"].endswith(hybrid_inference.GUARDRAIL_VERSION)


def test_real_model_ranks_an_explicit_vaping_narrative_as_major_26(real_predictor) -> None:
    result = real_predictor.predict(
        "Nahuling nag vape ang estudyante sa loob ng campus.",
        top_k=3,
    )

    assert result["predictions"][0]["label"] == "MAJOR-26"
    assert result["advisory"] is True
    assert result["decision_authority"] == "Student Discipline Office"


def test_real_model_predictions_are_valid_ranked_probabilities(real_predictor) -> None:
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


def test_real_model_respects_label_exclusions(real_predictor) -> None:
    result = real_predictor.predict(
        "Nahuling nag vape ang estudyante sa loob ng campus.",
        top_k=5,
        excluded_labels=["MAJOR-26"],
    )

    labels = [prediction["label"] for prediction in result["predictions"]]
    assert "MAJOR-26" not in labels


def test_real_model_no_match_flag_is_consistent_with_the_top_prediction(real_predictor) -> None:
    result = real_predictor.predict(
        "The student returned the borrowed umbrella to the librarian before leaving.",
        top_k=3,
    )

    top_label = result["predictions"][0]["label"]
    assert result["no_match"] == (top_label == "NO_MATCH")


def test_real_model_applies_the_clothing_graphic_guardrail(real_predictor) -> None:
    result = real_predictor.predict(
        "The student was seen wearing a shirt with an inappropriate graphic during the assembly.",
        top_k=5,
    )

    assert result["predictions"][0]["label"] == "MINOR-04"
    assert result["guardrail"]["applied"] is True
    assert result["guardrail"]["reason"] == "inappropriate_message_or_graphic_on_clothing"


# The three weapon-boundary tests below assert the OUTCOME, not the mechanism.
# Before the 2026-09-04 corpus expansion the model could not handle bare lay
# weapon words or benign weapon senses on its own, so the policy guardrail had
# to fire and these tests asserted guardrail["action"]. The v5 corpus closed
# that gap (lay_weapon_vocabulary + contrastive pairs), so the model now ranks
# these correctly natively and the guardrail stays dormant - the desired end
# state, with the guardrail as a fallback rather than the primary path. The
# tests therefore check the final label and, when the guardrail does fire, that
# it fired in the correct direction.


def test_real_model_does_not_escalate_a_weapon_word_used_in_a_non_weapon_sense(
    real_predictor,
) -> None:
    result = real_predictor.predict(
        "The student used a glue gun while preparing a project display in the classroom.",
        top_k=5,
    )

    assert result["predictions"][0]["label"] != "MAJOR-25"
    if result["guardrail"]["applied"]:
        assert result["guardrail"]["action"] in {"suppressed", "promoted"}
        assert result["guardrail"]["action"] != "promoted"


def test_real_model_does_not_escalate_a_narrative_recording_that_no_weapon_was_found(
    real_predictor,
) -> None:
    result = real_predictor.predict(
        "Security inspected the student's bag during rounds and found no weapon of any kind.",
        top_k=5,
    )

    assert result["predictions"][0]["label"] != "MAJOR-25"
    if result["guardrail"]["applied"]:
        assert result["guardrail"]["action"] != "promoted"


def test_real_model_ranks_a_bare_lay_weapon_word_the_corpus_never_contained_as_major_25(
    real_predictor,
) -> None:
    result = real_predictor.predict("the student is bringing a gun", top_k=5)

    assert result["predictions"][0]["label"] == "MAJOR-25"
    if result["guardrail"]["applied"]:
        assert result["guardrail"]["reason"] == "explicit_deadly_weapon_reference"
