"""Unit tests (pytest) for the handbook boundary guardrail rules used by the
Tagalog RoBERTa offense recommendation component.

policy_guardrail() is pure regex logic with no external dependencies, so it
is exercised directly against normal, edge, and invalid-input cases.
"""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

SCRIPTS_DIR = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS_DIR))

from policy_guardrails import (  # noqa: E402
    GUARDRAIL_VERSION,
    policy_demotion,
    policy_guardrail,
)


def test_guardrail_version_is_a_non_empty_string() -> None:
    assert isinstance(GUARDRAIL_VERSION, str)
    assert GUARDRAIL_VERSION != ""


def test_unrelated_text_passes_the_predicted_label_through_unchanged() -> None:
    label, reason = policy_guardrail(
        "The student turned in the assignment late.", "MAJOR-05"
    )

    assert label == "MAJOR-05"
    assert reason is None


def test_keyboard_rearrangement_without_damage_downgrades_to_no_match() -> None:
    label, reason = policy_guardrail(
        "The student rearranged the keycaps on the lab keyboard as a prank.",
        "MAJOR-15",
    )

    assert label == "NO_MATCH"
    assert reason == "keyboard_rearrangement_without_damage_evidence"


def test_keyboard_rearrangement_with_explicit_policy_violation_maps_to_major45() -> None:
    label, reason = policy_guardrail(
        "Without permission, the student rearranged the keyboard in the computer laboratory, "
        "which is against lab policy.",
        "MAJOR-15",
    )

    assert label == "MAJOR-45"
    assert reason == "explicit_unauthorized_lab_keyboard_rearrangement"


def test_keyboard_rearrangement_with_damage_evidence_keeps_original_label() -> None:
    label, reason = policy_guardrail(
        "The student rearranged the keycaps and the keyboard is now broken and unusable.",
        "MAJOR-15",
    )

    assert label == "MAJOR-15"
    assert reason is None


def test_keyboard_guardrail_only_applies_when_major15_was_predicted() -> None:
    label, reason = policy_guardrail(
        "The student rearranged the keycaps on the lab keyboard as a prank.",
        "MAJOR-05",
    )

    assert label == "MAJOR-05"
    assert reason is None


def test_intentional_property_damage_is_recognized_regardless_of_predicted_label() -> None:
    label, reason = policy_guardrail(
        "The student intentionally damaged school property in the hallway.",
        "NO_MATCH",
    )

    assert label == "MAJOR-15"
    assert reason == "explicit_intentional_property_damage"


def test_concealed_exam_aid_during_ongoing_exam_maps_to_major01() -> None:
    label, reason = policy_guardrail(
        "While taking the exam, the student secretly viewed a hidden kodigo with answers "
        "related to the test questions.",
        "NO_MATCH",
    )

    assert label == "MAJOR-01"
    assert reason == "explicit_concealed_assessment_aid"


def test_notes_used_after_the_exam_ended_do_not_trigger_the_exam_aid_guardrail() -> None:
    label, reason = policy_guardrail(
        "After the exam ended and was collected, the student viewed a hidden kodigo related to the test.",
        "NO_MATCH",
    )

    assert label == "NO_MATCH"
    assert reason is None


def test_exam_aid_guardrail_only_applies_when_no_match_was_predicted() -> None:
    label, reason = policy_guardrail(
        "While taking the exam, the student secretly viewed a hidden kodigo with answers "
        "related to the test questions.",
        "MAJOR-05",
    )

    assert label == "MAJOR-05"
    assert reason is None


def test_lawful_peaceful_approved_demonstration_is_not_subversion() -> None:
    label, reason = policy_guardrail(
        "Students held a peaceful demonstration that was approved by the university.",
        "MAJOR-30",
    )

    assert label == "NO_MATCH"
    assert reason == "lawful_peaceful_demonstration"


