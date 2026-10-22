@echo off
setlocal EnableExtensions

REM ============================================================
REM  PRISM: commit + push to main (syncs to hosting)
REM  Double-click this file, or run from a terminal in the repo.
REM ============================================================

cd /d "%~dp0..\.."
if not exist ".git" (
  cd /d "%~dp0"
)
if not exist ".git" (
  echo ERROR: Run this from the prism-capstone repo ^(put push-to-host.cmd in deploy\windows or repo root^).
  pause
  exit /b 1
)

echo.
echo ========================================
echo  PRISM sync to hosting ^(branch: main^)
echo ========================================
echo.

git status -sb
echo.

REM Optional: pass commit message as argument
REM   push-to-host.cmd "fix login button color"
set "MSG=%~1"

if "%MSG%"=="" (
  set /p MSG=Commit message: 
)

if "%MSG%"=="" (
  echo No commit message entered. Cancelled.
  pause
  exit /b 1
)

echo.
echo Staging all changes...
git add -A

git diff --cached --quiet
if %ERRORLEVEL%==0 (
  echo Nothing to commit. Checking remote...
  goto PUSH
)

echo Committing...
git commit -m "%MSG%"
if errorlevel 1 (
  echo Commit failed.
  pause
  exit /b 1
)

:PUSH
echo.
echo Pushing to origin/main...
git push origin main
if errorlevel 1 (
  echo.
  echo Push failed. Try: git pull origin main
  echo Then run this script again.
  pause
  exit /b 1
)

echo.
echo ========================================
echo  Done. Host will auto-pull soon
echo  ^(or run: schtasks /Run /TN "PRISM Auto Deploy"^)
echo ========================================
echo.
pause
endlocal
