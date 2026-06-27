@echo off
REM ============================================================================
REM Corex Service Management - Uninstall Windows Service
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0.."

echo.
echo ============================================================================
echo Corex AI Development Platform - Windows Service Uninstaller
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

echo.
echo WARNING: This will uninstall the Corex Windows service.
echo All containers will be stopped and removed.
echo.

choice /C YN /M "Are you sure you want to continue?"

if errorlevel 2 (
    echo Operation cancelled.
    pause
    exit /b 0
)

if errorlevel 1 (
    REM Run the PowerShell service wrapper
    echo Uninstalling Windows service...
    echo.
    
    powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0CorexServiceWrapper.ps1" -Action Uninstall
    
    if %errorlevel% neq 0 (
        echo.
        echo ERROR: Failed to uninstall service.
        pause
        exit /b 1
    )
    
    echo.
    echo Service uninstalled successfully!
    echo.
)

pause
