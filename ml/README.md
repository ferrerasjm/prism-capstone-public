# NU Clark Offense Classification Dataset

This directory contains synthetic, handbook-aligned incident narratives for
developing and evaluating an English, Tagalog, and Taglish offense classifier.
The source of truth is the **NU Clark Student Handbook 2025 Edition**, Section
VI.D (Minor Offenses) and Section VI.E (Major Offenses).

Handbook provenance:

- Policy title: `NU Clark Student Handbook 2025 Edition`
- Relevant printed pages: 32–39
- SHA-256:
  `C38AD7A9AB438A97CB54659A885B56B517BBECDB9FCEC7E8AC5C45B2FFDC157D`

## Scope

- 15 minor-offense labels.
- 45 campus-applicable major-offense labels; 44 are ranked by the language model.
- `MAJOR-22` (Violation of Student Athlete Dormitory Rules and Regulations) is
  intentionally excluded because NU Clark has no on-campus dormitories.
- `MAJOR-44` remains included. The handbook identifies it as public display of
  attention/affection; it is not the dormitory offense.
- `MAJOR-19` scenarios are retained for rule and end-to-end testing, but are
  marked `model_eligible: false`. Habitual disregard is established from
  confirmed violation history, not semantic meaning in a new incident report.
- `MAJOR-30` is model-eligible under the project operational definition dated
  2026-08-01. It requires deliberate, coordinated, violent, seriously illegal,
  or security-threatening action aimed at overthrowing/replacing lawful
  authority or making lawful operations inoperative. Peaceful criticism,
  lawful organizations and demonstrations, petitions, and good-faith complaints
  are explicit negative boundaries.
- `NO_MATCH` examples represent ordinary campus activity, insufficient
  evidence, or reports that should be routed to manual review instead of being
  forced into an offense label.

Translations and paraphrases belonging to the same reviewed scenario group
remain wholly within one data split. This prevents multilingual versions of the
same facts from leaking across training and evaluation.

Current official training distribution:

| Cohort | Examples |
|---|---:|
| Simple reviewed/working CSV | 2,114 |
| Rule-derived `MAJOR-19` excluded from narrative modeling | 20 |
| Model-eligible training split | 1,600 |
| Model-eligible validation split | 247 |
| Model-eligible reserved test split | 247 |

The 2026-08-15 realism-expansion pass added 57 `pending_human_review` rows
(19 English/Tagalog/Taglish scenario groups) covering the flip-flops/tsinelas
`MINOR-03` vocabulary gap and terse "blotter"/log-style phrasing across other
labels. All 57 rows landed in the training split; the frozen validation and
reserved test splits are unchanged. See
`reports/dataset_realism_expansion_2026-08-15.md`.

The 2026-09-15 landed-blow pass added 21 `pending_human_review` rows (7
English/Tagalog/Taglish scenario groups): landed blows with no recorded injury
for `MAJOR-11`, plus provocation-only `MAJOR-13` and mutual-fight `MAJOR-10`
boundary groups. All 21 rows are pinned to the training split; the frozen
validation and reserved test narratives are unchanged. See
`reports/landed_blow_retrain_2026-09-15.md`.

`MAJOR-19` remains excluded from narrative training because it is derived from
confirmed offense history. Current official split counts are recorded by the
frozen artifact manifests and enforced by `ml/tests/test_pilot_common.py`.

This is a balanced pilot dataset, not the final research corpus. Because the
three language versions of a group describe the same incident, each offense
currently has only three independent training fact patterns. After the first
SDO review, expand toward at least 20 distinct fact patterns per model label
(14 train, 3 validation, and 3 test) and incorporate adjudicated,
de-identified historical descriptions where approval and privacy controls
permit.

## Dataset sources and frozen splits

`data/processed/prism_training.csv` is the reviewed working dataset. It keeps
the intentionally simple three-column structure `description,label,language`.
`data/semantic_group_review.csv` records reviewed multilingual grouping
overrides, while `data/major30_policy_cases.csv` contains the approved Major-30
boundary cases and their required evaluation splits. The JSON files under
`data/source/` remain scenario provenance and expansion material; they do not
replace the reviewed CSV used by the deployed model.

