#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Pull latest code and refresh caches on an existing Windows PRISM install.

.EXAMPLE
  .\update-prism.ps1 -Branch main
#>
[CmdletBinding()]
param(
    [string]$AppDir = "C:\inetpub\prism",
    [string]$Branch = "main",
    [switch]$SkipNpmBuild
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $AppDir)) {
    throw "App directory not found: $AppDir"
}

Set-Location $AppDir

Write-Host "==> Stopping PRISM runtime processes before Git update" -ForegroundColor Cyan
foreach ($task in @('PRISM Scheduler', 'PRISM Service Watchdog', 'PRISM Queue Worker')) {
  # Absent tasks make schtasks write to stderr, which PowerShell 5.1 turns into a
  # terminating NativeCommandError here. Redirect inside cmd so a task that was
  # never registered cannot abort the deploy before it reaches the pull.
  cmd /c "schtasks /End /TN ""$task"" >nul 2>&1"
}
Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
  Where-Object {
    ($_.Name -eq 'php.exe' -and $_.CommandLine -match 'queue:work') -or
    ($_.Name -eq 'python.exe' -and $_.CommandLine -match 'serve_hybrid')
  } |
  ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Write-Host "==> Pulling $Branch" -ForegroundColor Cyan
git fetch origin $Branch
git checkout $Branch
git pull origin $Branch

Write-Host "==> Updating PHP dependencies" -ForegroundColor Cyan
composer install --no-dev --optimize-autoloader --no-interaction

# No JS build pipeline exists (audit pass 1, Sep 2026): the app is
# server-rendered Blade with no @vite call anywhere, so there is nothing
# to build.

Write-Host "==> Running migrations and refreshing caches" -ForegroundColor Cyan
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

Write-Host "==> Restarting queue worker task" -ForegroundColor Cyan
cmd /c 'schtasks /End /TN "PRISM Queue Worker" >nul 2>&1'
Start-Sleep -Seconds 2
cmd /c 'schtasks /Run /TN "PRISM Queue Worker" >nul 2>&1'

Write-Host ""
& powershell -ExecutionPolicy Bypass -File (Join-Path $AppDir 'deploy\windows\start-prism-services.ps1') -AppDir $AppDir
if ($LASTEXITCODE -ne 0) { throw "PRISM service readiness failed; update is not ready." }
Write-Host "Update complete." -ForegroundColor Green
