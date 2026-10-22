# Dataset realism-expansion pass

Run date: 2026-08-15
Scope: Dataset authoring only. No retraining, no changes to
`ml/scripts/hybrid_inference.py`, and no changes to the deployed artifact
directories (`ml/artifacts/roberta-prism-offense-v2`,
`ml/artifacts/tfidf-prism-offense-v2`, `ml/artifacts/hybrid-prism-offense-v2`).

## Trigger

A user report that "wearing flip-flops (sandals)" was ranked `MAJOR-12`
(direct assault) at high confidence. Root cause: `MINOR-03` already had 20
attire rows (slippers, slides, beach slippers, ripped jeans, etc.) but the
literal vocabulary "flip-flops" / "flip flops" (unhyphenated) / Tagalog
"tsinelas" never appeared anywhere in the corpus, so the model had no lexical
anchor for that specific term and defaulted to a spurious guess. (The second
reported failure, keyboard-keycap rearrangement, was already root-caused to a
hyphen bug in `ml/scripts/policy_guardrails.py` in this session and is not a
dataset problem; no additional keycap rows were added.)

The user also asked for a broader realism sweep: review other labels for
narrow/repetitive phrasing and add more varied, NU-Clark-plausible officer
report language (formal disciplinary register and informal/terse duty-log or
blotter register), in English, Tagalog, and Taglish, without inventing PII or
implying adjudicated guilt.

## Dataset change

- Added 57 new training-only English/Tagalog/Taglish narratives across 19
  independent scenario groups (3 rows per group).
- All new rows are marked `pending_human_review` and require Student
  Discipline Office and adviser review before being treated as approved
  training data, matching the mechanism used for the 2026-08-11
  keyboard-boundary rows.
- Grouping/split-forcing mechanism: rows and their `scenario_group_id` /
  `required_split: train` / `review_status` metadata were added to
  `ml/data/classification_boundary_cases.csv` (the same file, and the same
  mechanism, used for the keyboard-boundary cases), rather than
  `ml/data/semantic_group_review.csv`, which only records grouping overrides
  detected between existing reference validation/training rows and is not the
  mechanism for registering brand-new multilingual groups.
- Increased the reviewed working CSV from 1,760 to 1,817 rows and the
  model-ready training split from 1,246 to 1,303 rows.
- Validation and reserved test splits are byte-for-byte unchanged (247/247
  rows each); every new group was forced into `train`.
- New dataset fingerprint: `B50B4F02BD042D8787B73795A0E05E3A60CB10FE502DB957F18A7BF35CE21D3E`.

### Coverage added, by label

- `MINOR-03` (5 groups / 15 rows): explicit "flip-flops", unhyphenated
  "flip flops", and Tagalog "tsinelas" vocabulary, spanning a formal gate-
  inspection register, an informal guard-note register, and a PE/gymnasium
  fact pattern distinct from the existing hallway/classroom scenarios.
- `NO_MATCH` (3 groups / 9 rows): an authorized-medical-exception boundary
  case that deliberately mirrors the flip-flops fact pattern but with a clinic
  note and department-chair permission on file (paralleling the "vague vs.
  authorized vs. confirmed" three-way boundary structure used for the
  keyboard cases), a vague/unattributed report with no identifying details,
  and an ordinary compliant gate-check scenario.
- `MINOR-01` (2 groups / 6 rows), `MINOR-04` (1 group / 3 rows): terse
  duty-log phrasing to sharpen the boundary against `MINOR-03` (missing
  uniform vs. missing footwear vs. a graphic/slogan violation with compliant
  attire otherwise).
- `MAJOR-08` / `MAJOR-13` (1 group / 3 rows each): a disruption-only case and
  a verbal-confrontation-only case in terse incident-log register, to sharpen
  the boundary within the misconduct/provocation/brawl/assault cluster, which
  the existing corpus already covers with substantial narrative variety.
- `MAJOR-45` / `MAJOR-46` / `MAJOR-47` (1 group / 3 rows each): terse
  "lab log" / "IT ticket" register for the unauthorized-computer-lab-use,
  unauthorized-access, and security-breach cluster.
- `MAJOR-40` / `MAJOR-42` / `MAJOR-43` (1 group / 3 rows each): terse
  "duty log" / "incident report" / "chat log excerpt" register for the
  lewdness / lasciviousness / sexual-advance cluster.

### Realism gap targeted

Before this pass, nearly every row in the sampled corpus (~200+ rows read
across a dozen labels) was a full narrative sentence in the same rough
syntactic shape ("A/The student [verb phrase]..."). Real disciplinary intake
plausibly also includes terser register: guard/duty-log entries, blotter
lines, IT tickets, and chat-log excerpts. The new rows deliberately use that
terser register for the labels most likely to be semantically confusable
(attire/uniform boundary; misconduct/disruption/provocation; the immoral-acts
cluster; the computer-policy cluster), while keeping single-label, PII-free,
evidentiary (non-adjudicative) narratives consistent with the handbook.

## Verification

- `python ml/scripts/build_training_splits.py --check` passes against the
  regenerated splits.
- `python -m unittest discover -s ml/tests -v` passes all 25 tests. The one
  hardcoded row-count assertion in
  `ml/tests/test_pilot_common.py::FrozenDatasetContractTest` was updated from
  1,246 to 1,303 to match the new frozen training-split size, the same kind
  of update made for the prior keyboard-boundary expansion.
- `train.jsonl`, `validation.jsonl`, `test.jsonl`, and `label_map.json` were
  regenerated with `--overwrite`.

## Not done in this pass

No retraining or evaluation was performed; this task was dataset authoring
only. No accuracy/macro-F1 numbers are claimed for this change. The 60-label
catalog was not exhaustively swept — the following clusters were reviewed and
judged to already have adequate phrasing variety from the initial handbook
authoring pass and were left untouched: `MAJOR-01`–`MAJOR-07` (academic
integrity/records), `MAJOR-10`–`MAJOR-12` narrative register (already varied,
only `MAJOR-08`/`MAJOR-13` got a terse-register addition), `MAJOR-14`,
`MAJOR-17`–`MAJOR-39` except `MAJOR-40`/`MAJOR-42`/`MAJOR-43`, `MAJOR-41`,
`MAJOR-44`, and `MINOR-02`, `MINOR-05`–`MINOR-15` except `MINOR-01`. A future
pass should apply the same terse-register technique to those labels, and
ideally incorporate genuine adjudicated, de-identified historical narratives
once SDO review access is available, per the expansion goal already stated in
`ml/README.md` (at least 20 distinct fact patterns per label).
