#Requires -Version 5.1

param(
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\optimize-php-windows.log"
$ConfDir = New-Item -ItemType Directory -Path "$InstallDir\conf" -Force | Select-Object -ExpandProperty FullName

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }

Log "=== PHP Configuration for Windows ==="

$phpIniPath = "$ConfDir\php.ini"

# Find source php.ini
$sourceIni = @(
    "$InstallDir\tools\php\php.ini-development",
    "$InstallDir\tools\php\php.ini-production",
    "$InstallDir\tools\php\php.ini",
    "$env:ProgramFiles\php\php.ini",
    "C:\tools\php\php.ini",
    "C:\PHP\php.ini"
) | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $sourceIni) {
    Log "No php.ini source found. Generating from scratch..."
    $sourceIni = "$env:TMP\php-base.ini"
    @"
[PHP]
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zlib.output_compression = Off
implicit_flush = Off
serialize_precision = -1
zend.enable_gc = On
"@ | Out-File $sourceIni -Encoding ascii
}

Log "Source php.ini: $sourceIni"
Log "Target php.ini: $phpIniPath"

# Read source and apply Windows optimizations
$ini = Get-Content $sourceIni -Raw

# ── Windows-specific optimizations ──
$replacements = @{
    'memory_limit\s*=.*' = "memory_limit = 512M"
    'max_execution_time\s*=.*' = "max_execution_time = 300"
    'max_input_time\s*=.*' = "max_input_time = 300"
    'post_max_size\s*=.*' = "post_max_size = 100M"
    'upload_max_filesize\s*=.*' = "upload_max_filesize = 100M"
    'max_file_uploads\s*=.*' = "max_file_uploads = 50"
    'date.timezone\s*=.*' = 'date.timezone = "UTC"'
    'error_reporting\s*=.*' = "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT"
    'display_errors\s*=.*' = "display_errors = Off"
    'log_errors\s*=.*' = "log_errors = On"
    'error_log\s*=.*' = "error_log = $DataDir\logs\php-error.log"
    'session.save_path\s*=.*' = "session.save_path = $DataDir\sessions"
    'session.gc_maxlifetime\s*=.*' = "session.gc_maxlifetime = 1440"
    'session.cookie_httponly\s*=.*' = "session.cookie_httponly = 1"
    'session.use_strict_mode\s*=.*' = "session.use_strict_mode = 1"
    'realpath_cache_size\s*=.*' = "realpath_cache_size = 4096K"
    'realpath_cache_ttl\s*=.*' = "realpath_cache_ttl = 600"
    'opcache.enable\s*=.*' = "opcache.enable = 1"
    'opcache.memory_consumption\s*=.*' = "opcache.memory_consumption = 256"
    'opcache.max_accelerated_files\s*=.*' = "opcache.max_accelerated_files = 20000"
    'opcache.revalidate_freq\s*=.*' = "opcache.revalidate_freq = 2"
    'opcache.enable_cli\s*=.*' = "opcache.enable_cli = 1"
    'extension_dir\s*=.*' = "extension_dir = `"$InstallDir\tools\php\ext`""
    'sys_temp_dir\s*=.*' = "sys_temp_dir = `"$env:TMP`""
    'upload_tmp_dir\s*=.*' = "upload_tmp_dir = `"$env:TMP`""
}

# Apply replacements
foreach ($pattern in $replacements.Keys) {
    $replacement = $replacements[$pattern]
    if ($ini -match "(?m)^\s*;$pattern" -or $ini -notmatch "(?m)^\s*$pattern") {
        # Commented or missing — append at appropriate section
        $section = $pattern -replace '\\.*', ''
        $ini += "`n$($replacement -replace '^.*=\s*', ($pattern -replace '\\=.*', '') -replace '.*?=\s*', ($pattern -replace '\\=.*', '') + ' = ')"
        $ini = $ini -replace "(?m)$pattern", $replacement # uncomment if it exists
    } else {
        $ini = $ini -replace "(?m)$pattern", $replacement
    }
}
$ini = $ini -replace "(?m)^\s*;\s*$($replacements.Keys -join '|')", ''  # Remove commented versions

# Ensure critical extensions are enabled
$extensions = @(
    'extension=pdo_pgsql', 'extension=pdo_sqlite', 'extension=mbstring',
    'extension=openssl', 'extension=curl', 'extension=gd', 'extension=zip',
    'extension=fileinfo', 'extension=redis', 'extension=com_dotnet',
    'extension=bcmath', 'extension=ctype', 'extension=json',
    'extension=tokenizer', 'extension=xml', 'extension=xmlreader',
    'extension=xmlwriter', 'extension=filter', 'extension=session',
    'extension=opcache'
)

$iniSection = "`n[Windows Extensions]`n"
foreach ($ext in $extensions) {
    if ($ini -notmatch [regex]::Escape($ext)) {
        $iniSection += "$ext`n"
    }
}
$ini += $iniSection

# Write optimized php.ini
$ini | Out-File $phpIniPath -Encoding ascii -NoNewline

Ok "PHP configuration written: $phpIniPath"

# Create a PHP info test script
$infoFile = "$InstallDir\www\phpinfo.php"
New-Item -ItemType Directory -Path (Split-Path $infoFile -Parent) -Force | Out-Null
"<?php phpinfo();" | Out-File $infoFile -Encoding ascii
Log "PHP info: $infoFile"

# Create artisan wrapper for Windows
$artisanBat = "$InstallDir\bin\artisan.bat"
@"
@echo off
php "%~dp0..\backend\artisan" %*
"@ | Out-File $artisanBat -Encoding ascii
Ok "Artisan wrapper: $artisanBat"

Log "Log: $LogFile"
