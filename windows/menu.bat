@echo off
REM ============================================================================
REM Corex Windows Service Suite - Menu
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0.."

:MENU
cls
echo.
echo ============================================================================
echo          Corex AI Development Platform - Windows Service Manager
echo ============================================================================
echo.
echo Available commands:
echo.
echo   1. Start services              (start.bat)
echo   2. Stop services               (stop.bat)
echo   3. Restart services            (restart.bat)
echo   4. Check status                (status.bat)
echo   5. Health check                (health.bat)
echo   6. View logs                   (logs.bat)
echo.
echo   7. Run compatibility test      (test-compatibility.bat)
echo   8. Install Task Scheduler jobs (task-install.bat)
echo   9. List scheduled tasks        (task-list.bat)
echo  10. Test scheduled tasks        (task-test.bat)
echo.
echo  11. Install Windows service     (install-service.bat)
echo  12. Uninstall Windows service   (uninstall-service.bat)
echo.
echo  13. Open documentation          (README.md)
echo  14. Open Windows Task Scheduler (tasksched.msc)
echo  15. Open Services Manager       (services.msc)
echo.
echo   0. Exit
echo.

choice /C 0123456789AB /N /M "Select option: " /T 300 /D 0

if errorlevel 16 goto EXIT
if errorlevel 15 start services.msc && goto MENU
if errorlevel 14 start tasksched.msc && goto MENU
if errorlevel 13 start README.md && goto MENU
if errorlevel 12 call uninstall-service.bat && goto MENU
if errorlevel 11 call install-service.bat && goto MENU
if errorlevel 10 call task-test.bat && goto MENU
if errorlevel 9 call task-list.bat && goto MENU
if errorlevel 8 call task-install.bat && goto MENU
if errorlevel 7 call test-compatibility.bat && goto MENU
if errorlevel 6 call logs.bat && goto MENU
if errorlevel 5 call health.bat && goto MENU
if errorlevel 4 call status.bat && goto MENU
if errorlevel 3 call restart.bat && goto MENU
if errorlevel 2 call stop.bat && goto MENU
if errorlevel 1 call start.bat && goto MENU
if errorlevel 0 goto EXIT

:EXIT
exit /b 0
