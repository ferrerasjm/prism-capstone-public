# Operations and policy administration

Use this guide after completing [setup](SETUP.md). It covers policy editing, Registrar-confirmed status changes, background work, and updates. The scheduler invokes deadline/reporting commands; a separate queue worker processes queued work such as email.

## Offense and policy management

The Super Admin uses **Offense & Policy** in the Admin sidebar to maintain the offense catalog, categories and interventions, major-offense sanction recommendations, and habitual minor-offense thresholds. Every change requires a reason and is recorded in the audit log. The Discipline Coordinator can review cases but cannot change these policy settings.

The page places Offense Catalog, Minor Escalation, Categories & Interventions, and Sanction Matrix in tabs below the summary counts. The selected tab stays in the URL so pagination and validation return to the same section.

### Offense catalog

- Add minor or major offenses with a unique code, title, description, optional handbook reference, and search keywords. A code must begin with `MINOR-` or `MAJOR-` to match its type.
- A new minor offense is active immediately. A new major offense starts as a draft until its first-offense sanction recommendation is entered and the offense is activated.
- Edit an offense only before it is linked to a case. Once used, create a replacement code and archive the older offense to preserve historical case wording. Archived offenses stay on existing case records but cannot be selected for new charges or returned as classification suggestions.
- `MAJOR-19` cannot be archived or changed to a different code/type because the habitual-disregard workflow depends on it.
- New catalog entries can be selected manually and used by keyword matching. The ML classifier must be retrained separately before it can predict a new code.

### Sanction recommendations

The page lists recommendations with their occurrence count, category, and University Service hour range. Add or edit a row to change officer guidance. Archiving preserves the row and audit history while excluding it from new case recommendations; saving an archived row restores it. An active major offense must retain an active first-offense recommendation.

For major offenses, enter the intervention category by offense count, with optional University Service hour ranges and guidance. At a later repeat count without an exact row, the system uses the highest configured count **at or below** the actual count. These rows recommend sanctions to staff; they do not impose them automatically.

Officers and coordinators read active matrix rows for current case guidance. An open case without an assigned category uses the current recommendation; a category already assigned to a case remains a recorded decision. Archiving a repeat row makes later occurrences fall back to the nearest earlier active row.

### Offense categories

The five handbook category numbers remain fixed because case resolution uses their workflows. Admins can edit the intervention name and description, archive an unused category, or restore an archived category from its row. A category used by an active recommendation or open assigned case cannot be archived. Only active categories can be chosen for new recommendations and case decisions.

### Minor escalation

The same-type and different-type thresholds are stored in `discipline_policy_settings`, initially 3 and 4. Changes take effect on the next case evaluation and in student/staff progress displays. Existing decisions and audit records are not rewritten. Record a reason for each change; the existing policy reference remains stored.

### Deployment

Run `php artisan migrate --force` and refresh Laravel's configuration, route, and view caches. Existing catalog and matrix records are preserved. The catalog and matrix seeders now seed only empty tables, so rerunning `db:seed` cannot replace approved edits. Do not run policy seeders to update an established catalog; use the management page and record the reason.

## Student status updates

1. Request a Registrar-confirmed CSV containing `student_number,student_status`. Supported statuses: enrolled, graduated, transferred, withdrawn, not_enrolled. Missing students are never inferred to have left.
2. Open User Management > Update Student Statuses. Select the period, effective date and Registrar source. Upload the file to preview only.
3. Review Will Update, Unchanged and Needs Checking counts. Unknown accounts and outdated effective dates are skipped. Malformed files are rejected.
4. Confirm once to apply valid listed students. Access becomes inactive for departures; re-enrollment restores Active or Pending Setup. Separate inactive access restrictions are preserved, even across departure and re-enrollment.
5. For one student, open their account > Update Student Status and use the same preview and confirmation process.
6. Create new accounts through Bulk Student Import; status updates do not create accounts.

Existing school statuses start as Not Yet Confirmed; access states are not backfilled into school statuses. Every changed account and applied batch is audited with the source and effective date. Confirmation is transactional and idempotent; changed accounts invalidate a stale preview without partial application. A newer confirmed effective date cannot be overwritten by an older list.

Raw uploaded CSV files are not retained; proposed/applied rows are retained with the batch.


## Scheduled tasks

Source: [routes/console.php](../routes/console.php). Times use the configured
application timezone, normally `Asia/Manila`.

