#Requires -Version 5.1
<#
.SYNOPSIS
  Ensure Laragon MySQL/Apache, the PRISM queue worker, and the ML classifier
  service are running. Also registers a watchdog task so this check re-runs
  on its own, not just during deploys.
#>
[CmdletBinding()]
param(
    [string]$AppDir = "C:\laragon\www\prism"
)

$ErrorActionPreference = "Continue"

# Start Laragon services if the CLI is available
$LaragonExe = "C:\laragon\laragon.exe"
if (Test-Path $LaragonExe) {
    # Apache and MySQL are laragon.exe child processes rather than Windows services,
    # so nothing restores them after a reboot unless this task does it.
    $mysqld = Get-CimInstance Win32_Process -Filter "Name = 'mysqld.exe'" -ErrorAction SilentlyContinue
    if ($mysqld) {
        Write-Host "Laragon services already running (mysqld PID $($mysqld.ProcessId -join ', '))"
    } else {
        Write-Host "mysqld not running; starting Laragon services..."
        Start-Process -FilePath $LaragonExe -ArgumentList "start" -WindowStyle Hidden
        $laragonDeadline = (Get-Date).AddSeconds(90)
        do {
            Start-Sleep -Seconds 3
            $mysqld = Get-CimInstance Win32_Process -Filter "Name = 'mysqld.exe'" -ErrorAction SilentlyContinue
        } until ($mysqld -or (Get-Date) -ge $laragonDeadline)
        if ($mysqld) {
            Write-Host "Laragon services started (mysqld PID $($mysqld.ProcessId -join ', '))"
        } else {
            # Deliberately not fatal: the queue worker and classifier checks below
            # still report their own state, which is more useful than aborting here.
            Write-Warning "Laragon did not bring up mysqld within 90s; Apache/MySQL need manual attention."
        }
    }
}

# Prefer Laragon PHP/Composer if present (matches auto-deploy-prism.ps1)
$LaragonPhp = "C:\laragon\bin\php"
if (Test-Path $LaragonPhp) {
    $env:Path = "$LaragonPhp;" + $env:Path
}

$PhpCmd = Get-Command php -ErrorAction SilentlyContinue
if (-not $PhpCmd) {
    $PhpCmd = Get-ChildItem $LaragonPhp -Recurse -Filter php.exe -ErrorAction SilentlyContinue | Select-Object -First 1
}
if (-not $PhpCmd) {
    throw "Could not locate php.exe (checked PATH and $LaragonPhp)."
}
$PhpExe = $PhpCmd.Source
if (-not $PhpExe) { $PhpExe = $PhpCmd.FullName }

# Start one queue worker if none is running
$queueRunning = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match "queue:work" }

