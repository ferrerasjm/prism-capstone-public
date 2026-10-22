# PRISM rule-based risk scoring methodology

[Project documentation](../README.md)

PRISM computes a deterministic score from disciplinary records to support human
review and [proactive support](RISK_INTERVENTION_WORKFLOW.md). This component does
not use machine learning or determine guilt. The separate
[advisory offense classifier](../ml/README.md) does not contribute model
probabilities to the risk score.

## Formula and factors

Count factors: `contribution = min(raw value, cap) / cap * weight * 100`.
Boolean factors: `contribution = triggered ? weight * 100 : 0`.

The service sums unrounded contributions and rounds the composite to two decimal
places. Displayed contributions are individually rounded, so their displayed
sum can differ slightly from the composite.

| Factor | Current implementation | Cap | Default weight |
| --- | --- | ---: | ---: |
| Total confirmed offense count | Resolved cases containing at least one confirmed case offense; counts cases, not individual offense rows. | 5 | 25% |
| Recent confirmed violation | At least one such case has `resolved_at >= now()->subMonths(6)`. | Boolean | 15% |
| Major offense severity | Confirmed resolved cases with `case_type = major`. | 3 | 20% |
| Escalation flag | At least one escalation-flagged case that is neither dismissed nor voided. | Boolean | 15% |
| Unresolved cases | Cases in `open`, `pending_nte`, `awaiting_explanation`, `under_review`, `pending_upcc`, or `pending_resolution`. | 3 | 10% |
| Sanction non-compliance | Sanctions marked `non_compliant`, or evaluation monitoring sanctions with `assessment_outcome = needs_further_intervention`. | 3 | 15% |

Except for recency, these queries have no academic-term or lookback cutoff.
Dismissed/voided cases do not count as confirmed resolved cases or active
escalation flags. Pending cases can contribute to both unresolved cases and
the escalation factor when flagged.

Defaults are in [config/risk_scoring.php](../config/risk_scoring.php). Calculations
use active `risk_factor_weights` records, including saved rules, weights and caps.
At least one factor must be active and weights must total `1.0000` within the
service's validation tolerance; otherwise computation fails. Defaults are not
proof of the values deployed.

## Administrator-defined rules

Super Admins can create new factors with their own names and conditions. Each
factor stores a rule describing the records to count: confirmed resolved cases,
unresolved cases, escalated cases, or non-compliant sanctions (including adverse
evaluation findings). Case rules can select any, minor, or major severity.
An optional 1–120 month window uses resolution dates for confirmed cases and
creation dates for other sources; its lower and upper boundaries are inclusive.
Blank means all time. Sanction rules cannot filter case severity.

For count factors, matching records contribute proportionally up to a cap of
1–20. For boolean factors, the matching count must reach an administrator-set
threshold of 1–1000. For example, confirmed major cases within six months with
a threshold of two is evaluated directly from that saved rule.

Rules cannot access arbitrary tables or execute expressions. New data sources,
such as attendance, require a separately implemented integration. Existing
factors are migrated to equivalent rules. Rule definitions are included in
score snapshots and audit records, preserving the meaning of historical scores.
New factors start at zero points in a balanced model; assign points using the
model-wide editor. Archiving redistributes points proportionally; restoring
starts at zero points. The last active factor cannot be archived.

Example with default weights: one confirmed resolved minor case older than six
months and no other indicators scores `1 / 5 * 0.25 * 100 = 5.00` (Low).

## Bands and trend

Bands use descending minimum thresholds against the rounded composite:

| Band | Score interval |
| --- | --- |
| Low | `0 <= score < 26` |
| Moderate | `26 <= score < 51` |
| High | `51 <= score < 76` |
| Critical | `76 <= score <= 100` under valid weights |

Thus 25.99 is Low and 50.99 is Moderate. Trend compares with the previous
composite: greater than +2 is Increasing, below -2 is Decreasing, otherwise Stable.
The first computation has no prior trend.

## Recalculation and history

Workflow services invoke recomputation for case and sanction changes, including
creation, escalation, resolution, dismissal, voiding, and compliance changes.
The daily `risk:compute` sweep processes student accounts with cases and handles
time-dependent changes such as a violation leaving the six-month window.

Each computation writes an event snapshot with current/previous values, factor
details and changes, trigger, methodology version, and actor when supplied.
Term snapshots archive existing scores separately; they do not recompute them or
contain event-level before/after changes. Direct database edits bypass application
event handling and must not be assumed to refresh scores.

## Maintaining the methodology

1. Review the service, configuration, active rules, weights, and seeders before changing definitions. Preserve at least one active factor and the total-weight invariant.
2. Update affected tests and this guide together. Administrative weight changes use the application workflow and audit trail.
3. Explicitly decide versioning, migration, and recomputation behavior for future methodology changes. The code still declares `1.0`; version bumps are not automatically enforced.
4. Verify boundary scores, caps, pending/escalated cases, adverse monitoring outcomes, and history in a test environment.

```bash
php vendor/phpunit/phpunit/phpunit --filter RiskScoring
php vendor/phpunit/phpunit/phpunit --filter RiskRecomputationEventCoverageTest
```

- [Scoring implementation](../app/Services/RiskScoringService.php)
- [Weight administration](../app/Http/Controllers/Admin/RiskWeightController.php)
- [Methodology tests](../tests/Feature/RiskScoringMethodologyTest.php)
- [History tests](../tests/Feature/RiskScoringHistoryTest.php)
- [Event coverage tests](../tests/Feature/RiskRecomputationEventCoverageTest.php)
