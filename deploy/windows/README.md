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
