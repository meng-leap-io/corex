@echo off
REM ============================================================================
REM Corex Web-to-Desktop Migration Runner
REM One-click migration from web/Linux to Windows desktop.
REM ============================================================================
setlocal enabledelayedexpansion

cd /d "%~dp0"

:MENU
cls
echo.
echo ============================================================================
echo      Corex Web-to-Desktop Migration Tool
echo ============================================================================
echo.
echo  Project: %~dp0..\..
echo.
echo  Choose migration phase:
echo.
echo    [1] Full Migration (Data + Services + Config + Test)
echo    [2] Data Only (backup, SQLite, user data, paths, line-endings, env)
echo    [3] Services Only (firewall, SSL, PATH, Windows services)
echo    [4] Config Only (PHP, Nginx, Redis for Windows)
echo    [5] Test Only (services, ports, database, gateway, permissions)
echo    [6] Rollback (restore from backup, remove services, revert PATH)
echo    [7] Exit
echo.
set /p choice="Select option [1-7]: "

if "%choice%"=="1" goto full
if "%choice%"=="2" goto data
if "%choice%"=="3" goto services
if "%choice%"=="4" goto config
if "%choice%"=="5" goto test
if "%choice%"=="6" goto rollback
if "%choice%"=="7" goto end
goto menu

:full
echo.
echo ============================================================================
echo  Running full migration...
echo ============================================================================
echo  This will: backup data, migrate to SQLite, convert paths,
echo  setup services, configure firewall/SSL/PATH, and run all tests.
echo.
echo  Estimated time: 10-30 minutes depending on data size.
echo.
echo  PREREQUISITES:
echo    - PostgreSQL running (for data export)
echo    - Admin privileges
echo    - PowerShell 5.1+
echo.
pause
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0migrate.ps1" -Phase All
echo.
echo Migration complete. Press any key to view logs.
pause
start "" "%~dp0..\logs"
goto end

:data
echo.
echo ============================================================================
echo  Phase 1: Data Migration
echo ============================================================================
echo.
echo Running: backup, SQLite migration, user data, path conversion,
echo line endings, environment variables.
echo.
pause
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0migrate.ps1" -Phase Data
echo.
pause
start "" "%~dp0..\logs"
goto end

:services
echo.
echo ============================================================================
echo  Phase 2: Service Setup
echo ============================================================================
echo.
echo Running: firewall rules, SSL certificates, PATH config, Windows services.
echo Requires Administrator privileges.
echo.
pause
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0migrate.ps1" -Phase Services
echo.
pause
start "" "%~dp0..\logs"
goto end

:config
echo.
echo ============================================================================
echo  Phase 3: Configuration Migration
echo ============================================================================
echo.
echo Running: PHP.ini optimization, Nginx config, Redis config for Windows.
echo.
pause
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0migrate.ps1" -Phase Config
echo.
pause
start "" "%~dp0..\logs"
goto end

:test
echo.
echo ============================================================================
echo  Phase 4: Validation
echo ============================================================================
echo.
echo Running: service status, port checks, database test,
echo AI Gateway test, file permissions.
echo.
pause
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0migrate.ps1" -Phase Test
echo.
pause
start "" "%~dp0..\logs"
goto end

:rollback
echo.
echo ============================================================================
echo  Rollback
echo ============================================================================
echo.
echo This will restore from backup and undo all migration changes:
echo   - Stop and remove all Corex Windows services
echo   - Remove firewall rules
echo   - Remove PATH entries
echo   - Restore backed up data
echo.
set /p confirm="Type YES to confirm rollback: "
if /i not "!confirm!"=="YES" (
    echo Rollback cancelled.
    pause
    goto menu
)
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0migrate.ps1" -Phase Rollback
echo.
echo Rollback complete.
pause
goto end

:end
echo.
echo Thank you for using Corex Migration Tool.
echo.
