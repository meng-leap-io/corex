@echo off
REM ============================================================================
REM Corex Service Management - Stop Services
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0.."

echo.
echo ============================================================================
echo Corex AI Development Platform - Service Stopper
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

REM Run the PowerShell service wrapper
echo Stopping services...
echo.

powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0CorexServiceWrapper.ps1" -Action Stop

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Failed to stop services.
    pause
    exit /b 1
)

echo.
echo Services stopped successfully!
echo.

pause
