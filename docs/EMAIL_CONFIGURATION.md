# PRISM email configuration and troubleshooting

Last reviewed: **2026-09-09**. [Documentation index](README.md)

PRISM uses Laravel mail transports. Google SMTP is one deployment option, not a
hard-coded provider. Transport configuration is in [config/mail.php](../config/mail.php)
and provider credentials may also use [config/services.php](../config/services.php).
The NTE reminder email switch is in [config/notifications.php](../config/notifications.php);
see [.env.example](../.env.example) for other documented environment switches.
This guide replaces
`google-smtp-setup.md`.

## Development and staging

Use `MAIL_MAILER=log` to inspect messages without sending them. Messages may
contain account setup/reset links and sensitive details, so protect logs and
avoid copying them into issues. For delivery testing, use a controlled staging
mailbox and intended test recipients.

When changing environment values, rebuild configuration and restart workers:

```bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart
```

The process manager must replace workers after their graceful exit. See the
[scheduler/queue guide](SCHEDULER_QUEUE_DEPLOYMENT.md).

## Google SMTP example

Use an institution-managed sender account. Enable two-step verification and
check that the account permits app passwords. Some organizational accounts or
security configurations do not expose this option; coordinate with the Workspace
administrator instead of using the normal account password.
See [Google app-password requirements](https://support.google.com/accounts/answer/185833?hl=en).

Example deployment secrets (replace placeholders; never commit actual values):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME="no-reply@example.com"
MAIL_PASSWORD="replace-with-app-password"
MAIL_FROM_ADDRESS="${MAIL_USERNAME}"
MAIL_FROM_NAME="${APP_NAME}"
```

Port 587 is used for SMTP with STARTTLS. Confirm network/TLS support and that
the sender address is authorized for the mailbox. A configured `MAIL_URL` can
supply transport settings too; check it when the individual SMTP values appear
to be ignored.

App passwords are revoked when the Google account password changes. Replace the
deployment secret when required, refresh configuration, and restart workers.
Google imposes sending limits; for another transport or an administrator-managed
relay, agree the settings with the mail administrator rather than changing only
the hostname.

## Delivery verification

1. Confirm effective mailer/queue settings without exposing credentials.
2. Trigger one representative notification in staging, using a test account.
3. If queued, verify the worker consumes the job and inspect failed jobs.
4. Verify receipt, sender identity, links, spam placement, and expected reply behavior.
5. Ask the mail administrator to verify SPF, DKIM, and DMARC for the sender domain.
6. Repeat after transport, credentials, templates, or notification-switch changes.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| No message and no failed job | Notification eligibility/configuration switches, selected mailer, and queue worker. A log mailer does not send email. |
| Authentication rejected | Mailbox username, app password availability/revocation, and Workspace policy. |
| Connection or TLS failure | DNS, outbound port access, host, scheme, and certificate trust. |
| Old credentials still used | Cached configuration and long-running workers. |
| Queue failure | Inspect `php artisan queue:failed` and application logs before retrying a specific job. |
| Provider accepts but recipient does not receive | Spam/quarantine, sender authentication, recipient address, and provider limits. |

Use `php artisan queue:failed` to inspect failures. Avoid bulk retries until the
cause and potential duplicate-delivery effects are understood.

## Maintenance references

- [Account setup email tests](../tests/Feature/AdminAccountSetupEmailTest.php)
- [Student email tests](../tests/Feature/StudentEmailNotificationTest.php)
- [Password reset workflow tests](../tests/Feature/PasswordResetWorkflowTest.php)
- [Google app passwords](https://support.google.com/accounts/answer/185833?hl=en)