def test_good_faith_petition_through_proper_channels_is_not_subversion() -> None:
    label, reason = policy_guardrail(
        "Students filed a formal complaint through the proper office about cafeteria prices.",
        "MAJOR-30",
    )

    assert label == "NO_MATCH"
    assert reason == "good_faith_petition_or_complaint"


def test_criticism_without_incitement_to_violence_is_not_subversion() -> None:
    label, reason = policy_guardrail(
        "The student publicly criticized the administration but did not encourage violence.",
        "MAJOR-30",
    )

    assert label == "NO_MATCH"
    assert reason == "protected_policy_criticism"


def test_subversion_guardrail_does_not_downgrade_unrelated_predicted_labels() -> None:
    label, reason = policy_guardrail(
        "Students held a peaceful demonstration that was approved by the university.",
        "MAJOR-05",
    )

    assert label == "MAJOR-05"
    assert reason is None


def test_inappropriate_clothing_graphic_is_recognized_unconditionally() -> None:
    label, reason = policy_guardrail(
        "The student was seen wearing a shirt with an inappropriate graphic.",
        "MAJOR-02",
    )

    assert label == "MINOR-04"
    assert reason == "inappropriate_message_or_graphic_on_clothing"


def test_empty_text_does_not_raise_and_passes_through_unchanged() -> None:
    label, reason = policy_guardrail("", "NO_MATCH")

    assert label == "NO_MATCH"
    assert reason is None


def test_matching_is_case_insensitive() -> None:
    label, reason = policy_guardrail(
        "THE STUDENT INTENTIONALLY DAMAGED SCHOOL PROPERTY.",
        "NO_MATCH",
    )

    assert label == "MAJOR-15"
    assert reason == "explicit_intentional_property_damage"


@pytest.mark.parametrize("predicted_label", ["MAJOR-01", "MAJOR-15", "MAJOR-30", "NO_MATCH", "MAJOR-99"])
def test_unmatched_narratives_never_change_the_predicted_label(predicted_label: str) -> None:
    label, reason = policy_guardrail("Nothing relevant happened.", predicted_label)

    assert label == predicted_label
    assert reason is None


@pytest.mark.parametrize(
    "narrative",
    [
        "the student is bringing a gun",
        "May dalang gun ang estudyante sa loob ng campus.",
        "Security recovered a knife from the student's bag.",
        "May dalang baril ang estudyante.",
        "The student had metal knuckles during the school program.",
        "Found the student without an ID but carrying a knife.",
    ],
)
def test_deadly_weapon_reference_escalates_a_minor_ranking(narrative: str) -> None:
    label, reason = policy_guardrail(narrative, "MINOR-02")

    assert label == "MAJOR-25"
    assert reason == "explicit_deadly_weapon_reference"


def test_deadly_weapon_reference_escalates_an_unmatched_ranking() -> None:
    label, reason = policy_guardrail(
        "The student had a bolo inside the campus.", "NO_MATCH"
    )

    assert label == "MAJOR-25"
    assert reason == "explicit_deadly_weapon_reference"


@pytest.mark.parametrize(
    "narrative",
    [
        "The student used a glue gun while preparing a project display.",
        "The student's essay argued for stricter gun control laws.",
        "The coach jumped the gun and started the race early.",
        "The student complained of pain in the shoulder blade.",
        "Security inspected the bag and found no weapon.",
        "Walang dalang baril ang estudyante ayon sa inspeksiyon.",
        "The student had begun the examination early.",
        # A benign qualifier may sit one word away from the noun, and it applies
        # to every weapon noun that has an ordinary prop or toy counterpart -
        # not only to "gun".
        "The student brought a toy water pistol for the approved intramurals game.",
        "A plastic prop knife was used during the theater rehearsal.",
        "Security logged a wooden training rifle returned to the ROTC storage room.",
        "The club displayed a rubber replica sword at the cultural exhibit.",
    ],
)
def test_non_weapon_senses_and_recorded_absence_do_not_escalate(narrative: str) -> None:
    label, reason = policy_guardrail(narrative, "MINOR-13")

    assert label != "MAJOR-25"
    assert reason != "explicit_deadly_weapon_reference"