Verify the committed frozen splits without changing files:

```powershell
python ml/scripts/build_training_splits.py --check
```

After an approved change to `prism_training.csv`, rebuild with:

```powershell
python ml/scripts/build_training_splits.py --overwrite
```

The builder writes `train.jsonl`, `validation.jsonl`, `test.jsonl`, and
`label_map.json`. It preserves reviewed scenario groups across languages and
prevents the same fact pattern from leaking between training and evaluation.

## Reproducible pilot benchmark

The pilot trains only on `data/processed/train.jsonl`, evaluates on the frozen
validation split by default, and writes ignored experiment artifacts under
`ml/artifacts/`. The selected frozen artifacts are then consumed by the live
Laravel advisory path. The model never learns from offense titles, offense
types, sanctions, service hours, scenario IDs, or other target-revealing
metadata.

Current deployed 60-label statistical weights with policy guardrails
(`EF3F81DDE94D-2026-09-16-v11`):

- RoBERTa artifact: `ml/artifacts/roberta-prism-offense-v6` (frozen and reused)
- TF-IDF artifact: `ml/artifacts/tfidf-prism-offense-v7`
- Hybrid artifact: `ml/artifacts/hybrid-prism-offense-v7`

The v7 release retrains only the lightweight TF-IDF/SVM components after adding
targeted multilingual MINOR-07 eating/study-area examples. The RoBERTa v6
checkpoint is reused for inference only; it is not retrained. The v7 validation
run reports 95.18% accuracy, 95.67% macro-F1, and 99.60% top-3 recall on the
expanded split. These metrics are experimental and are not directly comparable
to the prior split because the dataset changed.

The deployable v7 bundle is kept in `ml/artifacts-deploy-v7/` for copying to a
server. It contains the v7 TF-IDF and hybrid weights plus the unchanged v6
RoBERTa model. Their
weight files are gitignored, so they must be copied into `ml/artifacts/` by
hand; `git pull` does not deliver them.

| Split | Top-1 | Macro-F1 | Top-3 |
|---|---:|---:|---:|
| Validation (v7 expanded split) | 95.18% | 95.67% | 99.60% |
| Reserved test | Not run for v7 | Not run for v7 | Not run for v7 |

The v5 weights were promoted on 2026-09-04 after the gap-resistance corpus
expansion (`reports/gap_resistance_retrain_2026-09-04.md`). They hold
validation accuracy flat with `-v3` (96.36%) and lift reserved-test top-3
recall by 1.2 points (97.98% vs 96.76%) at the cost of one row of
reserved-test accuracy (91.90% vs 92.31%). The decisive gain is on the
production regression and blind probe sets - the reported "forces a match"
and lay-vocabulary failures - which moved from 31/42 and 11/20 on `-v3` to
37/42 and 15/20. The bare word "gun" now ranks `MAJOR-25` at 0.40 (was 0.23)
without the policy guardrail firing. The `-v3` artifacts remain on disk as the
rollback fallback but are no longer the default loaded by `hybrid_inference.py`.

On both splits, `MAJOR-30` has 100% precision and recall. The explicit lawful
demonstration and petition cohorts all rank `NO_MATCH` first. These are pilot
results on synthetic/reviewed scenarios, not evidence for autonomous decisions.

The 2026-08-11 keyboard-boundary candidate was trained against dataset
fingerprint `73F71C1ADCE8`. It improved hybrid validation accuracy/macro-F1 to
95.95%/96.76%, but reserved-test accuracy/macro-F1 declined to 91.50%/91.56%
from 92.31%/92.30%. Its statistical weights were therefore not promoted. The
v4 evidence guardrail was promoted with the existing weights after passing all
nine keyboard and examination-phone regression cases. See
`reports/keyboard_boundary_retrain_2026-08-11.md`.

