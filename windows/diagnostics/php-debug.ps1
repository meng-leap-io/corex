#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    PHP Windows Diagnostic Script

.DESCRIPTION
    Debugs PHP-specific Windows issues: extension loading, thread safety,
    file locking, memory limits, timezone configuration, date/time formats,
    and COM/DLL dependencies.

.PARAMETER PhpPath
    Path to PHP executable. Auto-detects if not provided.

.PARAMETER OutputDir
    Directory for diagnostic output.

.EXAMPLE
    PS> .\php-debug.ps1
    PS> .\php-debug.ps1 -PhpPath "C:\tools\php\php.exe"
#>

param(
    [string]$PhpPath,
    [string]$OutputDir
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $PSCommandPath
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir '..\..')
$LogDir = $OutputDir ? $OutputDir : (Join-Path $ScriptDir '..\logs' "php-debug-$(Get-Date -Format 'yyyyMMdd-HHmmss')")
$ReportFile = Join-Path $LogDir 'php-debug.txt'
$JsonFile = Join-Path $LogDir 'php-debug.json'

New-Item -ItemType Directory -Path $LogDir -Force | Out-Null

function Write-Result { param([string]$M, [string]$L='Info') $c=@{Info='Gray';Ok='Green';Warn='Yellow';Fail='Red';H='Cyan'} [$L]; Write-Host "$(@{Info='  ';Ok='✓ ';Warn='⚠ ';Fail='✗ ';H='──'}[$L])$M" -ForegroundColor $c }
function H { param([string]$M) Write-Result $M H; "" }

# ──────────────────────────────────────────────────────────────────────────
# 1. Find PHP
# ──────────────────────────────────────────────────────────────────────────
H "1. PHP Discovery"

$data = @{ php_path = $null; version = $null; errors = @(); warnings = @(); passed = @() }
$errors = $data.errors; $warnings = $data.warnings; $passed = $data.passed

try {
    $php = if ($PhpPath) { $PhpPath } else { (Get-Command php -ErrorAction SilentlyContinue).Source }
    if (-not $php -and (Test-Path "$ProjectRoot\windows\packaging\tools\php\php.exe")) {
        $php = "$ProjectRoot\windows\packaging\tools\php\php.exe"
    }
    if (-not $php) {
        $paths = @("$env:ProgramFiles\php\php.exe", "$env:LOCALAPPDATA\Programs\php\php.exe", "C:\tools\php\php.exe", "C:\PHP\php.exe")
        $php = $paths | Where-Object { Test-Path $_ } | Select-Object -First 1
    }
    if (-not $php) { throw "PHP not found. Download from https://windows.php.net/download/ or use bundled build." }

    $data.php_path = (Resolve-Path $php).Path
    Write-Result "PHP found: $($data.php_path)" Ok

    $version = & $php --version 2>&1 | Select-Object -First 1
    $data.version = "$version"
    Write-Result "Version: $version" Ok
} catch {
    Write-Result "PHP not found: $_" Fail
    $errors += "PHP not found or not in PATH. Download PHP for Windows."
    $data | ConvertTo-Json -Depth 5 | Out-File $JsonFile -Encoding utf8
    return
}

# ──────────────────────────────────────────────────────────────────────────
# 2. PHP Info
# ──────────────────────────────────────────────────────────────────────────
H "2. PHP Configuration"

$info = & $php -i 2>&1

# Architecture
$arch = $info | Select-String 'Architecture\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
if ($arch -eq 'x64') { Write-Result "Architecture: x64 (correct)" Ok } else { Write-Result "Architecture: $arch (use x64)" Warn; $warnings += "PHP architecture: $arch. Use x64 for Windows." }
$data.arch = $arch

# Thread safety
$ts = $info | Select-String 'Thread Safety\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.thread_safety = $ts
if ($ts -eq 'enabled') {
    Write-Result "Thread safety: ENABLED (nts recommended)" Warn
    $warnings += "PHP has thread safety enabled. Use nts (non-thread-safe) for better performance with PHP-FPM."
} else {
    Write-Result "Thread safety: disabled (nts) ✓" Ok
    $passed += "PHP nts build"
}

# PHP SAPI
$sapi = $info | Select-String 'Server API\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.sapi = $sapi
Write-Result "SAPI: $sapi" Ok

# Memory limit
$memLimit = $info | Select-String 'memory_limit\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.memory_limit = $memLimit
if ($memLimit -match '(\d+)M') {
    $memMB = [int]$matches[1]
    if ($memMB -lt 128) {
        Write-Result "Memory limit: $memLimit (low)" Warn
        $warnings += "PHP memory_limit is $memLimit. Set to 256M or -1 in php.ini."
    } else {
        Write-Result "Memory limit: $memLimit ✓" Ok
        $passed += "PHP memory_limit ≥ 128M"
    }
} elseif ($memLimit -eq '-1') {
    Write-Result "Memory limit: unlimited ✓" Ok
} else {
    Write-Result "Memory limit: $memLimit" Info
}

