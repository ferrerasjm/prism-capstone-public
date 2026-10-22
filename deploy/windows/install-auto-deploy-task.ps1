#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Register a Windows Task Scheduler job that auto-pulls PRISM every N minutes.

.DESCRIPTION
  Run once on the hosting VPS (elevated PowerShell). After this, you can
  uninstall Cursor from the host. Local PC: commit + push; host pulls automatically.

.EXAMPLE
  .\install-auto-deploy-task.ps1
  .\install-auto-deploy-task.ps1 -IntervalMinutes 5 -SkipNpmBuild
#>
[CmdletBinding()]
param(
    [string]$AppDir = "C:\laragon\www\prism",
    [string]$Branch = "main",
    [int]$IntervalMinutes = 5,
    [string]$TaskName = "PRISM Auto Deploy",
    [switch]$SkipNpmBuild
)

$ErrorActionPreference = "Stop"

$ScriptPath = Join-Path $AppDir "deploy\windows\auto-deploy-prism.ps1"
if (-not (Test-Path $ScriptPath)) {
    throw "Deploy script not found: $ScriptPath`nPull the main branch first so deploy/windows exists on the host."
}

$args = "-NoProfile -ExecutionPolicy Bypass -File `"$ScriptPath`" -Branch $Branch -AppDir `"$AppDir`""
if ($SkipNpmBuild) {
    $args += " -SkipNpmBuild"
}

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $args
# Windows rejects TimeSpan.MaxValue for RepetitionDuration; use ~10 years instead.
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes $IntervalMinutes) `
    -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 15)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger `
    -Settings $settings -Principal $principal -Force | Out-Null

Write-Host ""
Write-Host "Scheduled task registered: $TaskName" -ForegroundColor Green
Write-Host "  Interval : every $IntervalMinutes minute(s)"
Write-Host "  Script   : $ScriptPath"
Write-Host "  Branch   : $Branch"
Write-Host "  Log file : $AppDir\storage\logs\auto-deploy.log"
Write-Host ""
Write-Host "Workflow:"
Write-Host "  1. On your PC: edit in Cursor -> git commit -> git push origin $Branch"
Write-Host "  2. Host pulls automatically within ~$IntervalMinutes minute(s)"
Write-Host "  3. You can uninstall Cursor from the hosting server"
Write-Host ""
Write-Host "Manual run anytime:"
Write-Host "  schtasks /Run /TN `"$TaskName`""