def test_weapon_depicted_on_clothing_is_treated_as_a_clothing_offense() -> None:
    label, reason = policy_guardrail(
        "The student wore a shirt with a printed gun graphic inside the campus.",
        "MINOR-03",
    )

    assert label == "MINOR-04"
    assert reason == "inappropriate_message_or_graphic_on_clothing"


@pytest.mark.parametrize("predicted_label", ["MAJOR-12", "MAJOR-25", "MAJOR-38"])
def test_weapon_escalation_leaves_an_existing_major_ranking_to_the_officer(
    predicted_label: str,
) -> None:
    label, reason = policy_guardrail(
        "A student pointed a gun at a classmate inside the campus.", predicted_label
    )

    assert label == predicted_label
    assert reason is None


@pytest.mark.parametrize(
    ("narrative", "expected_reason"),
    [
        (
            "The student used a glue gun while preparing a project display.",
            "non_weapon_sense_of_the_term",
        ),
        (
            "The student's essay argued for stricter gun control laws.",
            "non_weapon_sense_of_the_term",
        ),
        (
            "Security inspected the bag during rounds and found no weapon of any kind.",
            "recorded_absence_of_a_weapon",
        ),
        (
            "The student did not bring a gun to the campus.",
            "recorded_absence_of_a_weapon",
        ),
        (
            "Hindi nagdala ng baril ang estudyante.",
            "recorded_absence_of_a_weapon",
        ),
        (
            "The student wore a shirt with a printed gun graphic.",
            "weapon_depicted_not_carried",
        ),
    ],
)
def test_a_benign_weapon_narrative_suppresses_a_top_ranked_major25(
    narrative: str, expected_reason: str
) -> None:
    assert policy_demotion(narrative, "MAJOR-25") == expected_reason


@pytest.mark.parametrize(
    "narrative",
    [
        "The student was found carrying a knife inside the University premises.",
        "the student is bringing a gun",
        "May dalang baril ang estudyante sa loob ng campus.",
        "The student used a glue gun and also carried a knife in the same bag.",
    ],
)
def test_a_genuine_weapon_narrative_is_never_suppressed(narrative: str) -> None:
    assert policy_demotion(narrative, "MAJOR-25") is None


@pytest.mark.parametrize("predicted_label", ["MINOR-02", "MAJOR-15", "NO_MATCH"])
def test_suppression_only_ever_applies_to_a_top_ranked_major25(
    predicted_label: str,
) -> None:
    assert policy_demotion("The student used a glue gun.", predicted_label) is None


def test_a_weapon_alongside_a_benign_mention_still_escalates() -> None:
    label, reason = policy_guardrail(
        "The student used a glue gun and also carried a knife in the same bag.",
        "MINOR-13",
    )

    assert label == "MAJOR-25"
    assert reason == "explicit_deadly_weapon_reference"


# --- 2026-09-04-v8 compliance / benign-context demotions and lay-term escalations


@pytest.mark.parametrize(
    "narrative, predicted",
    [
        ("Asked at the turnstile, the student pulled the ID out of a pocket and let the guard scan it.", "MAJOR-20"),
        ("When the guard asked to see identification, the student handed over the University ID without argument.", "MAJOR-20"),
        ("The student dropped the empty juice box into the designated bin near the stairwell.", "MINOR-08"),
        ("The student read a borrowed reference book at the table and returned it to the counter.", "MINOR-11"),
        ("The student's hair was its natural black colour and neatly tied back.", "MINOR-12"),
        ("Log entry: student went past the second floor landing at around noon.", "MINOR-15"),
        ("The two students bumped into each other at the corner, said sorry, and moved on.", "MAJOR-10"),
    ],
)
def test_compliance_and_benign_context_demote_to_no_match(narrative: str, predicted: str) -> None:
    label, reason = policy_guardrail(narrative, predicted)

    assert label == "NO_MATCH"
    assert reason is not None


