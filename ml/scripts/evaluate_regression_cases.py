#!/usr/bin/env python3
"""Run high-value production regressions against a frozen hybrid classifier."""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from hybrid_inference import (
    DEFAULT_HYBRID_MANIFEST,
    DEFAULT_HYBRID_MODEL,
    DEFAULT_ROBERTA_MODEL_DIR,
    DEFAULT_TFIDF_MODEL,
    HybridOffensePredictor,
)


DEFAULT_CASES = Path(__file__).resolve().parents[1] / "data" / "inference_regression_cases.json"


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--cases", type=Path, default=DEFAULT_CASES)
    parser.add_argument("--roberta-model-dir", type=Path, default=DEFAULT_ROBERTA_MODEL_DIR)
    parser.add_argument("--tfidf-model", type=Path, default=DEFAULT_TFIDF_MODEL)
    parser.add_argument("--hybrid-model", type=Path, default=DEFAULT_HYBRID_MODEL)
    parser.add_argument("--hybrid-manifest", type=Path, default=DEFAULT_HYBRID_MANIFEST)
    parser.add_argument("--output", type=Path, default=None)
    return parser.parse_args()


def main() -> int:
    arguments = parse_arguments()
    cases = json.loads(arguments.cases.read_text(encoding="utf-8"))
    predictor = HybridOffensePredictor(
        roberta_model_dir=arguments.roberta_model_dir,
        tfidf_model_path=arguments.tfidf_model,
        hybrid_model_path=arguments.hybrid_model,
        manifest_path=arguments.hybrid_manifest,
    )

    results = []
    for case in cases:
        prediction = predictor.predict(str(case["text"]), top_k=3)
        actual = prediction["predictions"][0]["label"]
        # A case pins either the label that must rank first, or - where the
        # correct runner-up is a judgement call and pinning it would be
        # brittle - the label that must not rank first.
        expected = case.get("expected_label")
        forbidden = case.get("forbidden_label")
        if expected is None and forbidden is None:
            raise ValueError(
                f"Case {case['id']} sets neither expected_label nor forbidden_label"
            )
        passed = (expected is None or actual == expected) and (
            forbidden is None or actual != forbidden
        )
        results.append(
            {
                "id": case["id"],
                "expected_label": expected,
                "forbidden_label": forbidden,
                "actual_label": actual,
                "passed": passed,
                "top_predictions": prediction["predictions"],
                "guardrail": prediction["guardrail"],
            }
        )

    if arguments.output is not None:
        arguments.output.parent.mkdir(parents=True, exist_ok=True)
        arguments.output.write_text(
            json.dumps(results, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )

    for result in results:
        state = "PASS" if result["passed"] else "FAIL"
        score = result["top_predictions"][0]["score"]
        requirement = (
            f"expected={result['expected_label']}"
            if result["expected_label"] is not None
            else f"forbidden={result['forbidden_label']}"
        )
        print(
            f"{state} {result['id']}: {requirement} "
            f"actual={result['actual_label']} score={score:.4f}"
        )
    passed = sum(bool(result["passed"]) for result in results)
    print(f"Regression cases: {passed}/{len(results)} passed")
    return 0 if passed == len(results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
