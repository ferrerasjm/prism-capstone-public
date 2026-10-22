# PRISM Capstone

This public source snapshot starts with fresh Git history. It contains no operational student/staff rosters, case database, uploaded evidence, or production configuration. The optional classifier corpus is documented as synthetic in `ml/README.md`. Contact addresses and sample accounts are placeholders; configure institution-approved privacy contacts before deployment. See [public source preparation](docs/PUBLIC_REPOSITORY.md).

**Proactive Risk-Informed Student Disciplinary Case Management Web Application with Rule-Based Predictive Analytics for NU Clark Discipline Office**

PRISM is a Laravel-based disciplinary case management system designed for the NU Clark Discipline Office. It centralizes incident reporting, case processing, student notices, UPCC workflows, sanctions, guidance referrals, University Service tracking, reporting, and rule-based risk analytics.

PRISM’s predictive analytics are not machine learning. The risk component is a transparent, deterministic, rule-based scoring model with configurable weights, documented in `docs/RISK_SCORING_METHODOLOGY.md`, that helps authorized Discipline Office personnel identify students who may need timely review or support.

Separately, PRISM includes an advisory offense classifier (`ml/`) that suggests candidate offense codes from an incident narrative to speed up encoding. It is advisory only: suggestions are always confirmed by a human before a case is classified, every recommendation is written to an audit trail, the classifier never contributes to risk scores, and if the service is disabled or unavailable the system falls back to deterministic keyword matching.

## Project scope

PRISM supports the following major workflows:

- incident reports from faculty, security, students, and direct Discipline Office encoding;
- conversion of reports into disciplinary cases;
- offense classification based on the offense catalog;
- Notice to Explain (NTE) issuance, correction, reminders, and overdue tracking;
- student explanation submission through the portal;
- case evaluation, sanction category confirmation, and resolution;
- Notice of Formative Intervention (NFI);
- UPCC scheduling, decision recording, appeal submission, reconsideration, and finalization;
- University Service assignment and attendance tracking;
- guidance referral acknowledgment and outcome tracking;
- risk score computation, history snapshots, risk dashboard, and intervention workflow;
- CSV/PDF reports, monthly report archiving, escalation reports, and risk analytics reports;
- audit logs, privacy checks, scheduler checks, and deployment readiness commands.

## Technology stack

| Area | Stack |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Database | MySQL/MariaDB for local/production, SQLite for automated tests |
| Frontend assets | Server-rendered Blade templates; Bootstrap 5 via CDN with hand-written CSS and JS in `public/` |
| Authentication | Laravel session auth, JWT auth for API/mobile foundation |
| Reports | CSV generation, PDF exports through DomPDF |
| QR/TOTP | `simplesoftwareio/simple-qrcode`, `spomky-labs/otphp` |
| Testing | PHPUnit, Laravel feature/unit tests |
| Queues/scheduler | Laravel database queue and scheduler |

## Repository documentation map

Start with the [maintenance documentation index](docs/README.md) for operational
guides, verification commands, and implementation references.

- `README.md` — installation, deployment, roles, data dictionary overview, and operations guide.
- `docs/OFFENSE_POLICY_MANAGEMENT.md` — offense catalog, sanction recommendation, and escalation setting controls.
- `docs/RISK_SCORING_METHODOLOGY.md` — scoring formula, factors, caps, thresholds, and governance.
- `docs/RISK_INTERVENTION_WORKFLOW.md` — proactive support workflow for High/Critical students.
- `docs/SCHEDULER_QUEUE_DEPLOYMENT.md` — scheduler, queue worker, and automation deployment guide.
- `docs/SECURITY_PRIVACY_CONTROLS.md` — retention, access review, backups, malware scanning, and privacy controls.
- [Email configuration](docs/EMAIL_CONFIGURATION.md) — SMTP setup, delivery verification, and troubleshooting.

## Requirements

Install these before setup:

- PHP 8.3 or higher
- Composer
- MySQL or MariaDB
- Git
- PHP extensions commonly required by Laravel, including `pdo`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, and `ctype`

For PDF export, make sure PHP can write to `storage/` and `bootstrap/cache/`.

## Local installation

Clone the repository:

```bash
git clone <repository-url> prism-capstone
cd prism-capstone
```

Install PHP dependencies:

```bash
composer install
```

There is no JS build step — the frontend is server-rendered Blade with
Bootstrap loaded from a CDN, so `npm install`/`npm run build` are not
part of setup. `concurrently` in `package.json` exists only to run
`composer dev` locally; it is not required otherwise.