The 2026-08-15 v3 candidate was trained against dataset fingerprint
`B50B4F02BD04` after the realism-expansion pass documented in
`reports/dataset_realism_expansion_2026-08-15.md` (see that report for the
new training rows) and a guardrail hyphen-matching fix (`re-arranging` was
previously not recognized by the keyboard-rearrangement guardrail regex,
version bumped to `2026-08-15-v5`). It improved hybrid validation
accuracy/macro-F1 to 96.36%/97.45% while holding reserved-test accuracy flat
at 92.31% and edging reserved-test macro-F1 up to 92.42% (top-3 recall eased
slightly to 96.76% from 97.17%). All nine frozen keyboard and
examination-phone regression cases still pass. Its statistical weights were
promoted; the prior `-v2` artifacts remain on disk as a rollback fallback but
are no longer the default loaded by `hybrid_inference.py`. See
`reports/v3_promotion_2026-08-15.md`.

Install the small classical-baseline environment:

```powershell
python -m venv .venv-ml
.\.venv-ml\Scripts\Activate.ps1
python -m pip install -r ml/requirements-baseline.txt
```

Validate the dataset contract and run the TF-IDF baseline:

```powershell
python ml/scripts/build_training_splits.py --check
python -m unittest discover -s ml/tests -v
python ml/scripts/train_baseline.py
```

The baseline combines word and character TF-IDF features with balanced
logistic regression. Its run directory contains:

- `model.joblib` and `model_manifest.json`
- `metrics.json`
- `predictions.jsonl`
- `per_class.csv`
- `confusion_matrix.csv`
- `top_confusions.csv`

Every manifest records the dataset fingerprint and individual split hashes,
label mapping, hyperparameters, seed, runtime versions, training time, and the
number of independent scenario groups. It also hashes the run-defining source
and requirements files, summarizes the Git working state, and records the full
installed Python package environment.

The current pilot outcome and no-go deployment decision are documented in
`reports/pilot_validation.md`.

### Current keyword-engine comparison

Export the existing Laravel keyword engine's validation predictions without
changing cases or offense records, then score them with the same evaluator:

```powershell
php ml/scripts/export_keyword_predictions.php --split=validation
python ml/scripts/evaluate_predictions.py `
  --predictions ml/artifacts/keyword-validation/raw_predictions.jsonl `
  --eval-split validation `
  --run-name keyword-engine
```

The exporter uses the offense catalog in the configured Laravel database. Seed
the approved catalog first if it is empty. Keyword scores are ranking scores,
not calibrated probabilities. Its sidecar manifest hashes the catalog snapshot,
keyword-engine source, seeder, dependencies, dataset input, and predictions.

### Optional Hugging Face fine-tuning

Install the larger Transformer stack in the isolated environment:

```powershell
python -m pip install -r ml/requirements-transformer.txt
```

The training command defaults to `jcblaise/roberta-tagalog-base`, preserves
case, uses balanced cross-entropy, selects the best checkpoint by validation
macro-F1, reports top-3 recall and language slices, and saves a standard
Hugging Face model directory:

```powershell
python ml/scripts/train_transformer.py `
  --model-name jcblaise/roberta-tagalog-base `
  --revision <reviewed-commit-hash>
```

Downloads are disabled by default. Point `--model-name` to an approved local
model directory or add `--allow-download` only after network use and the model
license have been reviewed. Use separate run directories and identical seeds
to benchmark:

- `jcblaise/roberta-tagalog-base`
- `dost-asti/RoBERTa-tl-cased`
- `FacebookAI/xlm-roberta-base`

For the first pilot, the reviewed `jcblaise/roberta-tagalog-base` revision is
`7ab976a69b7cc338424ca08d228206e788756398`. Cache and train that immutable
revision with:

```powershell
$modelRevision = "7ab976a69b7cc338424ca08d228206e788756398"
.\.venv-ml\Scripts\hf.exe download jcblaise/roberta-tagalog-base `
  --revision $modelRevision `
  --cache-dir ml/cache/huggingface
.\.venv-ml\Scripts\python.exe ml/scripts/train_transformer.py `
  --model-name jcblaise/roberta-tagalog-base `
  --revision $modelRevision `
  --cache-dir ml/cache/huggingface
```