# Max execution time
$maxExec = $info | Select-String 'max_execution_time\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.max_execution_time = $maxExec
if ($maxExec -ne '0' -and [int]$maxExec -lt 120) {
    Write-Result "max_execution_time: ${maxExec}s (low for AI workloads)" Warn
    $warnings += "Set max_execution_time = 300 in php.ini for AI Gateway requests."
} else {
    Write-Result "max_execution_time: ${maxExec}s ✓" Ok
}

# Max input time
$maxInput = $info | Select-String 'max_input_time\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.max_input_time = $maxInput
Write-Result "max_input_time: ${maxInput}s" Info

# Post max size
$postMax = $info | Select-String 'post_max_size\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.post_max_size = $postMax
Write-Result "post_max_size: $postMax" Info

# Upload max filesize
$uploadMax = $info | Select-String 'upload_max_filesize\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.upload_max_filesize = $uploadMax
Write-Result "upload_max_filesize: $uploadMax" Info

# ──────────────────────────────────────────────────────────────────────────
# 3. Extensions
# ──────────────────────────────────────────────────────────────────────────
H "3. Required Extensions"

$requiredExts = @(
    @{Name='pdo_pgsql'; Type='ext'; Desc='PostgreSQL driver'},
    @{Name='mbstring'; Type='ext'; Desc='Multibyte strings'},
    @{Name='openssl'; Type='ext'; Desc='HTTPS/TLS'},
    @{Name='json'; Type='ext'; Desc='JSON'},
    @{Name='redis'; Type='ext'; Desc='Redis cache'},
    @{Name='tokenizer'; Type='ext'; Desc='Laravel tokenizer'},
    @{Name='xml'; Type='ext'; Desc='XML parsing'},
    @{Name='curl'; Type='ext'; Desc='HTTP requests'},
    @{Name='gd'; Type='ext'; Desc='Image processing'},
    @{Name='fileinfo'; Type='ext'; Desc='File type detection'},
    @{Name='bcmath'; Type='ext'; Desc='Math operations'},
    @{Name='ctype'; Type='ext'; Desc='Character type checks'},
    @{Name='filter'; Type='ext'; Desc='Input filtering'},
    @{Name='session'; Type='ext'; Desc='Session support'},
    @{Name='zip'; Type='ext'; Desc='Compression'},
    @{Name='com_dotnet'; Type='ext'; Desc='COM/DCOM Windows API'}
)

$extDir = $info | Select-String 'extension_dir\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.extension_dir = $extDir
Write-Result "Extension dir: $extDir" Info

if ($extDir -and (Test-Path $extDir)) {
    $dlls = Get-ChildItem "$extDir\*.dll" | Select-Object Name
    Write-Result "Found $($dlls.Count) extension DLLs" Info
    $data.extension_dlls = @($dlls.Name)
}

$loaded = @($info | Select-String '^\s+([\w\.]+)\s+$' | ForEach-Object { $_.Matches[0].Groups[1].Value.Trim() })
$data.loaded_extensions = $loaded
$missingExts = @()

foreach ($ext in $requiredExts) {
    $found = $loaded -contains $ext.Name -or $info -match [regex]::Escape($ext.Name)
    if ($found) {
        Write-Result "Extension: $($ext.Name) ($($ext.Desc))" Ok
        $passed += "PHP extension $($ext.Name) loaded"
    } else {
        Write-Result "Extension: $($ext.Name) NOT LOADED ($($ext.Desc))" Fail
        $errors += "PHP extension $($ext.Name) is missing. Enable in php.ini: extension=$($ext.Name)"
        $missingExts += $ext.Name
    }
}

$data.missing_extensions = $missingExts

# ──────────────────────────────────────────────────────────────────────────
# 4. Timezone & Date
# ──────────────────────────────────────────────────────────────────────────
H "4. Timezone & Date Configuration"

$tz = $info | Select-String 'date\.timezone\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
if ($tz -eq 'no value' -or -not $tz) {
    Write-Result "Timezone: NOT SET (defaults to UTC)" Fail
    $errors += "PHP timezone not set. Add date.timezone = 'UTC' in php.ini. Incorrect timezone causes date/time bugs in Laravel."
    $data.timezone = 'NOT SET'
} else {
    Write-Result "Timezone: $tz" Ok
    $data.timezone = $tz
    $passed += "PHP timezone set to $tz"
}

