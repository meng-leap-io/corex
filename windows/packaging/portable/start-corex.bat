@echo off
title Corex Platform
cd /d "%~dp0"

:: Check for existing instance
tasklist /FI "WINDOWTITLE eq Corex Platform" 2>nul | findstr /I "powershell.exe" >nul
if %ERRORLEVEL% equ 0 (
    echo Corex is already running.
    timeout /t 3 /nobreak >nul
    exit /b
)

:: Start services with PowerShell (hidden)
start "" /B powershell.exe -ExecutionPolicy Bypass -NoProfile -File "%~dp0start-corex.ps1" -StartElectron

:: Open browser after a short delay
timeout /t 5 /nobreak >nul
start http://127.0.0.1

exit /b
