# PRISM maintenance and system documentation

Last reviewed against the work tree: **2026-09-09**.

This folder explains how to operate PRISM and how its core rules work. It is for
maintainers, deployment operators, and project reviewers. These files are not
runtime dependencies, but should be maintained alongside the implementation.
Start with the [project README](../README.md) for project scope and quick setup,
or the [setup and operations guide](SETUP_AND_OPERATIONS.md) for full instructions.

## Choose a guide

| Task | Guide |
| --- | --- |
| Install PRISM, provision the administrator, and review operational commands | [Setup and operations](SETUP_AND_OPERATIONS.md) |
| Deploy updates, run scheduled tasks, investigate missing reminders | [Scheduler and queue deployment](SCHEDULER_QUEUE_DEPLOYMENT.md) |
| Configure notifications and investigate email failures | [Email configuration](EMAIL_CONFIGURATION.md) |
| Review access, uploads, retention, and backups | [Security and privacy controls](SECURITY_PRIVACY_CONTROLS.md) |
| Apply Registrar-confirmed student statuses and explain retention policy | [Student status updates and records retention](REGISTRAR_ROSTER_AND_RETENTION.md) |
| Explain or change score factors, weights, bands, and history | [Risk scoring methodology](RISK_SCORING_METHODOLOGY.md) |
| Maintain proactive support actions | [Risk intervention workflow](RISK_INTERVENTION_WORKFLOW.md) |
| Review the September 2026 cleanup evidence | [Historical cleanup review](CLEANUP_REVIEW_2026-09-09.md) |
| Prepare source and history for publication | [Public repository preparation](PUBLIC_REPOSITORY.md) |

Related subsystem guides:
[Windows deployment](../deploy/windows/README.md),
[advisory offense classifier](../ml/README.md),
[database diagrams](../tools/erd/README.md), and
[load testing](../tools/k6/README.md).

## Maintenance routine

1. Review each release, back up persistent data, and run relevant tests in development or staging.
2. Follow the scheduler guide to deploy, refresh caches, restart workers, and verify automation. Check recovery after a host reboot.
3. Review failed jobs and logs regularly; verify representative notification delivery in staging.
4. Follow the security guide for access reviews and restore drills. Health checks do not perform those procedures.

Run the backend suite from the project root with development dependencies installed:

```bash
php vendor/phpunit/phpunit/phpunit
```

The committed PHPUnit configuration selects SQLite in memory and the testing
environment. Verify local overrides first. For classifier changes, use the Python
environment described in `ml/README.md`, including these checks:

```bash
python -m pytest ml/tests -q
python ml/scripts/build_training_splits.py --check
```

## Keeping documentation accurate

- Update the relevant guide in the same change as its commands, rules, or workflow.
- Link to implementation and regression tests; distinguish examples from deployed settings.
- Keep secrets, tokens, and student records out of documentation.
- Record the review date. This is not a methodology version or proof of production deployment.
- Keep dated reviews as historical evidence: their test counts describe that review.
- Update repository links when renaming a guide.
