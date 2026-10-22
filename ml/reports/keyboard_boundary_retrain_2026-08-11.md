# Keyboard-boundary retraining report

Run date: 2026-08-11  
Decision: **Retain the deployed statistical weights; promote guardrail v4 and the expanded training corpus.**

## Trigger

The report “The student is seen rearranging the keyboard keys during rounds.”
was ranked as `MAJOR-15` (property damage) at 26.00%, although the narrative
does not state damage, intentional destruction, ownership, authorization, or a
computer-laboratory rule violation.

## Dataset change

- Added 27 training-only English, Tagalog, and Taglish narratives across nine
  independent boundary groups.
- Covered insufficient evidence/authorized handling (`NO_MATCH`), unauthorized
  nondamaging laboratory tampering (`MAJOR-45`), and confirmed intentional
  keyboard damage (`MAJOR-15`).
- Increased the model-ready training split from 1,219 to 1,246 rows.
- Preserved the 247-row reserved test split byte-for-byte at SHA-256
  `5F7F232065869033E3086FEB2F31CB283EEEB1F7750A684800AB5949F934C666`.
- New dataset fingerprint: `73F71C1ADCE897AB41A8FDB3A73689FE2860D6AC6AD009383BA1CD9256F9BB51`.

The new synthetic narratives are marked `pending_human_review`; they require
Student Discipline Office and adviser review before being treated as approved
research data.

## Candidate training

The pinned `jcblaise/roberta-tagalog-base` revision
`7ab976a69b7cc338424ca08d228206e788756398` was retrained with seed 42 and the
established balanced 12-epoch/early-stopping protocol. Epoch 7 was selected by
validation macro-F1. TF-IDF logistic regression and the linear-SVM hybrid
component were retrained on the same expanded training split.

| Model | Split | Accuracy | Macro-F1 | Top-3 recall |
|---|---|---:|---:|---:|
| Deployed hybrid | Validation | 94.74% | 95.37% | 99.60% |
| Retrained hybrid candidate | Validation | **95.95%** | **96.76%** | 99.60% |
| Deployed hybrid | Reserved test | **92.31%** | **92.30%** | 97.17% |
| Retrained hybrid candidate | Reserved test | 91.50% | 91.56% | **97.98%** |

The candidate improved validation and reserved-test top-3 recall, but it lost
reserved-test top-1 accuracy and macro-F1. More importantly, its raw ensemble
still ranked the triggering narrative as `MAJOR-15`, at 33.86%. The new
statistical weights were therefore rejected for deployment.

## Guardrail and regression gate

Guardrail `2026-08-11-v4` applies the handbook evidence boundary only when the
ensemble ranks keyboard rearrangement as `MAJOR-15`:

- explicit damage evidence keeps `MAJOR-15`;
- explicit unauthorized computer-laboratory policy facts route to `MAJOR-45`;
- otherwise the classifier abstains with `NO_MATCH`.

The guardrail recognizes explicit damage denial such as “no keys were damaged”
instead of treating the word “damaged” as positive evidence.

The retained deployed weights plus v4 passed all nine frozen regression cases:

- vague, laboratory-policy, and damaged-keyboard cases in English;
- the same three boundaries in Taglish;
- explicit examination-phone aid use;
- a powered-off phone that was never accessed; and
- permitted phone retrieval after an examination.

All 25 ML unit/contract tests passed. The classifier remains advisory and must
not confirm an offense or replace officer review.
