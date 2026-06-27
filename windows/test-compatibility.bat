@echo off
REM ============================================================================
REM Corex Windows Compatibility Test
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0.."

echo.
echo ============================================================================
echo Corex AI Development Platform - Windows Compatibility Test
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

REM Determine test category from command line argument
set CATEGORY=All
if not "%1"=="" set CATEGORY=%1

REM Run the PowerShell compatibility test
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0TestWindowsCompatibility.ps1" -TestCategory %CATEGORY%

set EXITCODE=%errorlevel%

echo.
echo Test results saved to: logs\windows-compatibility-test.log
echo.

if %EXITCODE% equ 0 (
    echo Your system is ready for Corex!
) else (
    echo Please fix the issues above before running Corex.
)

echo.
pause
exit /b %EXITCODE%
