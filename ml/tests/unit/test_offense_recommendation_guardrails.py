"""Module 3 - Offense Classification and Escalation Support.

Component: Tagalog RoBERTa Handbook Policy Guardrails
           (ml/scripts/policy_guardrails.py :: policy_guardrail)
Test Cycle No. 1 - 5 test cases.

The guardrail layer resolves explicit NU Clark handbook boundaries after the
hybrid Tagalog RoBERTa ensemble has produced its ranking. It is pure regex
logic with no model, network, or filesystem dependency, so it is exercised
directly against normal, boundary, and negative-boundary narratives.
"""

from __future__ import annotations

import sys
from pathlib import Path

SCRIPTS_DIR = Path(__file__).resolve().parents[2] / "scripts"
if str(SCRIPTS_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPTS_DIR))

from policy_guardrails import GUARDRAIL_VERSION, policy_guardrail  # noqa: E402


def test_an_ordinary_narrative_passes_the_model_label_through_unchanged() -> None:
    """Scenario 1 - Normal: no handbook boundary applies, so the model ranking stands."""
    label, reason = policy_guardrail(
        "The student submitted the laboratory report two days after the deadline.",
        "MAJOR-05",
    )

    assert label == "MAJOR-05"
    assert reason is None
    assert isinstance(GUARDRAIL_VERSION, str) and GUARDRAIL_VERSION != ""


def test_keyboard_rearrangement_without_damage_is_downgraded_to_no_match() -> None:
    """Scenario 2 - Negative boundary: property damage is not established."""
    label, reason = policy_guardrail(
        "The student rearranged the keycaps on the laboratory keyboard as a prank. "
        "There was no damage and the unit still works.",
        "MAJOR-15",
    )

    assert label == "NO_MATCH"
    assert reason == "keyboard_rearrangement_without_damage_evidence"


def test_unauthorized_laboratory_keyboard_rearrangement_is_redirected_to_major_45() -> None:
    """Scenario 3 - Boundary: undamaged, but an explicit laboratory rule was broken."""
    label, reason = policy_guardrail(
        "Without permission the student rearranged the keycaps on a computer "
        "laboratory workstation keyboard, contrary to the posted laboratory rule. "
        "No damage was found.",
        "MAJOR-15",
    )

    assert label == "MAJOR-45"
    assert reason == "explicit_unauthorized_lab_keyboard_rearrangement"


def test_a_concealed_assessment_aid_upgrades_no_match_to_major_01() -> None:
    """Scenario 4 - Normal: an explicit cheating boundary overrides a NO_MATCH ranking."""
    label, reason = policy_guardrail(
        "While taking the midterm examination the student used notes hidden "
        "beneath the desk; the notes were related to the test questions.",
        "NO_MATCH",
    )

    assert label == "MAJOR-01"
    assert reason == "explicit_concealed_assessment_aid"


def test_an_approved_peaceful_demonstration_is_not_treated_as_subversion() -> None:
    """Scenario 5 - Negative boundary: protected activity must not stay MAJOR-30."""
    label, reason = policy_guardrail(
        "Students held a peaceful demonstration that was approved by the "
        "university and ended on schedule.",
        "MAJOR-30",
    )

    assert label == "NO_MATCH"
    assert reason == "lawful_peaceful_demonstration"