| Frequency / time | Command | Purpose |
| --- | --- | --- |
| Daily, 00:20 | `reports:archive-monthly` | Archive the previous month; skip an existing archive and catch up after a missed run. |
| Daily, 00:05 | `cases:finalize-expired-appeals` | Finalize eligible decisions after appeal windows expire. |
| Daily, 00:10 | `academic-periods:notify-started` | Send academic-period start notifications. |
| Daily, 01:15 | `sanctions:close-monitoring-periods` | Complete elapsed periods without new cases; flag others for officer assessment. |
| Daily, 01:20 | `sanctions:close-probation-periods` | Complete elapsed clean probation periods; flag breaches for officer review. |
| Daily, 05:00 | `risk:compute` | Recompute risk for student accounts with cases. |
| Daily, 06:00 | `us:flag-overdue` | Flag overdue University Service assignments. |
| Daily, 06:15 | `cases:process-nte-deadlines` | Send NTE reminders and flag overdue responses. |
| Daily, 06:45 | `prism:automation-check` | Report selected queue and automation warning conditions. |
| Monthly, day 1 at 06:50 | `prism:privacy-check` | Report selected security and privacy warning conditions. |
| Every five minutes | `sanctions:send-intervention-reminders` | Send due sanction/intervention reminders. |

All entries prevent overlap and use a single-server lock. Most append output to
`storage/logs/scheduler.log`; the five-minute intervention reminders use
`storage/logs/scheduler-reminders.log`. Multiple hosts must share the cache backend
used for these locks. Check registration with `php artisan schedule:list`.

## Queue settings and supervision

Example production settings; preserve existing secrets and database settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Manila
QUEUE_CONNECTION=database
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=180
QUEUE_FAILED_DRIVER=database-uuids
CACHE_STORE=database
SESSION_DRIVER=database
```

Worker command:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=120
```

The 120-second timeout is shorter than the 180-second retry interval, reducing
the risk of retrying work while an earlier attempt still runs. Laravel timeout
enforcement requires PCNTL; on native Windows, do not assume this option terminates
a hung job. Monitor processes and configure process recovery and appropriate I/O
timeouts.

**Existing settings to reconcile:** `.env.example` currently uses a 120-second
retry interval while Composer and Windows launchers use a 120-second timeout.
This guide's example corrects that pairing; documentation does not change the
host configuration. Set the retry interval on the host, rebuild cached
configuration, and restart workers.

For Linux, invoke the scheduler every minute through cron:

```cron
* * * * * cd /path/to/prism-capstone && php artisan schedule:run >> /dev/null 2>&1
```

Supervise the worker using systemd, Supervisor, or the hosting process manager.
Set the project working directory and an account with database/storage access;
configure restart after exit and reboot.

For Windows, use the [host guide](../deploy/windows/README.md) and verify its
Task Scheduler entries and executable paths. For local development, keep these
in separate terminals:

```bash
php artisan schedule:work
php artisan queue:work database --sleep=3 --tries=3 --timeout=120
```

`composer scheduler` and `composer worker` provide shortcuts with the same
timeout considerations. The `sync` queue is a development or temporary fallback
that runs work during requests; the production health check warns about it.
The `null` driver discards queued work.

## Deployment and updates

Review the release and migrations, back up the database and persistent storage,
and coordinate downtime when changes are incompatible with running requests/jobs.
Preserve the existing application key. On an existing configured installation,
apply the reviewed release, then run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Run `php artisan storage:link` if the public storage link is absent.
`queue:restart` requests a graceful exit; the process manager must launch its
replacement. Restart a long-running `schedule:work` process after schedule or
code changes.

PRISM serves Blade templates and assets from `public/`; there is no npm build
script. Node dependencies support only the optional local `composer dev`
runner. Do not run `npm run build` during deployment. Classifier deployment
has separate requirements in [ml/README.md](../ml/README.md).

## Verification and troubleshooting

```bash
php artisan schedule:list
php artisan queue:failed
php artisan prism:automation-check --fail-on-warning
php artisan prism:privacy-check --fail-on-warning
```

`composer ops:check` runs both checks without the failure flag. Without that
flag, reported warnings do not result in a failing exit status.

| Symptom | Checks and next step |
| --- | --- |
| Deadlines/reminders not processed | Inspect scheduler process/task history, timezone, and scheduler log. Registration alone does not prove execution. |
| Email queued but not delivered | Verify worker connection/queue, failed jobs, and [mail and activation setup](SETUP.md#6-configure-optional-services). |
| Old behavior after deployment | Refresh config/route/view caches and restart long-running processes. |
| Failed job | Inspect logs, fix the cause, then retry the specific reviewed job with `php artisan queue:retry JOB_ID`. Check whether its original attempt already produced side effects. |
| Release must be reverted | Coordinate the prior application release with database compatibility and backed-up storage; do not blindly roll back live migrations. |

Read the scheduler log with `tail -n 100 storage/logs/scheduler.log` on Linux or
`Get-Content storage/logs/scheduler.log -Tail 100` in PowerShell.

The automation check inspects queue tables/counts, unprocessed overdue NTE/US
records, and the previous monthly archive record after day 1. It is not a
scheduler heartbeat, worker-process check, or archive-file integrity check.
Pair it with process monitoring and a staging notification smoke test.

## Maintenance references

- [Queue configuration](../config/queue.php)
- [Automation check implementation](../app/Console/Commands/CheckAutomationHealth.php)
- [Scheduler tests](../tests/Feature/SchedulerRegistrationTest.php)
- [Automation check tests](../tests/Feature/AutomationHealthCommandTest.php)