$dflTz = $info | Select-String 'Default timezone\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.default_timezone = $dflTz
Write-Result "Default timezone: $dflTz" Info

# Timezone database
$tzDb = $info | Select-String 'Timezone Database\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.timezone_database = $tzDb
Write-Result "Timezone DB: $tzDb" Info

# Check Windows timezone sync
$winTz = (Get-Timezone).Id
$phpTz = if ($tz -and $tz -ne 'no value') { $tz } else { 'UTC' }
Write-Result "Windows TZ: $winTz | PHP TZ: $phpTz" Info
if ($winTz -ne $phpTz -and $phpTz -eq 'UTC') {
    Write-Result "Timezone mismatch: PHP uses UTC, Windows uses $winTz" Warn
    $warnings += "PHP uses UTC but Windows timezone is $winTz. This can cause timestamp inconsistencies. Set date.timezone = '$winTz' in php.ini."
}

# Date formatting
$dateTest = & $php -r "echo date('Y-m-d H:i:s T');" 2>&1
Write-Result "Date test: $dateTest" Info

# ──────────────────────────────────────────────────────────────────────────
# 5. File Locking
# ──────────────────────────────────────────────────────────────────────────
H "5. File Locking & Cache"

$tmpDir = $env:TMP
foreach ($dir in @($tmpDir, "$ProjectRoot\backend\storage\framework\cache", "$ProjectRoot\backend\bootstrap\cache", "$ProjectRoot\backend\storage\logs")) {
    if (Test-Path $dir) {
        try {
            $testFile = "$dir\.php-lock-test"
            $fh = [System.IO.File]::Open($testFile, 'OpenOrCreate', 'ReadWrite', 'None')
            $fh.Close()
            Remove-Item $testFile -Force
            Write-Result "Lock test: $dir (exclusive lock OK)" Ok
            $passed += "File locking works in $dir"
        } catch {
            Write-Result "Lock test: $dir FAILED: $_" Fail
            $errors += "File locking failed in $dir. Check antivirus exclusions or permissions."
        }
    } else {
        Write-Result "Lock test: $dir (not found)" Warn
    }
}

# Check open_basedir
$obd = $info | Select-String 'open_basedir\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
if ($obd -and $obd -ne 'no value') {
    Write-Result "open_basedir: $obd (may restrict file access)" Warn
    $warnings += "open_basedir is set. This restricts file system access. Ensure project path is included."
}
$data.open_basedir = $obd

# ──────────────────────────────────────────────────────────────────────────
# 6. Windows-Specific COM/DLL
# ──────────────────────────────────────────────────────────────────────────
H "6. Windows-Specific Checks"

# Test com_dotnet
$comTest = & $php -r "echo class_exists('COM') ? 'COM:yes' : 'COM:no'; echo '|'; echo class_exists('DOTNET') ? 'DOTNET:yes' : 'DOTNET:no';" 2>&1
$data.com_dotnet = "$comTest"
if ($comTest -match 'COM:yes') {
    Write-Result "COM support: AVAILABLE (com_dotnet)" Ok
    $passed += "PHP COM support available"
} else {
    Write-Result "COM support: NOT AVAILABLE" Warn
    $warnings += "com_dotnet extension not loaded. Windows COM APIs (shortcuts, WSH) will fail. Enable extension=com_dotnet in php.ini."
}

# Check if php_com_dotnet.dll exists in ext dir
if ($extDir -and (Test-Path "$extDir\php_com_dotnet.dll")) {
    Write-Result "php_com_dotnet.dll found in extension dir ✓" Ok
} else {
    Write-Result "php_com_dotnet.dll NOT found (may be built-in)" Info
}

# Check for MSVC runtime
try {
    $vcRedist = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\*' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($vcRedist) {
        Write-Result "VC++ Redistributable: $($vcRedist.DisplayName)" Ok
    } else {
        Write-Result "VC++ Redistributable: checking alternate path..." Info
        $vcPath = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*' -ErrorAction SilentlyContinue |
            Where-Object { $_.DisplayName -match 'Visual C\+\+.*2015-2022|Visual C\+\+.*2022|x64' } | Select-Object -First 1
        if ($vcPath) {
            Write-Result "VC++ Redistributable: $($vcPath.DisplayName)" Ok
        } else {
            Write-Result "VC++ Redistributable: NOT FOUND (required for PHP)" Warn
            $warnings += "Visual C++ Redistributable for Visual Studio 2015-2022 x64 required. Download from https://aka.ms/vs/17/release/vc_redist.x64.exe"
        }
    }
} catch { }

