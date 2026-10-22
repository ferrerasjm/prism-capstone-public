# PRISM security and privacy maintenance

Last reviewed: **2026-09-09**. [Documentation index](README.md)

This guide distinguishes implemented behavior from procedures the operating
office must carry out. Configured retention periods are application baselines,
not a statement that an institutional retention policy has been approved.

## Access control and periodic review

Role middleware, authentication guards, and controller/service checks govern
access. This summary is not a complete authorization matrix:

| Role | Main responsibilities |
| --- | --- |
| Super Admin | Accounts, academic setup, and risk weights. |
| Discipline Coordinator | UPCC workflow, case oversight, reports, and audit review. |
| Discipline Officer | Incidents, cases, sanctions, support actions, and reports. |
| Guidance Officer | Assigned/scoped guidance referrals and outcomes. |
| Reporting User | Own submitted reports and associated permitted case/appeal information. |
| US Supervisor | Assigned University Service work through web and mobile/API flows. |
| Student | Own permitted cases, notices, explanations, appeals, sanctions, service work, and complaints. |

At least once per term, review active accounts, roles, and department/office
assignments. Investigate inactive staff, deactivate accounts no longer needed,
and record the reviewer and date in office records.

`PRISM_ACCESS_REVIEW_INACTIVE_DAYS` defaults to 90. The privacy command counts
active staff without a qualifying recent login audit record; it does not
automatically deactivate accounts or certify that their assignments are correct.

## Uploads and storage

Incident/complaint evidence and appeal attachments use the private `local` disk
and controlled download/view endpoints. New uploads through the attachment
security service record a SHA-256 hash and scan metadata. Incident/complaint
forms accept PDF/JPG/JPEG/PNG; appeal forms also accept DOC/DOCX. Validation in
the relevant controller remains authoritative for file counts and size limits.

This does not mean every uploaded file is private or follows the same scanning
path. Profile photos use the public disk. Student handbooks use private local
storage with their own validation and checksum handling. Preserve these
distinctions when adding upload features.

Optional scanner configuration:

```dotenv
PRISM_SCAN_UPLOADS=true
PRISM_MALWARE_SCANNER_BINARY=clamscan
PRISM_MALWARE_SCANNER_TIMEOUT=30
```

Install and maintain the scanner before enabling it. Uploads using
`AttachmentSecurityService` are rejected when scanning detects an infection,
times out, or is unavailable. With scanning disabled, hashes are still computed
but scan status is `not_configured`. A hash identifies content; it is not a
malware verdict. Test the actual upload path after scanner changes.

## Secrets and encryption

Use HTTPS, disable production debug output, and preserve the deployment's
`APP_KEY`. Laravel encryption protects selected secrets such as US TOTP
secrets; this is not blanket encryption of all database columns or uploaded files.

Keep application keys, JWT/SMTP credentials, and backup encryption keys outside
version control. Preserve required keys through backup and restore. Treat key
rotation as a coordinated migration/recovery task; changing `APP_KEY` without
a plan can make existing encrypted data unreadable.

## Retention and audit records

See [Registrar roster and records review](REGISTRAR_ROSTER_AND_RETENTION.md) for the checked CRK-ACD-SDO-D-PO-001 provisions, three-year inactive-record review, Coordinator disposal approval, and the outstanding QMO Annex A dependency. Inactive records and inactive login accounts are different concepts.

Defaults in [config/prism_security.php](../config/prism_security.php):

```dotenv
PRISM_CASE_RECORD_RETENTION_YEARS=7
PRISM_ATTACHMENT_RETENTION_YEARS=7
PRISM_REPORT_RETENTION_YEARS=5
PRISM_AUDIT_LOG_RETENTION_YEARS=5
```

These settings do not schedule automatic disposal. The privacy command currently
counts audit records older than the audit window; it does not enumerate or purge
every record category using all four settings.

Audit records capture workflow actions, actors, timestamps, and relevant change
details. They are intended to be append-only through normal application usage.
The model does not enforce database-level immutability or prevent all updates/
deletes. Limit privileged database access and retain protected backups; do not
describe the audit table as tamper-proof.

For disposal, the office must confirm its retention policy, identify eligible
records and linked files, approve the scope, back up the affected data, and
record the disposal result. Any deletion script requires its own review of
relationships and recovery. No general approved-disposal command is supplied by
this guide.

## Backup and recovery

Recommended operating routine:

| Work | Frequency | Evidence |
| --- | --- | --- |
| Database backup | Daily | Successful backup log and protected backup location. |
| Persistent file backup | Daily or incremental | Coverage of private attachments, handbooks, reports, and public uploads. |
| Restore drill | Every 90 days | Restored application checks, date, and responsible operator. |

Back up the database and persistent contents under `storage/app`. Preserve
deployment configuration and required encryption keys securely, alongside the
application release/dependency versions needed for recovery.

Restore into an isolated environment first. Disable outbound notifications there,
restore matching database/files/keys, and verify login, record access, private
attachments, reports, and representative workflows. Record the actual successful
drill date in `PRISM_LAST_RESTORE_TEST_AT` (YYYY-MM-DD), then refresh cached
configuration. Do not populate it merely to silence a warning.

Backup controls include `PRISM_BACKUP_PATH` (default `storage/app/backups`) and
`PRISM_RESTORE_TEST_INTERVAL_DAYS` (default 90). The privacy check examines the
configured directory's existence in production and the recorded drill date.
It does not create backups, inspect their contents, verify freshness, or restore them.

## Verification and limitations

```bash
php artisan prism:privacy-check --fail-on-warning
php artisan prism:automation-check --fail-on-warning
```

The privacy command checks selected application settings, old audit logs,
incident/appeal attachments missing hashes, scanner enablement, inactive staff,
and backup/restore metadata. It does not execute the scanner to prove it works,
inspect every upload category, or establish compliance with institutional policy.
Without `--fail-on-warning`, warnings do not produce a failing exit status.

For implementation changes, run the relevant tests in development/staging:

```bash
php vendor/phpunit/phpunit/phpunit --filter SecurityPrivacyControlsTest
php vendor/phpunit/phpunit/phpunit --filter SecurityHardeningTest
```

## Maintenance references

- [Privacy check implementation](../app/Console/Commands/CheckPrivacyControls.php)
- [Attachment security service](../app/Services/AttachmentSecurityService.php)
- [Audit model](../app/Models/AuditLog.php)
- [Filesystem configuration](../config/filesystems.php)
- [Web routes](../routes/web.php) and [API routes](../routes/api.php)
- [Security regression tests](../tests/Feature/SecurityPrivacyControlsTest.php)