@pytest.mark.parametrize(
    "narrative, predicted",
    [
        ("Told twice to present identification, the student said no and kept walking.", "MAJOR-20"),
        ("The student refused to show the University ID when the guard asked for it.", "NO_MATCH"),
        ("The student threw the wrapper on the corridor floor and walked away.", "MINOR-08"),
        ("The student hid two reserve books behind a shelf so no one else could borrow them.", "MINOR-11"),
        ("The student had dyed the hair bright red against the uniform policy.", "MINOR-12"),
    ],
)
def test_genuine_violations_are_not_demoted_by_the_compliance_rules(narrative: str, predicted: str) -> None:
    label, _ = policy_guardrail(narrative, predicted)

    assert label != "NO_MATCH" or predicted == "NO_MATCH"


def test_plain_refusal_to_present_id_is_escalated_to_major20() -> None:
    label, reason = policy_guardrail(
        "Told twice to present identification, the student said no and kept walking toward the elevators.",
        "NO_MATCH",
    )

    assert label == "MAJOR-20"
    assert reason == "refusal_to_present_university_id"


@pytest.mark.parametrize(
    "narrative, predicted",
    [
        ("The student admitted smoking pot behind the gymnasium before the first class.", "MAJOR-26"),
        ("A classmate said the strip of tablets in the student's pocket was ecstasy.", "MINOR-05"),
        ("Weed wrapped in foil was recovered from the student's shoe at the gate.", "MINOR-04"),
    ],
)
def test_lay_drug_terms_escalate_to_major38(narrative: str, predicted: str) -> None:
    label, reason = policy_guardrail(narrative, predicted)

    assert label == "MAJOR-38"
    assert reason == "explicit_dangerous_drug_reference"


def test_ordinary_pot_word_senses_do_not_escalate_to_a_drug_offence() -> None:
    for narrative in (
        "The student knocked over a flower pot near the entrance.",
        "The student used a glue gun and a cooking pot for the project display.",
    ):
        label, _ = policy_guardrail(narrative, "MINOR-08")
        assert label != "MAJOR-38"


def test_paddling_during_initiation_is_hazing() -> None:
    label, reason = policy_guardrail(
        "Applicants to an unrecognised fraternity were beaten with a wooden paddle during their initiation.",
        "MAJOR-34",
    )

    assert label == "MAJOR-32"
    assert reason == "explicit_hazing_during_initiation"


def test_slingshot_with_ammunition_is_a_deadly_weapon() -> None:
    label, reason = policy_guardrail(
        "Guards took a slingshot and a pouch of steel pellets from the student at the entrance.",
        "NO_MATCH",
    )

    assert label == "MAJOR-25"
    assert reason == "explicit_deadly_weapon_reference"


@pytest.mark.parametrize("label", ["MAJOR-13", "MAJOR-42", "MINOR-06", "NO_MATCH"])
def test_explicit_punch_promotes_physical_injury_candidate(label):
    assert policy_guardrail(
        "During lunch break, he punched another student in the face following a verbal disagreement.", label
    ) == ("MAJOR-11", "explicit_punch_to_person")


@pytest.mark.parametrize("text", [
    "He did not punch another student in the face.",
    "He denied that he punched another student in the face.",
    "During a drama play he punched another student in the face.",
    "He threatened to punch another student in the face.",
    "She reported that someone punched another student in the face.",
    "During boxing training he punched another student in the face.",
    "He punched a bag after a verbal disagreement.",
    "He accidentally punched another student in the face.",
])
def test_contact_rule_does_not_promote_ambiguous_context(text):
    assert policy_guardrail(text, "MAJOR-13") != ("MAJOR-11", "explicit_punch_to_person")
