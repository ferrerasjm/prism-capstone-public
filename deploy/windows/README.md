# Deploy PRISM on Windows Server

Use this guide for your **Windows Server VPS** (for example `prism-demo` on Windows Server 2019 / Laragon).

You do **not** need Cursor, VS Code, or any IDE on the server. Develop on your PC, push to GitHub, and let the host pull updates automatically.

## Recommended workflow (local Cursor → host sync)

```
Your PC (Cursor)  →  git push origin main  →  GitHub  →  host auto-deploy (Task Scheduler)
```

### On this PC (every change)

**Option A — GitHub Desktop (easiest)**

1. Open GitHub Desktop → select `prism-capstone` → branch `main`
2. Review the changed files
3. Type a Summary (commit message)
4. Click **Commit to main**
5. Click **Push origin** (top toolbar)

Hosting picks up the push via the auto-deploy task.

**Option B — `push-to-host.cmd`**

Double-click `push-to-host.cmd` in the repo root (or `deploy\windows\push-to-host.cmd`).  
It prompts for a commit message, then stages, commits, and pushes to `main`.

Or from a terminal:

```bat
push-to-host.cmd "fix login button color"
```

**Option C — plain git**

```powershell
git add .
git commit -m "your message"
git push origin main
```

### On the hosting VPS (one-time setup)

1. Make sure the host repo is on `main` and has the latest scripts:

```powershell
cd C:\laragon\www\prism
git fetch origin
git checkout main
git pull origin main
```

2. Register auto-deploy (elevated PowerShell). `-SkipNpmBuild` is retained for compatibility; the current scripts do not build frontend assets:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
cd C:\laragon\www\prism\deploy\windows
.\install-auto-deploy-task.ps1 -Branch main -IntervalMinutes 5 -SkipNpmBuild
```

3. Uninstall Cursor from the host. You only need Git + Laragon + the scheduled task.

4. Optional manual deploy anytime:

```powershell
schtasks /Run /TN "PRISM Auto Deploy"
# or:
.\auto-deploy-prism.ps1 -Branch main -SkipNpmBuild
```

Deploy log: `C:\laragon\www\prism\storage\logs\auto-deploy.log`

**Frontend:** PRISM serves Blade and committed assets from `public/`. There is no npm build script or requirement to copy `public/build`. See the [current scheduler and queue guide](../../docs/SCHEDULER_QUEUE_DEPLOYMENT.md) for deployment checks and worker retry/timeout settings.

## Before you start

Your current VPS spec (1 vCPU / 2 GB RAM) is tight for PRISM because the server must run:

- IIS
- PHP 8.3
- MySQL
- Laravel queue worker
- Laravel scheduler

**Recommended minimum:** 2 vCPU / 4 GB RAM.

If you must stay on 2 GB:

1. Deploy the committed Blade templates and `public/` assets with the application.
2. Node.js is not required for the current frontend; `-SkipNpmBuild` remains a compatibility option.
3. Close RDP sessions and do not install desktop tools on the server.
4. Import your SQL dump instead of running heavy seeders on the server.

## What gets installed

| Component | Purpose |
| --- | --- |
| IIS + URL Rewrite | Web server for Laravel |
| PHP 8.3 (FastCGI) | Laravel runtime |
| MySQL | Database |
| Git + Composer | Pull code and install PHP packages |
| Node.js (optional) | Build Vite assets on the server |
| Task Scheduler | Queue worker + Laravel scheduler |

## 1. Prepare on your PC

1. Push your hosting work to the `main` branch (already done if you followed earlier steps).
2. Export your database to a `.sql` file (for example `prism-fresh.sql`).
3. Confirm that the release includes the required files in `public/` and `resources/views/`. No npm build or generated asset copy is needed.

## 2. Copy files to the server

Remote Desktop into the server, then copy these to `C:\Setup\`:

- `deploy/windows/setup-prism.ps1`
- `deploy/windows/web.config`
- `prism-fresh.sql` (your database dump)

You can clone the repo instead:

```powershell
git clone --branch main https://github.com/ferrerasjm/prism-capstone-public.git C:\Setup\prism-capstone
```

## 3. Run setup (elevated PowerShell)

Open **PowerShell as Administrator** on the server:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
cd C:\Setup\prism-capstone\deploy\windows

# Compatibility flag; the current frontend has no build step
.\setup-prism.ps1 `
  -Branch main `
  -SqlFile "C:\Setup\prism-fresh.sql" `
  -AppUrl "http://192.0.2.10" `
  -SkipNpmBuild
```

Optional parameters:

| Parameter | Default | Notes |
| --- | --- | --- |
| `-Branch` | `main` | Git branch to deploy |
| `-AppDir` | `C:\inetpub\prism` | Install path |
| `-SqlFile` | _(none)_ | HeidiSQL / MySQL dump to import |
| `-AppUrl` | auto-detected public IP | Set to your server IP or domain |
| `-DbPass` | `PRISM_SETUP_DB_PASSWORD` environment variable | Required private database password |
| `-SkipNpmBuild` | off | Compatibility option; current frontend has no build step |

First run takes 15–30 minutes (Chocolatey downloads PHP, MySQL, IIS modules).

## 4. Open the app

Browser: `http://YOUR_SERVER_IP`

There is no default login. Configure `PRISM_ADMIN_EMAIL` and
`PRISM_ADMIN_PASSWORD` privately in the application's `.env`, then run:

```powershell
php artisan config:clear
php artisan db:seed
```

Use a unique password of at least 12 characters. After provisioning, clear the
bootstrap settings and rebuild the configuration cache. Existing accounts are
not reset. Create supervisor accounts through User Management.

## 5. Firewall

Allow inbound HTTP if the site does not load externally:

```powershell
New-NetFirewallRule -DisplayName "PRISM HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow
```

## 6. Later updates (no full reinstall)

After you push changes to GitHub:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
cd C:\Setup\prism-capstone\deploy\windows
.\update-prism.ps1 -Branch main -SkipNpmBuild
```

## Troubleshooting

### CPU or RAM at 100%

- Do not install Cursor or other IDEs on the server.
- Deploy committed frontend assets; no Node build process is required.
- Restart MySQL and IIS:

```powershell
Restart-Service MySQL*
iisreset
```

### 500 error / blank page

Check Laravel logs:

```powershell
Get-Content C:\inetpub\prism\storage\logs\laravel.log -Tail 50
```

Common fixes:

```powershell
cd C:\inetpub\prism
php artisan config:clear
php artisan cache:clear
php artisan config:cache
icacls storage /grant "IIS_IUSRS:(OI)(CI)M" /T
icacls bootstrap\cache /grant "IIS_IUSRS:(OI)(CI)M" /T
```

### CSS/JS missing

Check that the web server points to the release's `public` directory and that
the requested CSS/JS files are present there. Inspect browser network errors,
file permissions, and asset URLs. Refresh compiled Blade views after deployment
with `php artisan view:cache`; there is no Vite build to regenerate.

### Queue or scheduled jobs not running

```powershell
schtasks /Query /TN "PRISM Queue Worker"
schtasks /Query /TN "PRISM Scheduler"
schtasks /Run /TN "PRISM Queue Worker"
```

### MySQL will not start on 2 GB RAM

Edit MySQL config (path varies by install) and lower memory, then restart MySQL:

```ini
innodb_buffer_pool_size=128M
key_buffer_size=16M
```

## Workflow summary

```
Your PC (Cursor) → git push → GitHub (main) → server setup/update scripts → IIS serves the app
```

Cursor stays on your laptop. The server only runs the production stack.
