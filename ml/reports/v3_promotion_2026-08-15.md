# v3 promotion report

Run date: 2026-08-15
Decision: **Promote the v3 statistical weights and the v5 guardrail.**

## Trigger

Two real-world misclassifications were reported:

1. "wearing flip-flops (sandals)" ranked `MAJOR-12` (direct assault) instead
   of `MINOR-03` (inappropriate attire).
2. "re-arranging keyboard keycaps" ranked `MAJOR-15` (destruction of
   property) instead of abstaining or routing to `MAJOR-45`, even though a
   guardrail already exists for this exact keyboard-rearrangement boundary.

## Root-cause split

The two failures had different causes and were fixed differently:

- Case 2 was a **guardrail regex bug**, not a dataset or model gap. The
  existing keyboard-rearrangement guardrail in `policy_guardrails.py`
  matched `rearrang\w*`, which requires the literal unbroken string
  "rearrang" and therefore never matched the hyphenated "re-arranging". The
  regex was widened to `re[- ]?arrang\w*` / `re[- ]?position\w*` to tolerate
  an optional hyphen or space. Guardrail version bumped `2026-08-11-v4` →
  `2026-08-15-v5`.
- Case 1 was a **genuine dataset gap**. `MINOR-03` already had 20 rows
  covering "slippers", "slides", and "beach slippers", but the literal word
  "flip-flops" (or Tagalog "tsinelas") never appeared anywhere in the
  corpus, so the model had no anchor for that vocabulary and defaulted to a
  high-confidence spurious guess.

## Dataset change

Documented in full in `reports/dataset_realism_expansion_2026-08-15.md`.
Summary: 57 new rows across 19 reviewed scenario groups (English/
Tagalog/Taglish), marked `pending_human_review`, added to
`data/processed/prism_training.csv`:

- 5 groups (15 rows) closing the `MINOR-03` flip-flops/tsinelas gap.
- 14 groups (42 rows) adding terser blotter/duty-log/IT-ticket-register
  phrasing across `NO_MATCH`, `MINOR-01`, `MINOR-04`, `MAJOR-08`,
  `MAJOR-13`, `MAJOR-40`, `MAJOR-42`, `MAJOR-43`, `MAJOR-45`, `MAJOR-46`,
  `MAJOR-47`, alongside the existing narrative-sentence register.

Train split grew 1,246 → 1,303 rows; validation and test splits were
unchanged (247/247, all new groups forced to `train`). New dataset
fingerprint: `B50B4F02BD042D8787B73795A0E05E3A60CB10FE502DB957F18A7BF35CE21D3E`.

## Candidate training

The pinned `jcblaise/roberta-tagalog-base` revision
`7ab976a69b7cc338424ca08d228206e788756398` was retrained with seed 42 and
the established balanced 8-epoch protocol on CPU (`train_transformer.py`
defaults). Epoch 8 was selected by validation macro-F1. TF-IDF logistic
regression and the linear-SVM hybrid component were retrained on the same
expanded training split (`train_baseline.py`, `evaluate_hybrid.py`).

| Model | Split | Accuracy | Macro-F1 | Top-3 recall |
|---|---|---:|---:|---:|
| Deployed hybrid (v2) | Validation | 94.74% | 95.37% | 99.60% |
| Candidate hybrid (v3) | Validation | **96.36%** | **97.45%** | 99.60% |
| Deployed hybrid (v2) | Reserved test | 92.31% | 92.30% | **97.17%** |
| Candidate hybrid (v3) | Reserved test | 92.31% | **92.42%** | 96.76% |

Unlike the 2026-08-11 keyboard-boundary candidate (validation improved,
reserved test regressed 0.81pp), this candidate holds reserved-test accuracy
exactly flat and edges macro-F1 up, with only a small top-3 recall dip. Both
originally reported cases resolve correctly at the raw model level with no
guardrail intervention needed:

- "A student was seen wearing flip-flops instead of the prescribed school
  shoes." → `MINOR-03` at 92.5% confidence.
- "During routine rounds, the student was seen re-arranging keyboard
  keycaps." → top prediction moved off `MAJOR-15` entirely (now in the
  `MAJOR-45`/`NO_MATCH` range, consistent with the guardrail's own logic for
  an unspecified-context report).

All 9 frozen keyboard and examination-phone regression cases pass
(`evaluate_regression_cases.py`). All 25 ML unit/contract tests pass.

## Promotion

- `ml/artifacts/roberta-prism-offense-v3-candidate` → renamed
  `roberta-prism-offense-v3`
- `ml/artifacts/tfidf-prism-offense-v3-candidate` → renamed
  `tfidf-prism-offense-v3`
- `ml/artifacts/hybrid-prism-offense-v3-candidate` → renamed
  `hybrid-prism-offense-v3`
- `ml/scripts/hybrid_inference.py` default artifact paths updated from `-v2`
  to `-v3`.
- The prior `-v2` artifacts remain on disk (gitignored, not deleted) as a
  rollback fallback; they are simply no longer the default loaded by
  `HybridOffensePredictor`.
- One-time reserved-test evaluation scratch directories
  (`*-v3-candidate-test`) were deleted after their metrics were recorded
  above; they are reproducible via `--eval-split test
  --allow-test-evaluation` against the promoted artifacts if needed again.

## Outstanding

The new training rows are marked `pending_human_review`. Per the Review
requirement in `ml/README.md`, Student Discipline Office and adviser review
of the new narratives is still owed before they are "approved training
data," independent of this statistical promotion. The classifier remains
advisory and must not confirm an offense or replace officer review.
