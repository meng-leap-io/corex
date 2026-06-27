@echo off
REM ============================================================================
REM Corex Service Management - Install Windows Service
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0.."

echo.
echo ============================================================================
echo Corex AI Development Platform - Windows Service Installer
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

REM Check for NSSM
nssm status >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ERROR: NSSM (Non-Sucking Service Manager) is not installed or not in PATH.
    echo.
    echo NSSM is required to install Corex as a Windows service.
    echo.
    echo Download NSSM from: https://nssm.cc/download
    echo.
    echo Installation steps:
    echo   1. Download NSSM (latest version)
    echo   2. Extract to: C:\Program Files\NSSM
    echo   3. Add to PATH: setx PATH "%%PATH%%;C:\Program Files\NSSM\win64"
    echo   4. Restart this command prompt
    echo   5. Run this script again
    echo.
    pause
    exit /b 1
)

REM Run the PowerShell service wrapper
echo Installing Corex as Windows service...
echo.

powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0CorexServiceWrapper.ps1" -Action Install

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Failed to install service.
    pause
    exit /b 1
)

echo.
echo Service installed successfully!
echo.
echo Next steps:
echo   - Start the service: net start CorexPlatform
echo   - Check status: status.bat
echo   - View logs: logs.bat
echo.

pause
