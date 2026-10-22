# PRISM scheduler and queue deployment

Last reviewed: **2026-09-09**. [Documentation index](README.md)

The scheduler starts deadline and reporting commands. The queue worker processes
queued work, including notifications. Running one does not replace the other.
Execute commands below from the project root.

## Scheduled tasks

Source: [routes/console.php](../routes/console.php). Times use the configured
application timezone, normally `Asia/Manila`.

| Frequency / time | Command | Purpose |
| --- | --- | --- |
| Monthly, day 1 at 00:00 | `reports:archive-monthly` | Archive the previous month's violation summary. |
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

All entries prevent overlap, use a single-server lock, and append output to
`storage/logs/scheduler.log`. Multiple hosts must share the cache backend used
for these locks. Check registration with `php artisan schedule:list`.

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
timeouts. See [Laravel queue guidance](https://laravel.com/framework/docs/13.x/queues#job-expirations-and-timeouts).

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
| Email queued but not delivered | Verify worker connection/queue, failed jobs, and [email configuration](EMAIL_CONFIGURATION.md). |
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
