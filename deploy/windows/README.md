# Windows deployment

For Windows Server with IIS, PHP, and MySQL. The scripts require elevated PowerShell and can install packages, create scheduled tasks, restart services, and run migrations. Review their parameters before running them on a server.

## New installation

Clone this repository on the server, then configure the private `PRISM_SETUP_DB_PASSWORD` process environment variable. From the project root, run:

```powershell
.\deploy\windows\setup-prism.ps1 -Branch main -AppDir 'C:\inetpub\prism' -AppUrl 'https://your-domain.example'
```

`-SqlFile` optionally imports an authorized private database dump; a new source installation does not require one. Configure the installed `.env`, administrator bootstrap credentials, SMTP, HTTPS, and approved privacy contacts using the [setup guide](../../docs/SETUP.md).

## Runtime and updates

- `start-prism-services.ps1 -AppDir <path>` starts/checks the scheduler, worker, and optional classifier runtime.
- `install-auto-deploy-task.ps1 -AppDir <path> -Branch main` registers automatic updates. Enable only when reviewed pushes should deploy automatically.
- `update-prism.ps1 -AppDir <path> -Branch main` updates an existing installation; back up its database, storage, keys, and private configuration first.
- `recover-prism.ps1` and `check-mail.ps1` provide recovery and email diagnostics; inspect their parameters and output before sharing logs.

Use the new repository's `main` branch for installations based on this public snapshot. Preserve private contact directories and model artifacts across updates. Git does not deliver model weights; follow [classifier setup](../../ml/README.md). The frontend has no build step; `-SkipNpmBuild` is a compatibility flag.

Serve only `public/`. Review the [security guide](../../docs/SECURITY_PRIVACY_CONTROLS.md) and verify scheduled work, queue delivery, and uploads after deployment.

## Installation parameters

| Parameter | Meaning |
| --- | --- |
| `-RepoUrl` | Source repository; defaults to this public repository |
| `-Branch` | Reviewed branch to install; defaults to `main` |
| `-AppDir` | Installation destination; setup defaults to `C:/inetpub/prism` |
| `-DbName`, `-DbUser` | Database and database account names |
| `-DbPass` | Private database password; defaults to process environment `PRISM_SETUP_DB_PASSWORD` |
| `-SqlFile` | Optional private SQL import; review its target and contents before use |
| `-AppUrl` | Public application URL; configure HTTPS before operational use |

Provision the first administrator in the installed application's private `.env`, run `php artisan config:clear` and `php artisan db:seed`, then clear bootstrap credentials and rebuild configuration. There is no shared default login. Confirm the intended reference data exists before creating real cases.

## Before and after updates

Before updating, confirm the application directory, repository remote, branch, reviewed release, database backup, persistent files, keys, and migration compatibility. Review the selected update script: it can stop services and change the database. An existing checkout with divergent history requires reconciliation before deployment.

After updating, verify `php artisan schedule:list`, `php artisan queue:failed`, registered Windows task paths/account permissions, and scheduler/worker processes. Run `php artisan prism:automation-check --fail-on-warning` and `php artisan prism:privacy-check --fail-on-warning`, then confirm representative notification delivery in staging.

When ML is enabled, check `http://127.0.0.1:8765/health` and verify an authenticated prediction through Laravel. Health alone does not prove token matching, label compatibility, or the complete application integration. Restart the model process after classifier code/artifact changes. Never share the runtime-config helper's output; it includes the service token.

If a release fails, preserve logs privately, identify whether migrations or jobs already produced side effects, and restore compatible code/database/files/keys as a coordinated operation. See [operations](../../docs/OPERATIONS.md) for worker timeout settings, diagnostics, and update steps.