if (-not $queueRunning) {
    Write-Host "Starting queue worker..."
    Start-Process -FilePath $PhpExe -ArgumentList "artisan","queue:work","database","--sleep=3","--tries=3","--timeout=120" `
        -WorkingDirectory $AppDir -WindowStyle Hidden
} else {
    Write-Host "Queue worker already running (PID $($queueRunning.ProcessId -join ', '))"
}

# Probe authenticated inference using Laravel's effective configuration. Never log tokens.
$mlConfigJson = & $PhpExe (Join-Path $AppDir "deploy\windows\ml-runtime-config.php")
if ($LASTEXITCODE -ne 0) { throw "Cannot read Laravel classifier configuration." }
$mlConfig = ($mlConfigJson -join "`n") | ConvertFrom-Json -ErrorAction Stop
$mlScript = Join-Path $AppDir "ml\scripts\serve_hybrid.py"
$mlPython = Join-Path $AppDir ".venv-ml\Scripts\python.exe"

# A classifier left running across a deploy keeps serving the code it imported at
# startup, so it answers every probe successfully while ranking offences by
# superseded rules. Comparing the served guardrail against the one on disk is what
# distinguishes "responding" from "running the deployed policy".
$expectedGuardrail = $null
$guardrailSource = Join-Path $AppDir "ml\scripts\policy_guardrails.py"
if (Test-Path $guardrailSource) {
    $guardrailMatch = Select-String -Path $guardrailSource -Pattern 'GUARDRAIL_VERSION\s*=\s*"([^"]+)"' |
        Select-Object -First 1
    if ($guardrailMatch) { $expectedGuardrail = $guardrailMatch.Matches[0].Groups[1].Value }
}

function Test-PrismPrediction {
    try {
        $headers = @{}
        if ($mlConfig.token) { $headers.Authorization = "Bearer $($mlConfig.token)" }
        $result = Invoke-RestMethod -Uri ($mlConfig.url.TrimEnd('/') + '/predict') -Method Post `
            -Headers $headers -ContentType 'application/json' -TimeoutSec 20 `
            -Body '{"text":"During lunch break, he punched another student in the face following a verbal disagreement.","top_k":4,"excluded_labels":[]}' -ErrorAction Stop
        if (-not ($result.predictions.Count -gt 0 -and $null -ne $result.model)) { return $false }
        if ($expectedGuardrail -and $result.model.guardrail_version -ne $expectedGuardrail) {
            Write-Warning "Classifier is serving guardrail $($result.model.guardrail_version); $expectedGuardrail is deployed. Treating as not ready."
            return $false
        }
        return $true
    } catch { return $false }
}
if ($mlConfig.enabled) {
    if (-not (Test-PrismPrediction)) {
        # Only restart this checkout's service. Do not kill unrelated Python processes.
        $localUrl = [uri]$mlConfig.url
        if ($localUrl.Host -notin @('127.0.0.1', 'localhost') -or $localUrl.Port -ne 8765) {
            throw "Configured remote classifier failed authenticated readiness; manual recovery required."
        }
        if (-not (Test-Path $mlPython) -or -not (Test-Path $mlScript)) {
            throw "Classifier Python environment or script is missing."
        }
        Get-CimInstance Win32_Process -Filter "Name = 'python.exe'" |
            Where-Object { $_.CommandLine -and $_.CommandLine.Contains($mlScript) } |
            ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction Stop }
        $previousToken = $env:PRISM_ML_CLASSIFIER_TOKEN
        try {
            $env:PRISM_ML_CLASSIFIER_TOKEN = [string]$mlConfig.token
            Start-Process -FilePath $mlPython -ArgumentList @('"' + $mlScript + '"') `
                -WorkingDirectory $AppDir -WindowStyle Hidden -ErrorAction Stop `
                -RedirectStandardOutput (Join-Path $AppDir "storage\logs\ml-service.out.log") `
                -RedirectStandardError (Join-Path $AppDir "storage\logs\ml-service.err.log") | Out-Null
        } finally { $env:PRISM_ML_CLASSIFIER_TOKEN = $previousToken }
        $ready = $false
        $deadline = (Get-Date).AddSeconds(180)
        do {
            Start-Sleep -Seconds 3
            $ready = Test-PrismPrediction
        } until ($ready -or (Get-Date) -ge $deadline)
        if (-not $ready) { throw "Classifier failed authenticated prediction readiness. Check ml-service.err.log." }
    }
    Write-Host "Classifier authenticated prediction ready."
} else {
    Write-Warning "Laravel classifier is disabled; keyword fallback is active."
}

# Ensure scheduler task exists (creates if missing)
$SchedulerTask = "PRISM Scheduler"
$existing = schtasks /Query /TN $SchedulerTask 2>$null
if (-not $existing) {
    schtasks /Create /TN $SchedulerTask /SC MINUTE /MO 1 `
        /TR "`"$PhpExe`" `"$AppDir\artisan`" schedule:run" /RU SYSTEM /F | Out-Null
    Write-Host "Created scheduled task: $SchedulerTask"
}

# Ensure a watchdog task exists that re-runs this script periodically, so a
# crashed queue worker or ML service gets restarted even between deploys.
$WatchdogTask = "PRISM Service Watchdog"
$existingWatchdog = schtasks /Query /TN $WatchdogTask 2>$null
if (-not $existingWatchdog) {
    $ps = (Get-Command powershell).Source
    $thisScript = $PSCommandPath
    schtasks /Create /TN $WatchdogTask /SC MINUTE /MO 5 `
        /TR "`"$ps`" -ExecutionPolicy Bypass -File `"$thisScript`" -AppDir `"$AppDir`"" /RU SYSTEM /F | Out-Null
    Write-Host "Created scheduled task: $WatchdogTask"
}

Write-Host "PRISM services check complete."
