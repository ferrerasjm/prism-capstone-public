# check-mail.ps1 - diagnose OTP / transactional email health on the host.
# Read-only. Run from the project root:  powershell -ExecutionPolicy Bypass -File deploy\windows\check-mail.ps1

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $root

function Section($t) { Write-Host "`n=== $t ===" -ForegroundColor Cyan }

Section "Live mail config"
php artisan tinker --execute="dump(config('mail.default'), config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.username'), config('mail.from'), config('auth.mobile_otp.mail_enabled'), config('auth.login_otp.mail_enabled'));"

Section "Queue state"
php artisan tinker --execute="echo 'pending=' . DB::table('jobs')->count() . '  failed=' . DB::table('failed_jobs')->count();"

Section "Last failed job"
php artisan tinker --execute="`$j = DB::table('failed_jobs')->latest('id')->first(); echo `$j ? (`$j->failed_at . ' :: ' . substr(`$j->exception, 0, 700)) : 'none';"

Section "Queue worker process"
$w = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object { $_.CommandLine -match 'queue:work' }
if ($w) { $w | Select-Object ProcessId, CreationDate, CommandLine | Format-List }
else    { Write-Host "NOT RUNNING - start-prism-services.ps1 to launch it" -ForegroundColor Yellow }

Section "Recent mail errors in laravel.log"
if (Test-Path storage/logs/laravel.log) {
    Get-Content storage/logs/laravel.log -Tail 80 |
        Select-String -Pattern 'OTP|mail|smtp|550|535|sender|Brevo|Mailer' -Context 0,2
} else { Write-Host "no laravel.log" }

Section "Send a real test email (synchronous, bypasses the queue)"
$to = Read-Host "Send test to (blank to skip)"
if ($to) {
    php artisan tinker --execute="try { Mail::raw('check-mail.ps1 ' . now(), fn(`$m) => `$m->to('$to')->subject('PRISM mail check')); echo 'OK - accepted by SMTP, check Brevo Logs + inbox'; } catch (\Throwable `$e) { echo 'FAILED :: ' . `$e->getMessage(); }"
}

Write-Host "`nDone. If a queued OTP failed but the sync test says OK, the worker is stale - restart it." -ForegroundColor Green
