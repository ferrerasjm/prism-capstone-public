# PRISM

Student disciplinary case management for the NU Clark Discipline Office, built with Laravel.

PRISM brings incident reports, case decisions, student notices, sanctions, University Service, and guidance referrals into one workflow. Its rule-based risk scores support staff review. A separate optional ML classifier suggests offenses for human confirmation.

## Features

- Incident reporting, individual cases, notices, and student explanations
- UPCC hearings, decisions, appeals, and resolutions
- Sanction compliance, University Service attendance, and guidance referrals
- Risk dashboards, CSV/PDF reports, and scheduled reminders
- Role and record access checks, private evidence storage, and audit trails

## Stack

PHP 8.3+ ? Laravel 13 ? MySQL/MariaDB ? Blade ? Bootstrap 5 ? PHPUnit

Browser authentication uses sessions; the mobile API uses JWT. The frontend requires no build step.

## Getting started

Install PHP, Composer, MySQL/MariaDB, and Git, then:

```bash
git clone https://github.com/ferrerasjm/prism-capstone-public.git
cd prism-capstone-public
composer install
cp .env.example .env
```

Create a local database and configure its `DB_*` settings in `.env`. Set private `PRISM_ADMIN_EMAIL` and `PRISM_ADMIN_PASSWORD` values to provision the initial administrator; use a unique password of at least 12 characters.

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Clear the administrator bootstrap settings after provisioning. Follow the [setup and operations guide](docs/SETUP_AND_OPERATIONS.md) for account activation, mail, queues, scheduling, and deployment.

## Documentation

- [Setup and operations](docs/SETUP_AND_OPERATIONS.md)
- [Documentation index](docs/README.md)
- [Risk scoring methodology](docs/RISK_SCORING_METHODOLOGY.md)
- [Security and privacy controls](docs/SECURITY_PRIVACY_CONTROLS.md)
- [Optional offense classifier](ml/README.md)

## Public source

This repository starts with fresh history and excludes operational student/staff records, database exports, uploads, production configuration, and trained model artifacts. The classifier corpus is documented as synthetic. Contact details and sample accounts are placeholders; configure approved contacts before deployment. See [public source preparation](docs/PUBLIC_REPOSITORY.md).