# PHP ini location
$iniPath = $info | Select-String 'Loaded Configuration File\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$scannedDirs = $info | Select-String 'Scan this dir for additional \.ini files\s+=>\s+(\S+)' | ForEach-Object { $_.Matches[0].Groups[1].Value }
$data.php_ini_path = $iniPath
$data.scanned_ini_dirs = $scannedDirs
Write-Result "php.ini: $iniPath" Info
Write-Result "Scanned dirs: $scannedDirs" Info

# Check for multiple php.ini files (common Windows issue)
$iniCount = Get-ChildItem -Path (Split-Path $iniPath -Parent) -Filter 'php.ini' -Recurse -ErrorAction SilentlyContinue | Measure-Object | Select-Object -ExpandProperty Count
if ($iniCount -gt 1) {
    Write-Result "Multiple php.ini files found ($iniCount) — may cause confusion" Warn
    $warnings += "Multiple php.ini files found. Ensure the correct one is loaded."
}

# ──────────────────────────────────────────────────────────────────────────
# 7. Laravel-Specific Tests
# ──────────────────────────────────────────────────────────────────────────
H "7. Laravel-Specific Checks"

$backendPath = Join-Path $ProjectRoot 'backend'
if (Test-Path "$backendPath\artisan") {
    $envFile = "$backendPath\.env"
    $envPath = "$backendPath\bootstrap\cache"
    $viewPath = "$backendPath\storage\framework\views"
    $cachePath = "$backendPath\storage\framework\cache"

    foreach ($path in @($envFile, $envPath, $viewPath, $cachePath, "$backendPath\storage\logs")) {
        if (-not (Test-Path $path)) {
            Write-Result "Missing: $path" Warn
            if ($path -match '\.env$') {
                $warnings += ".env file missing at $path. Copy from .env.example."
            }
        }
    }

    # Test artisan command
    try {
        $artisanVer = & $php "$backendPath\artisan" --version 2>&1
        if ($artisanVer -match 'Laravel Framework') {
            Write-Result "Laravel: $artisanVer" Ok
        } else {
            Write-Result "artisan --version output: $artisanVer" Info
        }
    } catch {
        Write-Result "artisan --version FAILED: $_" Fail
        $errors += "Laravel artisan not working. Run 'composer install' in the backend directory."
    }

    # Check for config cache issues
    $configCache = "$backendPath\bootstrap\cache\config.php"
    if (Test-Path $configCache) {
        Write-Result "Config cache exists (remember to clear after env changes)" Info
        $data.config_cached = $true
    }
} else {
    Write-Result "Backend path missing artisan: $backendPath" Warn
    $warnings += "Artisan not found at $backendPath. Ensure repository is complete."
}

# ──────────────────────────────────────────────────────────────────────────
# Report
# ──────────────────────────────────────────────────────────────────────────
H "PHP DIAGNOSTIC SUMMARY"

Write-Host "Errors: $($errors.Count)" -ForegroundColor $(if ($errors.Count -gt 0) { 'Red' } else { 'Green' })
Write-Host "Warnings: $($warnings.Count)" -ForegroundColor $(if ($warnings.Count -gt 0) { 'Yellow' } else { 'Green' })
Write-Host "Passed: $($passed.Count)" -ForegroundColor Green

if ($errors.Count -gt 0) {
    Write-Host "`nErrors:" -ForegroundColor Red
    $errors | ForEach-Object { Write-Host "  ✗ $_" -ForegroundColor Red }
}
if ($warnings.Count -gt 0) {
    Write-Host "`nWarnings:" -ForegroundColor Yellow
    $warnings | ForEach-Object { Write-Host "  ⚠ $_" -ForegroundColor Yellow }
}

# Generate php-fix.ps1
$fixScript = Join-Path $LogDir 'php-fix.ps1'
@"
# PHP Windows Fixes
`$ErrorActionPreference = 'Stop'

`$iniPath = "$iniPath"
Write-Host "PHP config: `$iniPath" -ForegroundColor Cyan

"@ | Out-File $fixScript -Encoding utf8

foreach ($err in $errors) {
    "`n# Fix: $err" | Out-File $fixScript -Encoding utf8 -Append
}
"`nWrite-Host 'Done. Restart the PHP service after applying fixes.' -ForegroundColor Green" | Out-File $fixScript -Encoding utf8 -Append

$data | ConvertTo-Json -Depth 5 | Out-File $JsonFile -Encoding utf8

Write-Host "`nReport: $ReportFile" -ForegroundColor Gray
Write-Host "JSON: $JsonFile" -ForegroundColor Gray
Write-Host "Fix script: $fixScript" -ForegroundColor Gray

exit ($errors.Count -gt 0 ? 1 : 0)
