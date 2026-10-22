# Security and privacy

## Access and storage

Role middleware and record/department checks govern access. Review active staff accounts, roles, and assignments at least once per term; deactivate accounts that no longer need access.

Evidence and generated reports use private storage with controlled downloads. Profile photos use the public disk. Keep the database, private contacts, evidence, handbook PDFs, logs, and backups out of Git.

Optional upload scanning uses `PRISM_SCAN_UPLOADS=true` with `PRISM_MALWARE_SCANNER_BINARY` and `PRISM_MALWARE_SCANNER_TIMEOUT`. Install and maintain the scanner first. Uploads handled by the scanning service are rejected if scanning fails; hashes alone do not establish that a file is safe.

## Secrets and deployment

- Use HTTPS, `APP_ENV=production`, and `APP_DEBUG=false`.
- Disable OTP console/reveal settings and enable actual OTP email delivery; see [setup](SETUP.md).
- Keep `.env`, SMTP/JWT secrets, `APP_KEY`, and the operational contact directory private.
- Preserve encryption keys during updates and restores. Key changes require a recovery plan.
- Replace placeholder privacy contacts with approved institutional contacts before deployment.

## Retention and audit

Default retention settings in [config/prism_security.php](../config/prism_security.php) are seven years for cases/attachments and five for reports/audit logs. These are configurable baselines; confirm the institution's approved policy.

Retention settings do not automatically approve or perform disposal. Inactive accounts and inactive disciplinary records are different concepts. Authorized staff must review eligible records, approve disposal, and record the outcome. Audit records are append-only through normal workflows, without database-level immutability.

## Backup and recovery

Back up the database, `storage/app`, and required configuration/encryption keys to protected storage. Perform restore drills at least every 90 days and record `PRISM_LAST_RESTORE_TEST_AT` after a successful drill.

Run `php artisan prism:privacy-check --fail-on-warning` to inspect configured controls. The command does not certify policy compliance or execute access reviews, malware installation, backups, or restore drills.
