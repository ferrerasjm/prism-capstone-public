# PRISM

Student disciplinary case management for the NU Clark Discipline Office, built with Laravel.

PRISM brings incident reports, case decisions, student notices, sanctions, University Service, and guidance referrals into one workflow. Rule-based risk scores support staff review; an optional ML classifier suggests offenses for human confirmation.

## Features

- Incident reports, cases, notices, and student explanations
- UPCC hearings, decisions, appeals, and resolutions
- Sanction compliance, University Service attendance, and guidance referrals
- Risk dashboards, CSV/PDF reports, and scheduled reminders
- Role and record access checks, private evidence storage, and audit trails

## Stack

PHP 8.3+ ? Laravel 13 ? MySQL/MariaDB ? Blade ? Bootstrap 5 ? PHPUnit

## Setup

Follow the [setup guide](docs/SETUP.md) for prerequisites, database configuration, administrator creation, email, background workers, and production settings. The frontend requires no build step.

## Documentation

| Guide | Covers |
| --- | --- |
| [Setup](docs/SETUP.md) | Installation and daily operations |
| [Security and privacy](docs/SECURITY_PRIVACY_CONTROLS.md) | Access, private storage, backups, and retention |
| [Risk scoring](docs/RISK_SCORING_METHODOLOGY.md) | Factors, weights, and score bands |
| [Support actions](docs/RISK_INTERVENTION_WORKFLOW.md) | Staff review and follow-up workflow |
| [Optional classifier](ml/README.md) | Synthetic corpus and model service setup |
| [Windows deployment](deploy/windows/README.md) | Server installation and update scripts |
| [Database diagrams](tools/erd/README.md) | Schema and Workbench files |
| [Load testing](tools/k6/README.md) | Included k6 scripts |

## Public source

This repository starts with fresh history. Operational student/staff records, database exports, uploads, production configuration, and trained model artifacts are excluded. Contact details and sample accounts are placeholders. The classifier corpus is documented as synthetic; review data and policy mappings before operational use.
