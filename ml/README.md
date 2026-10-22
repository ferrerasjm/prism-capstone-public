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
