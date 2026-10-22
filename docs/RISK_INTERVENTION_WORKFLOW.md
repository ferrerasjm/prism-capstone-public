# PRISM proactive risk intervention workflow

Last reviewed: **2026-09-09**. [Documentation index](README.md)

Elevated rule-based scores prompt human review. Risk scoring does not itself
create sanctions, determine guilt, or start support actions. Formal disciplinary
decisions continue through the authorized case workflow.

## Record and follow up an action

1. An active Discipline Officer reviews a student's score and contributing factors.
2. A new action requires a current **High** or **Critical** score.
3. The officer selects an action type, enters a plan (20-5,000 characters), and selects a follow-up date of today or later.
4. The action starts **In Progress** immediately. The creating officer is assigned automatically; there is no separate assignee selection.
5. An open action can be rescheduled or closed as **Completed** or **Cancelled**. Closing requires an outcome/reason of 20-2,000 characters.
6. Closed actions cannot be edited or reopened through this workflow.

Action types are Risk Review, Student Support Meeting, Monitoring Plan, Referral
for Support Services, Compliance Follow-up, and Other Support Action. Recording
a support-action type does not itself create a formal guidance referral or sanction.

Only one open action of the same type is allowed per student. Different types
can be open together without an enforced sequence. An open action with a follow-up
date before today displays **Overdue**. An elevated student without an open action
displays **Needs Review** on the dashboard. These are display conditions, not
additional stored statuses.

| Current status | Allowed next status |
| --- | --- |
| In Progress | In Progress, Completed, Cancelled |
| Completed / Cancelled | None |

`Planned` belongs to the old workflow. September 2026 migrations converted
planned actions and changed the default to `in_progress`. Reintroducing it
would require coordinated service, UI, and migration changes.

## Decision history

Creation preserves the current score/band, configured methodology version, and
latest available score snapshot reference. It records the creating/assigned
officer, plan, and follow-up date. Creation and updates create audit entries;
closure records its time and actor. Later score changes do not rewrite the
original decision context or automatically close an action.

## Maintenance and verification

Change validation, transitions, labels, and migrations together. Verify
eligibility, duplicate prevention, dates, closure reasons, and closed-record
protection in a test environment:

```bash
php vendor/phpunit/phpunit/phpunit --filter RiskInterventionWorkflowTest
```

- [Service and transitions](../app/Services/RiskInterventionService.php)
- [Request validation](../app/Http/Controllers/Officer/RiskInterventionController.php)
- [Status and action labels](../app/Models/RiskIntervention.php)
- [Regression tests](../tests/Feature/RiskInterventionWorkflowTest.php)
- [Score methodology](RISK_SCORING_METHODOLOGY.md)
