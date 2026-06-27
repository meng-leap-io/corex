@echo off
title Corex Platform
cd /d "%~dp0"
powershell.exe -ExecutionPolicy Bypass -NoProfile -File "%~dp0start-corex.ps1" -Stop
echo Corex services stopped.
timeout /t 3 /nobreak >nul
