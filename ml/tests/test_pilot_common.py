from __future__ import annotations

import json
import math
import sys
import tempfile
import unittest
from pathlib import Path


SCRIPTS_DIR = Path(__file__).resolve().parents[1] / "scripts"
sys.path.insert(0, str(SCRIPTS_DIR))

from pilot_common import (  # noqa: E402
    DEFAULT_DATA_DIR,
    PilotDataError,
    evaluate_rankings,
    load_dataset,
    require_test_override,
    write_evaluation_artifacts,
)
from build_training_splits import (  # noqa: E402
    generated_group_id,
    policy_case_metadata,
    reviewed_statuses,
)
from policy_guardrails import policy_guardrail  # noqa: E402


class GeneratedGroupIdTest(unittest.TestCase):
    def test_new_ids_are_stable_and_do_not_reuse_legacy_namespace(self) -> None:
        first = generated_group_id("MAJOR-01", "a newly added scenario")
        repeated = generated_group_id("MAJOR-01", "a newly added scenario")
        different = generated_group_id("MAJOR-01", "a different scenario")

        self.assertEqual(first, repeated)
        self.assertNotEqual(first, different)
        self.assertTrue(first.startswith("ADDED-MAJOR-01-"))
        self.assertNotEqual("LEGACY-MAJOR-01-001", first)

    def test_reviewed_statuses_are_keyed_by_normalized_description(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            path = Path(temporary_directory) / "review.csv"
            path.write_text(
                "description,review_status\n"
                '"  Reviewed   Scenario  ",approved\n',
                encoding="utf-8",
            )
            self.assertEqual(
                {"reviewed scenario": "approved"},
                reviewed_statuses(path),
            )

    def test_major30_policy_cases_preserve_reviewed_multilingual_groups(self) -> None:
        metadata = policy_case_metadata(
            DEFAULT_DATA_DIR.parent / "major30_policy_cases.csv"
        )
        english = metadata[
            "a student formed a movement openly advocating the violent removal "
            "of the recognized university administration."
        ]
        tagalog = metadata[
            "nagtatag ang mag-aaral ng kilusan na hayagang nananawagan ng marahas "
            "na pagpapatalsik sa kinikilalang university administration."
        ]

        self.assertEqual("MAJOR-30", english["label"])
        self.assertEqual(english["scenario_group_id"], tagalog["scenario_group_id"])
        self.assertEqual("approved_policy_boundary", english["review_status"])

    def test_keyboard_boundary_cases_are_grouped_and_training_only(self) -> None:
        metadata = policy_case_metadata(
            DEFAULT_DATA_DIR.parent / "classification_boundary_cases.csv"
        )
        english = metadata[
            "during routine computer-laboratory rounds, a student rearranged "
            "removable keycaps on an assigned university keyboard without "
            "permission, contrary to the posted equipment-use rules; no key "
            "was damaged."
        ]
        taglish = metadata[
            "during routine computer-lab rounds, ni-rearrange ng student ang "
            "removable keycaps ng assigned university keyboard without "
            "permission, contrary sa posted equipment-use rules; walang key "
            "na nasira."
        ]

        self.assertEqual("MAJOR-45", english["label"])
        self.assertEqual(english["scenario_group_id"], taglish["scenario_group_id"])
        self.assertEqual("train", english["required_split"])
        self.assertEqual("synthetic_keyboard_boundary", english["source_type"])


class PolicyGuardrailTest(unittest.TestCase):
    def test_vague_keyboard_rearrangement_abstains_from_property_damage(self) -> None:
        label, reason = policy_guardrail(
            "The student is seen rearranging the keyboard keys during rounds.",
            "MAJOR-15",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertEqual("keyboard_rearrangement_without_damage_evidence", reason)

    def test_unauthorized_lab_keyboard_rearrangement_is_lab_policy(self) -> None:
        label, reason = policy_guardrail(
            "During computer laboratory rounds, a student rearranged keycaps "
            "on an assigned workstation without permission; no keys were damaged.",
            "MAJOR-15",
        )
        self.assertEqual("MAJOR-45", label)
        self.assertEqual(
            "explicit_unauthorized_lab_keyboard_rearrangement",
            reason,
        )

    def test_damaged_keyboard_remains_property_damage(self) -> None:
        label, reason = policy_guardrail(
            "A student deliberately snapped keys off a University keyboard "
            "and left it unusable.",
            "MAJOR-15",
        )
        self.assertEqual("MAJOR-15", label)
        self.assertIsNone(reason)

    def test_explicit_concealed_exam_aid_is_not_no_match(self) -> None:
        label, reason = policy_guardrail(
            "During the ITE420 long examination in Computer Laboratory 2, "
            "Nica was observed using a mobile phone beneath the desk while "
            "answering the examination. The phone displayed notes related to "
            "questions on the test. Two students seated nearby and the assigned "
            "laboratory assistant witnessed the incident.",
            "NO_MATCH",
        )
        self.assertEqual("MAJOR-01", label)
        self.assertEqual("explicit_concealed_assessment_aid", reason)

    def test_permitted_phone_retrieval_after_exam_remains_no_match(self) -> None:
        label, reason = policy_guardrail(
            "After the examination ended, the proctor allowed the student to "
            "retrieve the phone stored at the front of the room.",
            "NO_MATCH",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertIsNone(reason)

    def test_phone_presence_without_access_to_test_material_remains_no_match(self) -> None:
        label, reason = policy_guardrail(
            "During the examination, the proctor saw a powered-off phone sealed "
            "inside the student's bag, and the student never accessed it.",
            "NO_MATCH",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertIsNone(reason)

    def test_concealed_phone_use_without_test_content_remains_no_match(self) -> None:
        label, reason = policy_guardrail(
            "During the examination, the student used a phone beneath the desk "
            "only to silence an incoming emergency call.",
            "NO_MATCH",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertIsNone(reason)

    def test_peaceful_approved_demonstration_is_not_subversion(self) -> None:
        label, reason = policy_guardrail(
            "A recognized organization held a peaceful demonstration in the "
            "approved area and made no threats.",
            "MAJOR-30",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertEqual("lawful_peaceful_demonstration", reason)

    def test_good_faith_petition_is_not_subversion(self) -> None:
        label, reason = policy_guardrail(
            "Students submitted a petition through the recognized student council "
            "and requested a formal dialogue.",
            "MAJOR-30",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertEqual("good_faith_petition_or_complaint", reason)

    def test_guardrail_does_not_hide_violent_subversion(self) -> None:
        label, reason = policy_guardrail(
            "The student recruited an armed group to violently overthrow lawful "
            "University authority.",
            "MAJOR-30",
        )
        self.assertEqual("MAJOR-30", label)
        self.assertIsNone(reason)

    def test_authorized_own_record_access_is_not_an_access_offense(self) -> None:
        label, reason = policy_guardrail(
            "The student used their own University portal account and opened "
            "only their own enrollment record.",
            "MAJOR-46",
        )
        self.assertEqual("NO_MATCH", label)
        self.assertEqual("authorized_own_account_and_record", reason)

    def test_clothing_graphic_is_not_drug_possession(self) -> None:
        label, _reason = policy_guardrail(
            "A student wore a jacket with graphics promoting illegal drugs.",
            "MAJOR-38",
        )
        self.assertEqual("MINOR-04", label)

    def test_unwanted_physical_contact_is_not_only_a_verbal_advance(self) -> None:
        label, _reason = policy_guardrail(
            "The student attempted an unwanted sexual kiss in the stairwell.",
            "MAJOR-43",
        )
        self.assertEqual("MAJOR-42", label)

    def test_unrelated_prediction_is_left_unchanged(self) -> None:
        label, reason = policy_guardrail(
            "The student ate noodles inside the chemistry laboratory.",
            "MINOR-07",
        )
        self.assertEqual("MINOR-07", label)
        self.assertIsNone(reason)


class FrozenDatasetContractTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.bundle = load_dataset(DEFAULT_DATA_DIR)

    def test_current_processed_dataset_has_leakage_safe_model_splits(self) -> None:
        self.assertEqual(60, len(self.bundle.labels))
        self.assertIn("MAJOR-30", self.bundle.labels)
        self.assertEqual(1600, len(self.bundle.rows_by_split["train"]))
        self.assertEqual(247, len(self.bundle.rows_by_split["validation"]))
        self.assertEqual(247, len(self.bundle.rows_by_split["test"]))

        groups = {
            split: {
                row["scenario_group_id"]
                for row in self.bundle.rows_by_split[split]
            }
            for split in ("train", "validation", "test")
        }
        self.assertTrue(groups["train"].isdisjoint(groups["validation"]))
        self.assertTrue(groups["train"].isdisjoint(groups["test"]))
        self.assertTrue(groups["validation"].isdisjoint(groups["test"]))

    def test_reserved_test_split_requires_explicit_acknowledgement(self) -> None:
        with self.assertRaises(PilotDataError):
            require_test_override("test", False)
        require_test_override("test", True)
        require_test_override("validation", False)


class RankingEvaluationTest(unittest.TestCase):
    def setUp(self) -> None:
        self.rows = [
            {
                "id": "A-G01-EN",
                "primary_offense_code": "A",
                "language": "en",
                "difficulty": "easy",
                "scenario_group_id": "A-G01",
            },
            {
                "id": "B-G01-TL",
                "primary_offense_code": "B",
                "language": "tl",
                "difficulty": "hard",
                "scenario_group_id": "B-G01",
            },
        ]
        self.predictions = {
            "A-G01-EN": [
                {"label": "A", "score": 0.7},
                {"label": "B", "score": 0.2},
                {"label": "C", "score": 0.1},
            ],
            "B-G01-TL": [
                {"label": "C", "score": 0.6},
                {"label": "B", "score": 0.3},
                {"label": "A", "score": 0.1},
            ],
        }

    def test_metrics_include_top_k_slices_and_calibration(self) -> None:
        metrics, detailed = evaluate_rankings(
            self.rows,
            self.predictions,
            ["A", "B", "C"],
            top_k=3,
            run_metadata={"name": "unit-test"},
        )

        self.assertEqual(0.5, metrics["summary"]["accuracy"])
        self.assertEqual(1.0, metrics["summary"]["top_3_recall"])
        self.assertEqual(1.0, metrics["slices"]["language"]["en"]["accuracy"])
        self.assertEqual(0.0, metrics["slices"]["language"]["tl"]["accuracy"])
        self.assertIsNotNone(metrics["calibration"])
        self.assertTrue(detailed[0]["correct"])
        self.assertTrue(detailed[1]["correct_in_top_3"])

    def test_prediction_ids_must_match_the_evaluation_rows(self) -> None:
        incomplete = dict(self.predictions)
        incomplete.pop("B-G01-TL")
        with self.assertRaises(PilotDataError):
            evaluate_rankings(
                self.rows,
                incomplete,
                ["A", "B", "C"],
            )

    def test_probability_log_loss_uses_the_handbook_label_order(self) -> None:
        rows = [
            {
                "id": "NO-MATCH-G01-EN",
                "primary_offense_code": "NO_MATCH",
                "language": "en",
                "difficulty": "hard",
                "scenario_group_id": "NO-MATCH-G01",
            }
        ]
        predictions = {
            "NO-MATCH-G01-EN": [
                {"label": "MAJOR-01", "score": 0.6},
                {"label": "NO_MATCH", "score": 0.3},
                {"label": "MINOR-01", "score": 0.1},
            ]
        }
        metrics, _detailed = evaluate_rankings(
            rows,
            predictions,
            ["NO_MATCH", "MINOR-01", "MAJOR-01"],
        )

        self.assertAlmostEqual(
            -math.log(0.3),
            metrics["calibration"]["log_loss"],
        )

    def test_rankings_reject_non_finite_or_unsorted_scores(self) -> None:
        unsorted = {
            **self.predictions,
            "A-G01-EN": [
                {"label": "A", "score": 0.2},
                {"label": "B", "score": 0.7},
                {"label": "C", "score": 0.1},
            ],
        }
        with self.assertRaises(PilotDataError):
            evaluate_rankings(self.rows, unsorted, ["A", "B", "C"])

        non_finite = {
            **self.predictions,
            "A-G01-EN": [
                {"label": "A", "score": float("nan")},
                {"label": "B", "score": 0.2},
                {"label": "C", "score": 0.1},
            ],
        }
        with self.assertRaises(PilotDataError):
            evaluate_rankings(self.rows, non_finite, ["A", "B", "C"])

    def test_artifact_writer_creates_machine_readable_outputs(self) -> None:
        metrics, detailed = evaluate_rankings(
            self.rows,
            self.predictions,
            ["A", "B", "C"],
        )
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_dir = Path(temporary_directory)
            write_evaluation_artifacts(output_dir, metrics, detailed)

            self.assertTrue((output_dir / "metrics.json").is_file())
            self.assertTrue((output_dir / "predictions.jsonl").is_file())
            self.assertTrue((output_dir / "per_class.csv").is_file())
            self.assertTrue((output_dir / "confusion_matrix.csv").is_file())
            self.assertTrue((output_dir / "top_confusions.csv").is_file())
            loaded = json.loads(
                (output_dir / "metrics.json").read_text(encoding="utf-8")
            )
            self.assertEqual(1, loaded["schema_version"])
            prediction = json.loads(
                (output_dir / "predictions.jsonl")
                .read_text(encoding="utf-8")
                .splitlines()[0]
            )
            self.assertEqual(
                len(self.predictions["A-G01-EN"]),
                len(prediction["top_predictions"]),
            )


if __name__ == "__main__":
    unittest.main()
