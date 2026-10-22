#Requires -Version 5.1
<#
.SYNOPSIS
  Bring PRISM back up on the Laragon Windows host after a 500 / downtime.

.DESCRIPTION
  Run on the hosting VPS in PowerShell (as Administrator if possible):
    cd C:\laragon\www\prism\deploy\windows
    powershell -ExecutionPolicy Bypass -File .\recover-prism.ps1
#>
[CmdletBinding()]
param(
    [string]$AppDir = "C:\laragon\www\prism"
)

$ErrorActionPreference = "Continue"

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

if (-not (Test-Path $AppDir)) {
    Write-Host "ERROR: App not found at $AppDir" -ForegroundColor Red
    exit 1
}

Write-Step "Starting Laragon (Apache + MySQL)"
$Laragon = "C:\laragon\laragon.exe"
if (Test-Path $Laragon) {
    # Start Laragon UI if not already running (starts services when "Start All" was used before)
    $laragonProc = Get-Process -Name "laragon" -ErrorAction SilentlyContinue
    if (-not $laragonProc) {
        Start-Process $Laragon
        Start-Sleep -Seconds 5
    }
    Write-Host "Open Laragon if needed and click 'Start All' (Apache + MySQL)."
} else {
    Write-Host "Laragon not found at $Laragon - start Apache/MySQL manually." -ForegroundColor Yellow
}

Write-Step "Checking MySQL / Apache ports"
try {
    $mysql = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
    Write-Host ("MySQL :3306  -> {0}" -f $(if ($mysql.TcpTestSucceeded) { "UP" } else { "DOWN" }))
} catch { Write-Host "Could not probe MySQL port" }
try {
    $http = Test-NetConnection -ComputerName 127.0.0.1 -Port 80 -WarningAction SilentlyContinue
    Write-Host ("HTTP  :80    -> {0}" -f $(if ($http.TcpTestSucceeded) { "UP" } else { "DOWN" }))
} catch { Write-Host "Could not probe HTTP port" }

Set-Location $AppDir

Write-Step "Checking .env"
if (-not (Test-Path ".env")) {
    Write-Host "ERROR: .env is missing. Restore it from backup before continuing." -ForegroundColor Red
    exit 1
}
Write-Host ".env found"

Write-Step "Ensuring vendor exists"
if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "vendor missing - running composer install..."
    composer install --no-dev --optimize-autoloader --no-interaction
} else {
    Write-Host "vendor OK"
}

Write-Step "Clearing and rebuilding Laravel caches"
php artisan config:clear
php artisan cache:clear 2>$null
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

Write-Step "Quick artisan health check"
php artisan about 2>&1 | Select-Object -First 25

Write-Step "Restarting queue worker"
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match "queue:work" } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

$StartServices = Join-Path $AppDir "deploy\windows\start-prism-services.ps1"
if (Test-Path $StartServices) {
    & powershell -ExecutionPolicy Bypass -File $StartServices
} else {
    Start-Process -FilePath "php" -ArgumentList "artisan","queue:work","database","--sleep=3","--tries=3","--timeout=120" `
        -WorkingDirectory $AppDir -WindowStyle Hidden
}

Write-Step "Latest Laravel log (last 40 lines)"
$log = Get-ChildItem "$AppDir\storage\logs\laravel*.log" -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1
if ($log) {
    Write-Host ("Log: {0}" -f $log.FullName) -ForegroundColor Yellow
    Get-Content $log.FullName -Tail 40
} else {
    Write-Host "No laravel.log found yet."
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "Recovery steps finished."
Write-Host "1. In Laragon: click Start All if Apache/MySQL were stopped"
Write-Host "2. Reload your configured application URL"
Write-Host "3. If still 500, scroll up - the log lines above show the cause"
Write-Host "========================================" -ForegroundColor Green
