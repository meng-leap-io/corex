@echo off
REM ============================================================================
REM WiX Toolset Build Script for Corex Installer
REM 
REM Prerequisites:
REM   - WiX Toolset 3.14 or later (https://wixtoolset.org/)
REM   - Visual Studio Build Tools or Visual Studio
REM
REM Build instructions:
REM   1. Download and install WiX from https://wixtoolset.org/
REM   2. Run this script from the directory containing .wxs files
REM   3. Output: Corex-Setup.msi
REM ============================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0"

echo.
echo ============================================================================
echo Corex Installer Build - WiX Toolset
echo ============================================================================
echo.

REM Check for WiX installation
where candle.exe >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: WiX Toolset is not installed or not in PATH
    echo.
    echo Download from: https://wixtoolset.org/
    echo.
    pause
    exit /b 1
)

echo Building Corex installer...
echo.

REM Create object directory
if not exist "obj\" mkdir obj
if not exist "bin\" mkdir bin

REM Compile .wxs files to object files
echo Compiling WiX source files...
candle.exe ^
    -o obj\ ^
    -d SourceDir=..\..\.. ^
    Product.wxs ^
    Files.wxs

if %errorlevel% neq 0 (
    echo ERROR: Compilation failed
    pause
    exit /b 1
)

echo Compilation successful
echo.

REM Link object files to create MSI
echo Linking object files...
light.exe ^
    -out bin\Corex-Setup.msi ^
    -cultures:en-us ^
    obj\*.wixobj

if %errorlevel% neq 0 (
    echo ERROR: Linking failed
    pause
    exit /b 1
)

echo.
echo ============================================================================
echo Build successful!
echo ============================================================================
echo.
echo Output: bin\Corex-Setup.msi
echo.
echo Next steps:
echo   1. Test the installer: msiexec /i bin\Corex-Setup.msi
echo   2. For silent install: msiexec /i bin\Corex-Setup.msi /quiet /qn
echo   3. For uninstall: msiexec /x bin\Corex-Setup.msi /quiet
echo.
pause
