# Corpus-gap experiment and retrain (not promoted)

Run date: 2026-09-02 to 2026-09-03
Outcome: **negative — no candidate promoted.** The deployed artifacts remain
`roberta-prism-offense-v3` / `tfidf-prism-offense-v3` / `hybrid-prism-offense-v3`.
The dataset, splits, and the split contract in `ml/tests/test_pilot_common.py`
were restored to their pre-experiment state (1,303 / 247 / 247).

## Trigger

A Discipline Officer entered the narrative "the student is bringing a gun" and
the recommendation ranked `MINOR-02` (failure to bring University ID) first at
25.8%, with `MAJOR-25` (deadly weapons) second at 12.6%.

## Root cause

Two independent corpus defects that aggregate accuracy did not surface:

1. **Lay-vocabulary gap.** The bare word "gun" appeared in **zero** of the 1,303
   training narratives. `MAJOR-25` rows used *handgun*, *firearm*, *baril*,
   *switchblade*, *machete*, *balisong* — every term except the one an officer
   under pressure is most likely to type. TF-IDF gave it no weight; RoBERTa had
   never seen it in context.
2. **Shortcut token.** `bring` / `bringing` occurred in exactly one label across
   the whole corpus — `MINOR-02`, "failure to bring University ID". The model
   therefore learned *bringing implies the ID offense*. Confirmation:
   "the student is bringing something" also ranked `MINOR-02`.

The narrative's only in-vocabulary content word pointed at `MINOR-02`, and "gun"
contributed nothing to counter it. Swapping one word fixed it:
"bringing a firearm" ranked `MAJOR-25` at 42.8%.

Held-out splits contained three narratives with "gun", all of which passed,
because each said *stun gun* or *pellet gun* and carried corroborating cues. No
evaluation row paired a lay weapon term with a competing shortcut token, and
`inference_regression_cases.json` had no `MAJOR-25` case at all.

## What shipped (independent of the retrain)

A deadly-weapon boundary rule in `ml/scripts/policy_guardrails.py`
(`GUARDRAIL_VERSION` 2026-09-02-v6), covering both directions:

- **Promotion** to `MAJOR-25` when an unexplained weapon term appears and the
  ranking is minor or unmatched. A major ranking is left to the officer.
- **Suppression** of a top-ranked `MAJOR-25` when every weapon term sits inside
  a benign context — non-weapon sense (*glue gun*, *gun control*), depiction
  (a printed graphic), or recorded absence (*found no weapon*).

Matching is by **span**, not presence, so "used a glue gun and also carried a
knife" still escalates on the knife while "used a glue gun" alone does not.

`ml/scripts/evaluate_hybrid.py` previously applied only the promotion rule.
It now applies suppression as well, so evaluation matches serving; every
number below was measured with identical guardrail code on both models.

## Dataset experiment

126 narratives across 42 scenario groups, authored as English / Tagalog /
Taglish triples sharing one `scenario_group_id` so translations of the same
facts cannot leak across splits. Added through
`ml/data/classification_boundary_cases.csv` with explicit `required_split`,
the same mechanism as the 2026-08-11 keyboard and 2026-08-15 realism passes.

| Cohort | Groups | Purpose |
|---|---:|---|
| `ordinary_activity_expansion` | 19 | Ordinary campus activity that is not an offense |
| `lay_weapon_vocabulary` | 13 | Bare "gun", knife, patalim, ice pick; benign weapon senses |
| `bring_token_debias` | 10 | "bringing" on MAJOR-14/23/26/38 and MINOR-15; MINOR-02 without "bring" |

Both defects closed at the data level: `bringing` spread from 1 label to 6, and
`gun` appeared for the first time.

## Results

Two candidates were trained with the v3 recipe unchanged (8 epochs, lr 2e-5,
batch 8, seed 42, `jcblaise/roberta-tagalog-base` @ 7ab976a6).

- **v5** — full corpus (splits 1,399 / 259 / 265)
- **v6** — targeted: the `ordinary_activity` cohort dropped after it failed to
  generalize (splits 1,354 / 253 / 259)

Because the splits changed, every comparison below is restricted to the
**247 original rows** both models share.

### Validation (247 original rows)

| Model | accuracy | top-3 | under-charges | over-charges |
|---|---:|---:|---:|---:|
| v3 (deployed) | **0.9636** | 0.9960 | 1 | 6 |
| v5 candidate | 0.9555 | 1.0000 | 4 | 3 |
| v6 candidate | 0.9555 | **1.0000** | 2 | 6 |

### Reserved test, v5 only (touched once)

| Model | original 247 | top-3 | new 18 |
|---|---:|---:|---:|
| v3 (deployed) | **0.9271** | 0.9717 | — |
| v5 candidate | 0.9190 | **1.0000** | 0.5000 |

v6 was never evaluated on the reserved test: it lost at validation, and
spending a one-shot resource on a losing candidate would compromise it.

### What the reserved test showed about the cohorts

The 18 new reserved-test rows split cleanly:

- **Offense-side cohorts: 9/9** on unseen phrasings — `MAJOR-14` "bringing an
  improvised explosive device", `MAJOR-25` "confiscated a gun", `MINOR-02`
  phrased without "bring". The vocabulary and de-biasing fixes generalize.
- **Ordinary-activity cohort: 0/9.** A plastic water gun ranked `MAJOR-25`,
  waiting at the designated pick-up area ranked `MAJOR-21` (restricted area),
  and presenting the ID when asked ranked `MAJOR-20` (refusal to present ID).

The same cohort scored 12/12 on validation. That gap is the lesson: the
validation rows shared phrasing with their training groups, so they measured
memorization rather than generalization.

## Why no candidate was promoted

1. Both candidates scored **0.9555** on the 247 original validation rows against
   v3's **0.9636** — identical to four decimals, from two independently trained
   models 45 training rows apart. The cost comes from perturbing the corpus at
   all, not from any one cohort. The hypothesis that `NO_MATCH` growth caused
   the regression is **falsified**.
2. v5 also lost the reserved test on the original rows (0.9190 vs 0.9271).
3. Precedent: the 2026-08-11 keyboard candidate was not promoted for a smaller
   regression.

Both candidates did win **top-3 recall** (1.0000 vs 0.9960 validation, 1.0000 vs
0.9717 reserved test). That is the metric closest to what the officer sees,
since the interface renders three cards, and is the strongest argument for
revisiting this with a larger corpus.

## Disposition

- Deployed model unchanged; the guardrail delivers the user-visible fix.
- The 126 authored rows are preserved, unused, in
  `ml/data/candidate_corpus_2026-09-02.csv` with a per-row `experiment_result`
  column, as the basis for a future pass.
- `ml/data/inference_regression_cases.json` grew from 9 to 18 cases, including
  bare-"gun" narratives and both over-escalation guards. The evaluator gained a
  `forbidden_label` field for cases where the correct runner-up is a judgement
  call.

## For a future attempt

- Ordinary-activity `NO_MATCH` coverage cannot be taught with 19 groups. Every
  innocent narrative sits directly adjacent to a specific offense — *presenting*
  an ID versus *refusing* to, a *permitted* waiting area versus a *restricted*
  one. It needs dense per-offense inverse coverage, not a generic cohort.
- Author held-out rows with deliberately different phrasing from their training
  groups, or validation will measure memorization, as it did here.
- The offense-side cohorts are ready to reuse: they generalized 9/9 and are the
  part of this experiment worth carrying forward.
