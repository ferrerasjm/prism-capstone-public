# Offense classifier pilot validation report

Run date: 2026-07-31
Decision: **Continue research; do not integrate this checkpoint into Laravel.**

## Frozen inputs

- Policy: `NU Clark Student Handbook 2025 Edition`
- Dataset fingerprint:
  `A5213F41BF10EA1F1649CE1CC2F307154345329851CEEB5718BF5B78D3DED1F0`
- Model-ready labels: 60, including `NO_MATCH`
- Training: 567 narratives / 189 independent scenario groups
- Validation: 189 narratives / 63 independent scenario groups
- Reserved test: not evaluated
- Tagalog RoBERTa base revision:
  `7ab976a69b7cc338424ca08d228206e788756398`

All current narratives are synthetic and marked `draft_synthetic`. Each
ordinary offense has only three independent training groups and one validation
group; English, Tagalog, and Taglish translations are not independent cases.

## Validation comparison

| Model | Accuracy | Macro-F1 | Top-3 recall | `NO_MATCH` recall |
|---|---:|---:|---:|---:|
| Current Laravel keyword engine | 38.62% | 37.97% | 41.27% | 66.67% |
| Word + character TF-IDF logistic regression | 48.68% | 46.76% | 75.66% | 25.00% |
| Tagalog RoBERTa (selected epoch 7) | **66.67%** | **63.49%** | **83.07%** | **0.00%** |

RoBERTa improved over TF-IDF by 17.99 percentage points in accuracy, 16.73
points in macro-F1, and 7.41 points in top-3 recall. It improved over the
keyword engine by 28.04, 25.52, and 41.80 points respectively.

The checkpoint was selected by validation macro-F1. Epoch 8 reached 84.13%
top-3 recall but a lower 62.94% macro-F1, so the trainer correctly restored
epoch 7.

## RoBERTa language slices

| Language | Accuracy | Macro-F1 | Top-3 recall |
|---|---:|---:|---:|
| English | 68.25% | 62.78% | 80.95% |
| Tagalog | 66.67% | 61.11% | 84.13% |
| Taglish | 65.08% | 59.28% | 84.13% |

The tokenizer audit found no truncation at 256 tokens. Maximum lengths were 78
tokens for English, 72 for Tagalog, and 73 for Taglish.

## Blocking findings

1. `NO_MATCH` precision, recall, and F1 were all zero. Every validation hard
   negative was forced into a handbook offense. A production system must be
   able to abstain.
2. Eleven labels had zero validation recall:
   `NO_MATCH`, `MINOR-03`, `MINOR-04`, `MINOR-05`, `MINOR-08`, `MAJOR-02`,
   `MAJOR-12`, `MAJOR-15`, `MAJOR-20`, `MAJOR-24`, and `MAJOR-36`.
3. The model is under-confident and not calibrated. Mean top-1 confidence was
   16.68% despite 66.67% accuracy; 10-bin expected calibration error was
   49.98%. The current validation set is too small for a defensible threshold.
4. Validation difficulty is badly skewed: 183 medium rows, three easy rows,
   and three hard rows. Difficulty-slice conclusions are therefore invalid.
5. Per-label validation support is normally one fact pattern rendered in
   three languages. Per-class scores can change completely from one new
   scenario.
6. This is one seed and one Transformer backbone. DOST-ASTI Tagalog RoBERTa
   and XLM-RoBERTa have not yet been run on the same frozen data.

Common full-group confusions included:

- `MAJOR-02` unjust enrichment/stealing → `MAJOR-03` unauthorized collection
- `MAJOR-15` property damage → `MAJOR-45` computer-laboratory policy
- `MAJOR-20` refusal to present ID → `MAJOR-07` lending/using another ID
- `MAJOR-24` intoxication → `MAJOR-38` prohibited-drug possession/use
- `MINOR-03` inappropriate attire → `MINOR-01` prescribed uniform
- `MINOR-04` inappropriate clothing graphics → `MINOR-12` artificial hair
  color

These pairs should receive independently written boundary cases, not simple
paraphrases of existing scenarios.

## Training record

- Python 3.13.0
- PyTorch 2.13.0 CPU
- Transformers 5.14.1
- Seed 42
- Eight scheduled epochs
- Learning rate `2e-5`
- Balanced cross-entropy
- Batch size 8
- Maximum length 256
- CPU training time: 2,338.64 seconds (38.98 minutes)

An audit during training found and corrected three pipeline issues for future
runs: test data can no longer select checkpoints, gradient accumulation greater
than one now receives correct loss scaling under Transformers 5, and saved
prediction artifacts now retain the complete 60-label distribution. None
changed this run's validation/GAS-1 model computation. The saved best model was
re-evaluated with the corrected audit path and reproduced the metrics above.

## Artifacts

- Keyword evaluation:
  `ml/artifacts/keyword-validation/evaluation/`
- TF-IDF evaluation and model:
  `ml/artifacts/tfidf-logreg-validation-seed-42/`
- RoBERTa training run and saved model:
  `ml/artifacts/roberta-tagalog-base-validation-seed-42/`
- Full-probability RoBERTa audit:
  `ml/artifacts/roberta-tagalog-base-validation-seed-42/audited-validation/`

Artifact directories are intentionally ignored by Git because they contain
large generated checkpoints. The audited metrics include dataset/model hashes,
the full installed Python environment, model-file hashes, and source-code
provenance.

## Next review gate

Before another serious training round:

1. Obtain SDO/adviser approval for labels and scenario wording.
2. Expand toward at least 14 independent training, three validation, and three
   test groups per label.
3. Add diverse `NO_MATCH`, insufficient-evidence, and manual-review cases.
4. Rebalance validation/test difficulty and source types by independent group.
5. Add targeted boundary cases for the confusion pairs above.
6. Freeze a new dataset fingerprint, then benchmark Tagalog RoBERTa,
   DOST-ASTI RoBERTa, XLM-RoBERTa, TF-IDF, and the keyword engine over multiple
   seeds.
7. Calibrate confidence and define abstention only after the expanded
   validation set is approved.

Only after that gate should the team evaluate the reserved test set and
consider a Laravel inference adapter.