Create the environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure `.env` for a local MySQL database:

```env
APP_NAME=PRISM
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prism
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

Start the app:

```bash
php artisan serve
```

For active local automation testing, use separate terminals:

```bash
php artisan schedule:work
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Composer shortcuts are available:

```bash
composer scheduler
composer worker
composer ops:check
```

## Account activation and bulk imports

Accounts created through User Management do not receive temporary passwords.
PRISM stores a hash of a single-use setup token and queues an activation email
containing the corresponding link. The link expires after 72 hours by default,
is invalidated when a replacement is issued, and is consumed when the user
creates a password.

Bulk student CSV files must include each student's actual
`@students.nu-clark.edu.ph` email address. PRISM queues one encrypted activation
email job for every successfully created account; skipped and invalid rows do
not send email. Keep a queue worker running when `QUEUE_CONNECTION=database`:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

The setup-link lifetime can be adjusted with
`AUTH_ACCOUNT_SETUP_EXPIRE` in minutes.

## Initial administrator and reference data

There are no shared login credentials in this repository. Before running the
seeders, set `PRISM_ADMIN_EMAIL` and `PRISM_ADMIN_PASSWORD` in your private `.env`.
Use an authorized email and a unique password of at least 12 characters.

```bash
php artisan config:clear
php artisan migrate --seed
```

The administrator seeder creates an account only when both settings are present;
it never resets an existing account's password. Clear the bootstrap settings and
refresh the configuration cache after provisioning. Create staff, student, and
mobile supervisor accounts through User Management and the account activation
workflow. Deployments never create or reset a demo supervisor account.

Offense, sanction, and risk-policy seeders remain available. The handbook PDF is
kept private; an authorized operator can supply it at
`database/seeders/assets/NU-Clark-Student-Handbook-2025.pdf` before seeding, or
upload the appropriate handbook through the application. The optional handbook
seeder skips a missing PDF.

Local roster/demo seeders and the internal demo runbook are excluded from Git.
Do not run those local files against production accounts.

Keep the real University Service contact directory in the ignored private file
`storage/app/private/university-service-contacts.php`. It may return the original
`university_service.php` configuration or an office-to-contacts array; private
offices override the public fallback directory while preserving their form keys.
`PRISM_US_CONTACTS_FILE` can point to a different absolute private path. Preserve
this file when updating an existing installation, then rebuild the config cache.
For an older installation, preserve its original data-only directory before
installing this loader; do not copy the new loader into its own private input.
Existing assignments retain their stored contact snapshots, and active supervisor
accounts continue to take priority over the fallback contact directory.

## Role access overview

| Role | Main permissions |
| --- | --- |
| Super Admin | Manage users, import students, configure risk factor weights |
| Discipline Coordinator | Review escalated/UPCC cases, manage void approvals, view audit logs, generate reports |
| Discipline Officer | Review reports, create/convert cases, issue NTE/NFI, resolve cases, manage risk interventions |
| Guidance Officer | Acknowledge referrals, record confidential guidance outcomes |
| Reporting User | Submit and track own faculty/security reports |
| US Supervisor | Track University Service assignments and attendance |
| Student | View own cases, submit explanations, file UPCC appeals, view sanctions and University Service obligations |

## Core data dictionary overview

| Table/group | Purpose |
| --- | --- |
| `users`, `student_profiles` | Accounts, roles, student demographic/profile data |
| `incident_reports`, `incident_report_subjects`, `incident_attachments` | Initial reports, involved students, and private uploaded evidence |
| `cases`, `case_offenses`, `case_notices`, `case_explanations` | Main disciplinary case workflow, charges, NTE/NFI notices, and student explanations |
| `offense_catalog`, `sanction_matrix` | Handbook-linked offense definitions and recommended intervention categories |
| `case_resolutions`, `sanctions` | Final case resolution and assigned disciplinary interventions |
| `upcc_referrals`, `upcc_appeal_attachments` | UPCC conference, decision, appeal, reconsideration, and appeal attachments |
| `us_assignments`, `us_attendance_logs` | University Service assignment, QR/TOTP support, rendered hours, no-shows |
| `counseling_referrals` | Guidance referral status, acknowledgment, and confidential completion result |
| `risk_scores`, `risk_score_snapshots`, `risk_factor_weights`, `risk_interventions` | Rule-based risk model, score history, configurable weights, and support actions |
| `scheduled_reports` | Generated CSV/PDF report archive metadata |
| `audit_logs` | Append-only audit trail for sensitive actions |
| `number_sequences` | Concurrency-safe yearly report/case number allocation |
| `notifications` | Student/staff operational notifications |
| `jobs`, `failed_jobs` | Database queue processing |

