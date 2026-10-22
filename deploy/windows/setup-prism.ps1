#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Install PRISM on Windows Server (IIS + PHP 8.3 + MySQL).

.DESCRIPTION
  Run on the server in an elevated PowerShell window:
    Set-ExecutionPolicy Bypass -Scope Process -Force
    .\setup-prism.ps1

  For low-RAM VPS (2 GB), build assets on your PC first, then deploy with:
    .\setup-prism.ps1 -SkipNpmBuild

.PARAMETER SkipNpmBuild
  Accepted for backward compatibility; has no effect. The app has no JS
  build pipeline (audit pass 1, Sep 2026), so there is nothing to build
  or skip.
#>
[CmdletBinding()]
param(
    [string]$RepoUrl = "https://github.com/ferrerasjm/prism-capstone-public.git",
    [string]$Branch = "main",
    [string]$AppDir = "C:\inetpub\prism",
    [string]$SqlFile = "",
    [string]$DbName = "prism",
    [string]$DbUser = "prism",
    [string]$DbPass = $env:PRISM_SETUP_DB_PASSWORD,
    [string]$AppUrl = "",
    [string]$SiteName = "PRISM",
    [switch]$SkipNpmBuild
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($DbPass)) {
    throw "Supply a private database password via PRISM_SETUP_DB_PASSWORD or -DbPass."
}

function Write-Step([string]$Message) {
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Ensure-Command([string]$Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Required command '$Name' was not found on PATH."
    }
}

function Install-Chocolatey {
    if (Get-Command choco -ErrorAction SilentlyContinue) {
        return
    }

    Write-Step "Installing Chocolatey"
    Set-ExecutionPolicy Bypass -Scope Process -Force
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
    Invoke-Expression ((New-Object System.Net.WebClient).DownloadString("https://community.chocolatey.org/install.ps1"))
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" +
        [System.Environment]::GetEnvironmentVariable("Path", "User")
}

function Install-Package([string]$Package, [string[]]$ExtraArgs = @()) {
    if ($null -ne (choco list --local-only $Package -r -e 2>$null | Select-String "^$Package\|")) {
        Write-Host "  $Package already installed"
        return
    }

    Write-Host "  Installing $Package..."
    choco install $Package -y --no-progress @ExtraArgs | Out-Null
}

Write-Step "Checking administrator privileges"
$currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($currentIdentity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script in an elevated PowerShell session."
}

if ([string]::IsNullOrWhiteSpace($AppUrl)) {
    try {
        $AppUrl = "http://{0}" -f (Invoke-RestMethod -Uri "https://api.ipify.org" -TimeoutSec 10)
    } catch {
        $AppUrl = "http://localhost"
    }
}

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Step "Installing Chocolatey and base packages"
Install-Chocolatey
Install-Package "git"
Install-Package "composer"
Install-Package "php" @("--version=8.3.17")
Install-Package "mysql"
Install-Package "urlrewrite"
Install-Package "vcredist140"

# Node.js is not installed here: the app has no JS build pipeline (audit
# pass 1, Sep 2026) and never runs `composer dev` on the server, so nothing
# on this host needs npm.

Write-Step "Enabling IIS features"
$IisFeatures = @(
    "IIS-WebServerRole",
    "IIS-WebServer",
    "IIS-CommonHttpFeatures",
    "IIS-StaticContent",
    "IIS-DefaultDocument",
    "IIS-DirectoryBrowsing",
    "IIS-HttpErrors",
    "IIS-HttpRedirect",
    "IIS-ApplicationDevelopment",
    "IIS-CGI",
    "IIS-ISAPIExtensions",
    "IIS-ISAPIFilter",
    "IIS-HttpLogging",
    "IIS-RequestFiltering",
    "IIS-HttpCompressionStatic",
    "IIS-ManagementConsole"
)