Re-run inference from the saved best model without retraining:

```powershell
.\.venv-ml\Scripts\python.exe ml/scripts/evaluate_transformer.py `
  --model-dir ml/artifacts/jcblaise-roberta-tagalog-base-validation-seed-42/model
```

This evaluation-only command verifies the saved model's label map and dataset
fingerprint and stores all 60 probabilities for every row, allowing log loss,
confidence calibration, and top-k results to be independently recomputed.

Do not select a model from accuracy alone. Compare validation macro-F1,
per-label precision/recall/F1, top-3 recall, confusion pairs, English/Tagalog/
Taglish slices, and confidence coverage. Repeat serious comparisons with
multiple seeds.

### Reserved test set

All pilot scripts refuse to evaluate `test.jsonl` unless
`--allow-test-evaluation` is supplied. Use validation data for model choice,
threshold selection, and error analysis. Touch the reserved test split only
for a documented final comparison after the dataset and model-selection
procedure have been frozen.

The current validation and test splits contain only one independent scenario
group per offense. Their metrics are useful for finding pipeline errors and
confusing labels, but they are not evidence that the classifier is ready for
disciplinary use.

## Modeling boundary

The language model should rank offense codes, not infer final sanctions.
Sanctions depend on confirmed prior-offense history, subtype/severity findings,
appeals, and officer judgment. Laravel's `SanctionRecommendationService`
remains the authoritative deterministic mapping after an officer confirms the
offense.

Use the offense code, rather than title text, as the stable integration key.
Some dataset titles retain the handbook's complete qualifier (notably
`MAJOR-33`, `MAJOR-44`, and `MAJOR-46`) instead of a shorter UI label.

The same boundary applies to `MAJOR-19`: Laravel should derive habitual
disregard from confirmed minor-offense counts. The corresponding synthetic
rows exercise that rule end to end and must not be used to teach RoBERTa to
guess a student's history from prose.

`MAJOR-30` uses the reviewed boundary in
`ml/data/major30_policy_definition.json`. It remains advisory: the model must
not infer criminal liability, political affiliation, guilt, or sanctions.

Most training examples are deliberately single-label so the supervision is
clear. Real cases may contain multiple offenses; those should be evaluated in a
separate multi-charge cohort or handled with an explicitly multi-label model.

### Run the frozen advisory classifier in Laravel

This uses the already-trained validation-frozen RoBERTa/TF-IDF/SVM ensemble. It
does not retrain the model. Start the localhost-only service from the project
root and keep the terminal open:

```powershell
.\.venv-ml\Scripts\python.exe ml\scripts\serve_hybrid.py
```

The first startup loads the 436 MB RoBERTa checkpoint and can take roughly
15 seconds on CPU. Check it with:

```powershell
Invoke-RestMethod http://127.0.0.1:8765/health
```

Apply Laravel migrations, then enable the client in `.env`:

```dotenv
PRISM_ML_CLASSIFIER_ENABLED=true
PRISM_ML_CLASSIFIER_URL=http://127.0.0.1:8765
```

Run `php artisan config:clear` after changing `.env`. If the service is stopped,
times out, or returns malformed output, Laravel continues the case workflow
with the existing keyword classifier and clearly labels it as a fallback.

Recommendations are stored in `offense_recommendation_audits` using the model
version, ranked offense codes and scores, a SHA-256 hash of the narrative, and
the officer's eventual selection. The narrative itself is not duplicated in
this audit table. The model never confirms an offense or recommends a sanction.

## Review requirement

These narratives are synthetic and contain no real student names, numbers, or
case records. Most are marked `draft_synthetic`; the new `MAJOR-30` boundary
cases are marked `approved_policy_boundary`. All must be reviewed by the Student Discipline
Office and the research adviser before they are treated as approved training
data. Reviewers should check:

1. Correct offense label and handbook meaning.
2. Natural English, Tagalog, and Taglish phrasing.
3. Local NU Clark plausibility.
4. Absence of personally identifiable information.
5. Clear separation from confusing neighboring offenses.
