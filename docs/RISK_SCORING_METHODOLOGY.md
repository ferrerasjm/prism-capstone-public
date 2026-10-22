# Rule-based risk scoring

Scores support human review. They do not determine guilt or automatically assign sanctions. The optional ML offense classifier is separate and does not contribute model probabilities to these scores.

## Default factors

| Factor | Measurement | Cap | Weight |
| --- | --- | ---: | ---: |
| Confirmed offense count | Resolved cases with a confirmed offense; counts cases | 5 | 25% |
| Recent violation | Such a case resolved within six months | Boolean | 15% |
| Major severity | Confirmed resolved major cases | 3 | 20% |
| Escalation flag | Flagged case that is neither dismissed nor voided | Boolean | 15% |
| Unresolved cases | Open cases in the configured workflow statuses | 3 | 10% |
| Non-compliance | Non-compliant sanctions or adverse monitoring outcomes | 3 | 15% |

Count contribution: `min(value, cap) / cap * weight * 100`, where a 25% weight is `0.25`. Boolean factors contribute their full weight when triggered. The sum is rounded to two decimals. Except for recency, defaults have no term/lookback cutoff.

| Band | Score |
| --- | --- |
| Low | Below 26 |
| Moderate | 26 to below 51 |
| High | 51 to below 76 |
| Critical | 76 to 100 |

Trend compares with the prior score: a change above +2 is Increasing, below -2 is Decreasing, otherwise Stable. The first score has no previous trend.

## Configuration and recalculation

Defaults are in [config/risk_scoring.php](../config/risk_scoring.php); active database rules and weights determine actual calculations. At least one factor must be active and weights must total 100%. Administrators can configure sources, severity filters, windows, caps, and thresholds through the risk-weight workflow.

Workflow services recompute scores after relevant case/sanction changes. The scheduled `risk:compute` command handles time-dependent changes. Direct database edits bypass event handling. Snapshots retain score factors and decision context; term snapshots archive existing scores separately.

See [RiskScoringService](../app/Services/RiskScoringService.php) for exact queries and [support actions](RISK_INTERVENTION_WORKFLOW.md) for staff follow-up.