foreach ($feature in $IisFeatures) {
    if ((Get-WindowsOptionalFeature -Online -FeatureName $feature).State -ne "Enabled") {
        Enable-WindowsOptionalFeature -Online -FeatureName $feature -All -NoRestart | Out-Null
    }
}

Import-Module WebAdministration

Write-Step "Locating PHP"
Ensure-Command "php"
$PhpRoot = Split-Path -Parent (Get-Command php).Source
$PhpCgi = Join-Path $PhpRoot "php-cgi.exe"
if (-not (Test-Path $PhpCgi)) {
    throw "php-cgi.exe was not found under $PhpRoot"
}

Write-Step "Configuring PHP for Laravel"
$PhpIni = Join-Path $PhpRoot "php.ini"
if (-not (Test-Path $PhpIni)) {
    Copy-Item (Join-Path $PhpRoot "php.ini-production") $PhpIni
}

$PhpSettings = @{
    "extension_dir" = "`"ext`""
    "upload_max_filesize" = "32M"
    "post_max_size" = "32M"
    "memory_limit" = "256M"
    "max_execution_time" = "120"
    "date.timezone" = "Asia/Manila"
    "cgi.fix_pathinfo" = "0"
    "fastcgi.impersonate" = "1"
    "opcache.enable" = "1"
}

$PhpIniContent = Get-Content $PhpIni -Raw
foreach ($entry in $PhpSettings.GetEnumerator()) {
    $pattern = "(?m)^\s*;?\s*$([regex]::Escape($entry.Key))\s*=.*$"
    if ($PhpIniContent -match $pattern) {
        $PhpIniContent = [regex]::Replace($PhpIniContent, $pattern, "$($entry.Key) = $($entry.Value)")
    } else {
        $PhpIniContent += "`r`n$($entry.Key) = $($entry.Value)"
    }
}

$LaravelExtensions = @(
    "curl",
    "fileinfo",
    "gd",
    "intl",
    "mbstring",
    "mysqli",
    "openssl",
    "pdo_mysql",
    "sodium",
    "zip"
)

foreach ($extension in $LaravelExtensions) {
    $line = "extension=$extension"
    if ($PhpIniContent -notmatch "(?m)^\s*extension\s*=\s*$extension\s*$") {
        $PhpIniContent += "`r`n$line"
    }
}

Set-Content -Path $PhpIni -Value $PhpIniContent -Encoding ASCII

Write-Step "Configuring IIS FastCGI for PHP"
& "$env:windir\system32\inetsrv\appcmd.exe" set config /section:system.webServer/fastCgi /+"[fullPath='$PhpCgi']" | Out-Null
& "$env:windir\system32\inetsrv\appcmd.exe" set config /section:system.webServer/fastCgi /+"[fullPath='$PhpCgi'].environmentVariables.[name='PHPRC',value='$PhpRoot']" | Out-Null

Write-Step "Starting MySQL and creating database"
Ensure-Command "mysql"
$MySqlService = Get-Service -Name "MySQL*" -ErrorAction SilentlyContinue | Select-Object -First 1
if ($MySqlService -and $MySqlService.Status -ne "Running") {
    Start-Service $MySqlService.Name
}

$SqlBootstrap = @"
CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';
ALTER USER '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';
GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';
FLUSH PRIVILEGES;
"@

$SqlBootstrap | mysql -u root

if ($SqlFile -and (Test-Path $SqlFile)) {
    Write-Step "Importing SQL dump: $SqlFile"
    Get-Content $SqlFile -Raw | mysql -u root
    $SqlBootstrap | mysql -u root
} else {
    Write-Host "No SQL dump imported. Run migrations later if needed."
}

Write-Step "Cloning repository ($Branch)"
if (Test-Path $AppDir) {
    Remove-Item $AppDir -Recurse -Force
}

git clone --branch $Branch --single-branch $RepoUrl $AppDir
Set-Location $AppDir

Write-Step "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

# No JS build pipeline exists (audit pass 1, Sep 2026): the app is
# server-rendered Blade with no @vite call anywhere, so there is nothing
# to build and no manifest.json to check for.

Write-Step "Writing .env"
@(
    "APP_NAME=PRISM"
    "APP_ENV=production"
    "APP_KEY="
    "APP_DEBUG=false"
    "APP_URL=$AppUrl"
    "APP_TIMEZONE=Asia/Manila"
    ""
    "APP_LOCALE=en"
    "APP_FALLBACK_LOCALE=en"
    "APP_FAKER_LOCALE=en_US"
    ""
    "APP_MAINTENANCE_DRIVER=file"
    "BCRYPT_ROUNDS=12"
    ""
    "LOG_CHANNEL=stack"
    "LOG_STACK=single"
    "LOG_LEVEL=error"
    ""
    "DB_CONNECTION=mysql"
    "DB_HOST=127.0.0.1"
    "DB_PORT=3306"
    "DB_DATABASE=$DbName"
    "DB_USERNAME=$DbUser"
    "DB_PASSWORD=$DbPass"
    ""
    "SESSION_DRIVER=database"
    "SESSION_LIFETIME=120"
    "QUEUE_CONNECTION=database"
    "CACHE_STORE=database"
    "FILESYSTEM_DISK=local"
    "MAIL_MAILER=log"
    "BROADCAST_CONNECTION=log"
    ""
    'VITE_APP_NAME="${APP_NAME}"'
) | Set-Content -Path ".env" -Encoding UTF8

php artisan key:generate --force
php artisan jwt:secret --force 2>$null

Write-Step "Preparing Laravel storage and caches"
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)M" /T | Out-Null
icacls "bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)M" /T | Out-Null
php artisan storage:link 2>$null
php artisan migrate --force
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache


Write-Step "Configuring IIS site"
Copy-Item (Join-Path $ScriptDir "web.config") (Join-Path $AppDir "public\web.config") -Force

if (Test-Path "IIS:\Sites\$SiteName") {
    Remove-Website -Name $SiteName
}

New-Website -Name $SiteName -Port 80 -PhysicalPath (Join-Path $AppDir "public") -Force | Out-Null
Set-ItemProperty "IIS:\Sites\$SiteName" -Name applicationPool -Value "DefaultAppPool"

$HandlerPath = "IIS:\Sites\$SiteName\root\Handlers"
Remove-WebHandler -Name "PHP_via_FastCGI" -PSPath $HandlerPath -ErrorAction SilentlyContinue
New-WebHandler -Name "PHP_via_FastCGI" -Path "*.php" -Verb "GET,HEAD,POST" -Modules "FastCgiModule" `
    -ScriptProcessor "$PhpCgi|" -ResourceType Either -PSPath $HandlerPath | Out-Null

Write-Step "Registering scheduler and queue worker tasks"
$PhpExe = (Get-Command php).Source
$SchedulerTask = "PRISM Scheduler"
$WorkerTask = "PRISM Queue Worker"

schtasks /Delete /TN $SchedulerTask /F 2>$null | Out-Null
schtasks /Create /TN $SchedulerTask /SC MINUTE /MO 1 /TR "`"$PhpExe`" `"$AppDir\artisan`" schedule:run" /RU SYSTEM /F | Out-Null

schtasks /Delete /TN $WorkerTask /F 2>$null | Out-Null
schtasks /Create /TN $WorkerTask /SC ONSTART /TR "`"$PhpExe`" `"$AppDir\artisan`" queue:work database --sleep=3 --tries=3 --timeout=120" /RU SYSTEM /F | Out-Null
schtasks /Run /TN $WorkerTask | Out-Null

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "PRISM is live at: $AppUrl"
Write-Host "Provision the initial administrator using private PRISM_ADMIN_EMAIL and PRISM_ADMIN_PASSWORD settings."
Write-Host "Run php artisan db:seed after configuring the private bootstrap settings."
Write-Host "========================================" -ForegroundColor Green