## Risk methodology

PRISM computes a composite score from six documented, explainable factors:

- total confirmed offense count;
- recent confirmed violation;
- major offense severity;
- escalation flag;
- unresolved cases;
- sanction non-compliance.

Weights must total exactly 100%. Scores are grouped into Low, Moderate, High, and Critical bands. Risk scoring is used to support human review only; it does not determine guilt or automatically assign sanctions.

Read the full methodology in `docs/RISK_SCORING_METHODOLOGY.md`.

## Reports

PRISM includes the following report pages and exports:

- monthly violation summary;
- case summary;
- student case report;
- University Service compliance;
- UPCC cases;
- registration holds;
- habitual/escalation report;
- risk analytics report.

Reports can be exported as CSV/PDF where supported. Scheduled monthly violation archives are stored through the scheduler.

## Scheduler and queue

The scheduler is defined in `routes/console.php`.

Important scheduled commands:

- `reports:archive-monthly`
- `cases:finalize-expired-appeals`
- `risk:compute`
- `us:flag-overdue`
- `cases:process-nte-deadlines`
- `prism:automation-check`
- `prism:privacy-check`

Check scheduler registration:

```bash
php artisan schedule:list
```

Run automation health checks:

```bash
php artisan prism:automation-check
php artisan prism:privacy-check
```

See `docs/SCHEDULER_QUEUE_DEPLOYMENT.md` for production scheduler and worker setup.

## Security and privacy controls

PRISM uses:

- role-based access control;
- private local storage for attachments and generated reports;
- SHA-256 hashes and optional malware scanning metadata for uploads;
- encrypted Laravel secrets for sensitive generated values;
- append-only audit logs;
- configurable retention values;
- access-review and privacy health checks;
- backup/restore documentation.

Important production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=Asia/Manila
PRISM_SCAN_UPLOADS=true
PRISM_LAST_RESTORE_TEST_AT=YYYY-MM-DD
```

See `docs/SECURITY_PRIVACY_CONTROLS.md` for the complete procedure.

## Testing

Run all tests:

```bash
php artisan test
```

Or through Composer:

```bash
composer test
```

Run formatter:

```bash
vendor/bin/pint --dirty
```

The test suite covers:

- account/profile update boundaries;
- password reset workflow;
- case classification, NTE, NFI, resolution, and lifecycle flow;
- UPCC scheduling, appeal, reconsideration, and finalization;
- risk methodology, history snapshots, recomputation events, and interventions;
- reports and PDF/CSV exports;
- monthly report archiving;
- scheduler and automation health checks;
- security/privacy controls;
- authorization boundaries;
- concurrency-safe numbering.

## Production deployment checklist

On a production server:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set the scheduler cron on Linux:

```cron
* * * * * cd /path/to/prism-capstone && php artisan schedule:run >> /dev/null 2>&1
```

Run the queue worker with a process manager:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=120
```

After deployment:

```bash
php artisan schedule:list
composer ops:check
php artisan queue:failed
```

## Backup and restore

Back up both:

- the database;
- `storage/app`, including attachments and generated report files.

Recommended minimum:

- daily database backups;
- daily or incremental private file backups;
- restore drill every 90 days.

After a successful restore drill, set:

```env
PRISM_LAST_RESTORE_TEST_AT=YYYY-MM-DD
```

Then run:

```bash
php artisan prism:privacy-check --fail-on-warning
```

## Troubleshooting

If pages do not reflect new routes or settings:

```bash
php artisan optimize:clear
```

If emails are not being received locally, check:

```bash
storage/logs/laravel.log
```

If scheduled reminders or archives do not run:

```bash
php artisan schedule:list
php artisan schedule:work
tail -n 100 storage/logs/scheduler.log
```

If queued work does not process:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
php artisan queue:failed
```

If PDF/report files cannot be downloaded, confirm that `storage/` is writable and that the file exists under the private local disk.

## Capstone statement

PRISM is designed to help the NU Clark Discipline Office move from fragmented manual tracking to a centralized, auditable, proactive case management workflow. Its rule-based risk analytics are transparent and explainable, allowing the office to identify students who may need timely attention while preserving human judgment, due process, and official disciplinary procedures.
