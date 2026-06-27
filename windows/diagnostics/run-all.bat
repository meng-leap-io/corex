@echo off
REM ============================================================================
REM Corex Windows Diagnostic Runner
REM Runs all diagnostics in sequence and opens the report directory.
REM ============================================================================
setlocal enabledelayedexpansion

cd /d "%~dp0"

echo.
echo ============================================================================
echo  Corex Windows Diagnostic Suite
echo ============================================================================
echo.
echo This will run:
echo   1. System diagnostic (paths, ports, env, registry, defender, UAC)
echo   2. PHP debug (extensions, memory, timezone, COM, OpCache)
echo   3. Python debug (DLLs, asyncio, encoding, dependencies)
echo   4. Performance analysis (CPU, RAM, disk, network, tweaks)
echo   5. Error log analysis (last 24 hours)
echo.
echo Estimated time: 2-5 minutes
echo.
echo IMPORTANT: Run as Administrator
echo.
pause

REM Check admin
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ERROR: Please run as Administrator.
    echo Right-click this file and select "Run as administrator"
    pause
    exit /b 1
)

set LOGDIR=..\logs\diagnostic-%date:~-4,4%%date:~-10,2%%date:~-7,2%-%time:~0,2%%time:~3,2%%time:~6,2%
set LOGDIR=%LOGDIR: =0%
mkdir %LOGDIR% 2>nul

echo.
echo ============================================================================
echo  1. System Diagnostic
echo ============================================================================
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0diagnose.ps1"
echo.
echo Report saved to logs directory.
echo.
pause

echo.
echo ============================================================================
echo  2. PHP Debug
echo ============================================================================
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0php-debug.ps1"
echo.
pause

echo.
echo ============================================================================
echo  3. Python Debug
echo ============================================================================
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0python-debug.ps1"
echo.
pause

echo.
echo ============================================================================
echo  4. Performance Analysis (analyze only, no changes)
echo ============================================================================
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0performance.ps1"
echo.
pause

echo.
echo ============================================================================
echo  5. Error Log Analysis (last 24 hours)
echo ============================================================================
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0analyze-logs.ps1" -Hours 24
echo.
pause

echo.
echo ============================================================================
echo  All diagnostics complete!
echo ============================================================================
echo.
echo Report files are in:
echo   %CD%\..\logs\
echo.
echo See DEBUGGING.md for fixes and explanations.
echo.
pause
