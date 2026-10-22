# v6 promotion report

Run date: 2026-09-15
Decision: **Promote the v6 statistical weights.** Guardrail unchanged
(`2026-09-14-v9`).

## Trigger

The classify screen for "During lunch break, he punched another student in the
face following a verbal disagreement." showed one handbook-rule card for
`MAJOR-11` instead of the usual three model suggestions. The v5 ensemble scored
`MAJOR-11` at 1.8%; the explicit-punch guardrail forced it to rank 1, and until
the serving fix below the card displayed the displaced `MAJOR-13` score (17.4%).

## Root cause

All 17 `MAJOR-11` training narratives stated an injury outcome ("causing a
bleeding lip", "nasugatan"). The model learned that `MAJOR-11` requires a
recorded injury, so a report of a landed blow alone ranked `MAJOR-13` first.
Appending "causing a bleeding lip" to the same narrative raised v5's `MAJOR-11`
score from 1.8% to 35.5%. Bare physical-contact narratives also leaned toward
`MAJOR-42`, whose 19 training rows all share the shape "student touches another
student's body part".

## Serving changes

- `hybrid_inference.py`: a guardrail promotion reorders the ranking instead of
  swapping two labels' scores, so every label keeps its own probability.
- `AdvisoryOffenseClassificationService`: removed the punch-specific filter that
  hid ranks 2-3; every promotion now uses the same noise floor.
- `show.blade.php`: heading "Suggested offenses"; badge "Model + Handbook Rule"
  when a rule promoted rank 1; a rule-backed card shows its own model score.
- `LOW_CONFIDENCE_SCORE` lowered from 0.35 to 0.20 after calibration (below);
  the banner now points the officer to the catalog search.

## Dataset change

`data/landed_blow_coverage_2026-09-15.csv`: 21 `pending_human_review` rows in 7
English/Tagalog/Taglish groups, merged with `merge_cohort_rows.py` and pinned
to `train`.

| Groups | Label | Purpose |
|---|---|---|
| 5 (15 rows) | `MAJOR-11` | Landed blow with no recorded injury: punch to face or stomach, slap, kick, elbow |
| 1 (3 rows) | `MAJOR-13` | Taunting that escalates to shouting, no one struck |
| 1 (3 rows) | `MAJOR-10` | Two students exchanging blows |

Training rows went from 1,579 to 1,600. The validation and reserved test
splits hold the identical 247 narratives each (`test.jsonl` is byte-identical;
only `id` strings shift in `validation.jsonl`). The pinned train count in
`ml/tests/test_pilot_common.py` was updated.

## Candidate training

Pinned `jcblaise/roberta-tagalog-base` revision
`7ab976a69b7cc338424ca08d228206e788756398`, seed 42, 10 epochs (matching v5),
balanced class weights, CPU, 5,182 s. Validation accuracy was still rising at
epoch 10 (92.31% to 92.71%), so early stopping did not trigger. TF-IDF and the
SVM component were retrained on the same split.

| Model | Split | Accuracy | Macro-F1 | Top-3 recall |
|---|---|---:|---:|---:|
| RoBERTa v5 | Validation | 91.90% | 92.38% | 97.98% |
| RoBERTa v6 | Validation | 92.71% | 93.33% | 98.38% |
| Hybrid v5 | Validation | **96.36%** | **96.68%** | 99.60% |
| Hybrid v6 | Validation | 95.14% | 95.67% | 99.60% |
| Hybrid v5 | Reserved test | 91.90% | 91.82% | **97.98%** |
| Hybrid v6 | Reserved test | **92.31%** | **92.32%** | 97.57% |

Validation: v6 lost 3 rows that v5 ranked correctly and gained none; all 3 keep
the correct label in the top 3 (a hazing narrative with a kick ranked
`NO_MATCH`, repeated pushing ranked `MAJOR-10` instead of `MAJOR-36`, and an
approved stool rearrangement ranked `MINOR-09`). The ensemble weights were
tuned on validation for v5's components and are reused untuned.

Reserved test, touched once for this comparison: v6 gained one row (a concealed
taser now ranks `MAJOR-25`) and lost one top-3 hit (overt sexual behaviour,
`MAJOR-44`, ranked wrong at top-1 by both models).

## Regression gates

| Gate | v5 | v6 |
|---|---:|---:|
| `inference_contact_probes_2026-09-15.json` (new, 14 cases) | 12/14 | **14/14** |
| `inference_regression_cases.json` (42 cases) | 42/42 | 42/42 |
| `inference_blind_probes_2026-09-04.json` (20 cases) | 20/20 | 20/20 |

v5's passes on the reported narrative and the bare "punched another student in
the face" relied on the guardrail (model scores 1.8% and 5.1%). v6 passes all
14 contact probes without any guardrail action. The contact probes were checked
for exact and near-duplicate overlap against every split, the new cohort, and
the existing gates.

The reported narrative on v6 ranks `MAJOR-11` 48.8%, `MAJOR-42` 2.6%,
`MAJOR-13` 2.4%, with no guardrail applied.

## Low-confidence threshold

| Top-1 score | Validation: top-1 / top-3 | Reserved test: top-1 / top-3 |
|---|---:|---:|
| Under 0.20 | 45.5% / 90.9% | 53.8% / 69.2% |
| 0.20 to 0.35 | 87.1% / 100.0% | 84.8% / 97.0% |

At 0.35 the banner fired on 17.0% (validation) and 18.6% (reserved test) of
cases; at 0.20 it fires on 4.5% and 5.3%. Across both splits the change leaves
one additional wrong top-1 unflagged whose correct label is also outside the
top 3 (reserved test).

## Promotion

- `ml/scripts/hybrid_inference.py` default artifact paths updated from `-v5`
  to `-v6`.
- The `-v5` artifacts remain in `ml/artifacts/` (gitignored) as a rollback.
- `ml/artifacts-deploy-v6/` holds the three serving folders without checkpoints
  (8 files, about 460 MB), hash-verified against their sources and load-tested
  on their own.
- Reserved-test directories `roberta-v6-test`, `tfidf-v6-test` and
  `hybrid-v6-reserved` are kept for the record.

## Outstanding

- **Server deploy.** Copy the three `ml/artifacts-deploy-v6/` folders into
  `ml/artifacts/` on the server and restart the classifier. The readiness check
  compares only `GUARDRAIL_VERSION`, which did not change, so it cannot detect a
  server still loading v5; confirm the served `model.version` reads
  `1BC0E67365EC-2026-09-14-v9`.
- Remove `ml/artifacts-deploy-v5/` only after the server is confirmed on v6.
- The 21 new rows are `pending_human_review`; Student Discipline Office review
  is still owed.
- `MAJOR-12`'s "or any student" branch has no training narratives; whether
  student-on-student assault belongs there is a policy decision for the SDO.
- The "verify the injury evidence" note appears only when the punch guardrail
  fires, which v6 no longer needs for these narratives.
- The classifier remains advisory and must not confirm an offense or replace
  officer review.
