#Requires -Version 5.1
<#
.SYNOPSIS
  Pull latest Git changes and refresh the Laragon PRISM install on the host.

.DESCRIPTION
  Intended to run on the hosting VPS only (Task Scheduler or manual).
  Does NOT overwrite .env. Safe to uninstall Cursor after this is set up.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File C:\laragon\www\prism\deploy\windows\auto-deploy-prism.ps1

.EXAMPLE
  .\auto-deploy-prism.ps1 -Branch main -SkipNpmBuild
#>
[CmdletBinding()]
param(
    [string]$AppDir = "C:\laragon\www\prism",
    [string]$Branch = "main",
    [string]$Remote = "origin",
    [switch]$SkipNpmBuild,
    [switch]$SkipMigrate
)

$ErrorActionPreference = "Stop"
$LogDir = Join-Path $AppDir "storage\logs"
$LogFile = Join-Path $LogDir "auto-deploy.log"

function Write-DeployLog([string]$Message, [string]$Color = "White") {
    $line = "[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $Message
    Write-Host $line -ForegroundColor $Color
    try {
        if (-not (Test-Path $LogDir)) {
            New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
        }
        Add-Content -Path $LogFile -Value $line -Encoding UTF8
    } catch {
        # Logging must never fail the deploy.
    }
}

function Assert-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command not found on PATH: $Name"
    }
}

function Stop-PrismRuntime([string]$AppDir) {
    foreach ($task in @('PRISM Scheduler', 'PRISM Service Watchdog', 'PRISM Queue Worker')) {
        schtasks /End /TN $task 2>$null | Out-Null
    }

    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object {
            ($_.Name -eq 'php.exe' -and $_.CommandLine -match 'queue:work') -or
            ($_.Name -eq 'python.exe' -and $_.CommandLine -match 'serve_hybrid')
        } |
        ForEach-Object {
            Write-DeployLog "Stopping runtime process PID $($_.ProcessId) before Git update"
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        }
}

if (-not (Test-Path $AppDir)) {
    throw "App directory not found: $AppDir"
}

Set-Location $AppDir
Write-DeployLog "PRISM Auto Deploy starting in $AppDir" "Cyan"

# Prefer Laragon PHP/Composer if present (must happen before Assert-Command
# checks below, since a bare elevated PowerShell session has neither on PATH).
# Laragon nests the exe under a version folder (e.g. php-8.3.30-Win32-vs16-x64),
# so the PHP root itself is never on PATH directly - find the exe and add its
# actual containing folder instead.
$LaragonPhp = "C:\laragon\bin\php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $LaragonPhpExe = Get-ChildItem $LaragonPhp -Recurse -Filter php.exe -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($LaragonPhpExe) {
        $env:Path = "$($LaragonPhpExe.DirectoryName);" + $env:Path
    }
}

$LaragonComposer = "C:\laragon\bin\composer"
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    $LaragonComposerCmd = Get-ChildItem $LaragonComposer -Recurse -Filter composer.bat -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if ($LaragonComposerCmd) {
        $env:Path = "$($LaragonComposerCmd.DirectoryName);" + $env:Path
    }
}

Assert-Command "git"
Assert-Command "php"
Assert-Command "composer"

# This task runs as SYSTEM, which has no cached Git credentials. Without this,
# an auth prompt from git blocks forever (no one is there to answer it), and
# because the scheduled task allows MultipleInstances IgnoreNew, every later
# 5-minute trigger gets silently skipped while this one hangs - deploys can
# then stall for days until someone manually kills the stuck run. Forcing git
# to fail fast here means a bad/expired credential surfaces immediately in
# the log instead of wedging the whole deploy pipeline.
$env:GIT_TERMINAL_PROMPT = "0"

Write-DeployLog "Fetching $Remote/$Branch"
git fetch $Remote $Branch
if ($LASTEXITCODE -ne 0) { throw "Git fetch failed; running services were left available." }

$localSha = (git rev-parse HEAD).Trim()
$remoteSha = (git rev-parse "$Remote/$Branch").Trim()

if ($localSha -eq $remoteSha) {
    Write-DeployLog "Already up to date at $localSha - nothing to deploy." "Green"
    exit 0
}

# A history rewrite requires an operator to reconcile the server checkout.
# Detect it before stopping the current runtime or changing application files.
git merge-base --is-ancestor $localSha $remoteSha
if ($LASTEXITCODE -ne 0) {
    throw "Deployment requires a fast-forward update. Reconcile the checkout after the history rewrite; running services were left available."
}

if (git status --porcelain --untracked-files=no) {
    throw "Tracked local changes need review before deployment; running services were left available."
}

Write-DeployLog "Updating $localSha -> $remoteSha"
git checkout $Branch
if ($LASTEXITCODE -ne 0) { throw "Git checkout failed; running services were left available." }
git pull --ff-only $Remote $Branch
if ($LASTEXITCODE -ne 0) { throw "Git pull failed; running services were left available." }

Stop-PrismRuntime $AppDir

Write-DeployLog "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

# No JS build pipeline exists (audit pass 1, Sep 2026): the app is
# server-rendered Blade with no @vite call anywhere, so there is nothing
# to build. -SkipNpmBuild is kept as an accepted, now-inert flag so any
# existing invocation that still passes it does not break.

if (-not $SkipMigrate) {
    Write-DeployLog "Running migrations"
    php artisan migrate --force
}

Write-DeployLog "Refreshing Laravel caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

Write-DeployLog "Restarting queue workers"
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match "queue:work" } |
    ForEach-Object {
        Write-DeployLog "Stopping queue worker PID $($_.ProcessId)"
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }

$StartServices = Join-Path $AppDir "deploy\windows\start-prism-services.ps1"
if (Test-Path $StartServices) {
    Write-DeployLog "Starting PRISM services"
    & powershell -ExecutionPolicy Bypass -File $StartServices -AppDir $AppDir
    if ($LASTEXITCODE -ne 0) { throw "PRISM service readiness failed; deployment is not ready." }
} else {
    Start-Process -FilePath "php" -ArgumentList "artisan","queue:work","database","--sleep=3","--tries=3","--timeout=120" `
        -WorkingDirectory $AppDir -WindowStyle Hidden
}

$short = (git rev-parse --short HEAD).Trim()
Write-DeployLog "Deploy complete at commit $short" "Green"
exit 0
