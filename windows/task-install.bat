@echo off
REM ============================================================================
REM Corex Task Scheduler - Install Tasks
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0.."

echo.
echo ============================================================================
echo Corex AI Development Platform - Task Scheduler Setup
echo ============================================================================
echo.

REM Check for Administrator privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ERROR: This script must be run as Administrator.
    echo.
    echo Right-click on this file and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

REM Check for PowerShell
powershell -Version 5.1 >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PowerShell 5.1 or higher is required.
    pause
    exit /b 1
)

REM Run the PowerShell task scheduler setup
echo Setting up Windows Task Scheduler...
echo.

powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0SetupTaskScheduler.ps1" -Action Install

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Failed to setup tasks.
    pause
    exit /b 1
)

echo.
echo Tasks installed successfully!
echo.
echo Next steps:
echo   - View tasks: tasksched.msc (or run: task-list.bat)
echo   - Test tasks: task-test.bat
echo   - View logs: %APPDATA%\Logs\Corex\task-scheduler.log
echo.

pause
