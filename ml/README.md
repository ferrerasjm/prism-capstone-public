# Optional offense classifier

The classifier ranks candidate offenses in English, Tagalog, and Taglish. An officer confirms the classification; sanctions and risk scoring remain separate rule-based workflows.

## Data

The included corpus is documented as synthetic and aligned to the NU Clark Student Handbook 2025 Edition, sections VI.D?E. Review provenance, labels, and personal-data absence before operational use. Translations of a scenario must remain in the same split.

- `data/processed/prism_training.csv`: working descriptions and labels.
- `data/processed/{train,validation,test}.jsonl`: frozen splits.
- `data/source/`: scenario provenance; `data/major30_policy_definition.json`: Major-30 boundary.
- Major-19 is derived from history and excluded from narrative modeling; Major-22 is excluded as inapplicable to the campus.

## Development environment

From the project root, create a Python virtual environment and activate it:

```powershell
python -m venv .venv-ml
.\.venv-ml\Scripts\Activate.ps1
python -m pip install -r ml/requirements-baseline.txt
python ml/scripts/build_training_splits.py --check
python ml/scripts/train_baseline.py
```

On Linux/macOS, activate with `source .venv-ml/bin/activate`. Transformer work additionally requires `ml/requirements-transformer.txt`. Training entry points are in `ml/scripts/`; inspect their `--help` for model/revision arguments. Network downloads are opt-in. Use validation for model selection; reserved-test evaluation requires an explicit flag.

## Serve the hybrid model

Trained weights are excluded from Git. The baseline command above does not build the complete hybrid bundle. Supply compatible, reviewed artifacts at:

- `ml/artifacts/roberta-prism-offense-v6/model/`
- `ml/artifacts/tfidf-prism-offense-v7/model.joblib`
- `ml/artifacts/hybrid-prism-offense-v7/model.joblib`

Install `ml/requirements-transformer.txt`. Set `PRISM_ML_CLASSIFIER_TOKEN` in the service process environment to the same private value configured in Laravel, then run:

```bash
python ml/scripts/serve_hybrid.py
```

The service defaults to `127.0.0.1:8765`; `/health` reports readiness. In Laravel `.env`, set `PRISM_ML_CLASSIFIER_ENABLED=true`, `PRISM_ML_CLASSIFIER_URL=http://127.0.0.1:8765`, and the matching token; refresh configuration and restart workers.

Keep the service bound to localhost. If weights are absent or the service fails, Laravel uses keyword suggestions. Model scores are advisory ranking values, not calibrated probabilities of guilt. Audits record model metadata, ranked suggestions, a narrative hash, and officer selection.

See [application setup](../docs/SETUP.md) and `hybrid_inference.py` for the artifact-loading contract.

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


## Evaluation and reproducibility

The baseline writes model artifacts, a manifest, metrics, predictions, per-class results, confusion matrices, and top confusions under ignored `ml/artifacts/` run directories. Preserve the manifest, dataset/split fingerprints, model revision, label map, seed, hyperparameters, runtime versions, and evaluation split with each result.

Compare macro-F1, per-label precision/recall, top-3 recall, language slices, and confidence coverage alongside accuracy. Synthetic held-out scenarios do not establish performance on real disciplinary reports. Human review remains required; keep narrative text and operational records out of published experiment output.

After an approved dataset change, `python ml/scripts/build_training_splits.py --overwrite` regenerates splits; review differences and preserve cross-language scenario grouping. For routine integrity checks, use `--check`, which does not rewrite the files. Do not silently rebuild evaluation splits to improve reported results.

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


## Evaluation and reproducibility

The baseline writes model artifacts, a manifest, metrics, predictions, per-class results, confusion matrices, and top confusions under ignored `ml/artifacts/` run directories. Preserve the manifest, dataset/split fingerprints, model revision, label map, seed, hyperparameters, runtime versions, and evaluation split with each result.

Compare macro-F1, per-label precision/recall, top-3 recall, language slices, and confidence coverage alongside accuracy. Synthetic held-out scenarios do not establish performance on real disciplinary reports. Human review remains required; keep narrative text and operational records out of published experiment output.

After an approved dataset change, `python ml/scripts/build_training_splits.py --overwrite` regenerates splits; review differences and preserve cross-language scenario grouping. For routine integrity checks, use `--check`, which does not rewrite files. Do not silently rebuild evaluation splits to improve reported results.
