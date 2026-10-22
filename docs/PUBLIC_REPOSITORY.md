# Public source snapshot

This repository was prepared from the current tracked PRISM source on 22 October 2026 and starts with one new root commit. It shares no Git history, pull request references, tags, remotes, or Git object database with the private development repository.

## Included source

The application, migrations, reference-data seeders, dependency lockfiles, tests, schema diagrams, and documented synthetic classifier corpus are included. The initial administrator is provisioned using private `PRISM_ADMIN_EMAIL` and `PRISM_ADMIN_PASSWORD` values; see the root README.

Personal contact addresses, realistic student email examples, the production hostname/IP, and the deployment default pointing to the private repository were replaced in this snapshot. Institutional names, branding, and policy definitions remain part of the project.

## Private runtime data

Only files tracked at the source revision were exported. Local `.env`, operational seeders, database exports, credentials, uploaded evidence, handbook PDFs, logs, dependencies, cached configuration, private contact directories, and trained model artifacts were not copied.

Do not import private data or copy runtime storage into this repository. Configure approved privacy contacts in `resources/views/auth/partials/privacy-notice-body.blade.php` before deploying. Example mailboxes under `example.com` cannot deliver operational mail; configure real sender credentials privately. Student account examples use the application's required institutional email domain with explicit demo names.

An existing installation may use `storage/app/private/university-service-contacts.php` or `PRISM_US_CONTACTS_FILE` for its operational contact directory. Supply this file privately. The default tracked directory contains generic roles only.

## Independent development

The original development repository must remain private while its retained sensitive pull request history is being addressed. Keep its support request open. A new public snapshot does not purge data retained in the original repository or other clones.

Keep these repositories independent. Review and export future source changes without merging the old development history or pushing its refs. Never use a mirror push from the old repository to update this one. The deployment scripts in this snapshot default to this repository's `main` branch; existing installations were not changed or accessed.

## Review limits

This is a source and exposure review, not an application security certification. Common secret formats, current private credential values, and known identifiers from available ignored operational files were checked. Automated scans cannot prove the absence of every kind of personal data. The classifier corpus is described as synthetic by its project documentation; scan results do not independently establish its provenance.
