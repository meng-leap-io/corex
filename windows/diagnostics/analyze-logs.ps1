#Requires -Version 5.1

<#
.SYNOPSIS
    Corex Windows Error Log Analyzer

.DESCRIPTION
    Scans Windows Event Logs, Corex service logs, PHP error logs, and Python
    tracebacks to identify and diagnose common issues.

.PARAMETER Hours
    Number of hours back to analyze. Default: 24

.PARAMETER OutputDir
    Directory for analysis report.

.EXAMPLE
    PS> .\analyze-logs.ps1
    PS> .\analyze-logs.ps1 -Hours 48
#>

param(
    [int]$Hours = 24,
    [string]$OutputDir
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $PSCommandPath
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir '..\..')
$LogDir = $OutputDir ? $OutputDir : (Join-Path $ScriptDir '..\logs' "analysis-$(Get-Date -Format 'yyyyMMdd-HHmmss')")
$ReportFile = Join-Path $LogDir 'error-analysis.txt'
$JsonFile = Join-Path $LogDir 'error-analysis.json'

New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
$since = (Get-Date).AddHours(-$Hours)
$errors = @()
$anomalies = @()
$data = @{ analysis_period_hours = $Hours; sources = @{} }

function Write-Result { param([string]$M, [string]$L='Info') $c=@{Info='Gray';Ok='Green';Warn='Yellow';Fail='Red';H='Cyan'} [$L]; Write-Host "$(@{Info='  ';Ok='✓ ';Warn='⚠ ';Fail='✗ ';H='──'}[$L])$M" -ForegroundColor $c }
function H { param([string]$M) Write-Result $M H; "" }
function Add-Error { param([string]$Source, [string]$Message, [string]$Severity='error', [string]$Fix) $errors += @{source=$Source; message=$Message; severity=$Severity; fix=$Fix; timestamp=(Get-Date -Format o)} }

# ═══════════════════════════════════════════════════════════════════════════
# 1. Windows Event Logs
# ═══════════════════════════════════════════════════════════════════════════
H "1. Windows Event Log Analysis"

$logSources = @{
    'Application' = @('Application Error', '.NET Runtime', 'Windows Error Reporting', 'Corex', 'PHP', 'Python')
    'System' = @('Service Control Manager', 'DCOM', 'disk', 'ntfs', 'Tcpip', 'bowser', 'mrxsmb')
}

$logData = @{}
foreach ($logName in $logSources.Keys) {
    try {
        $logs = Get-WinEvent -LogName $logName -ErrorAction SilentlyContinue |
            Where-Object { $_.TimeCreated -ge $since }

        $logData[$logName] = @{ total = $logs.Count; errors = @(); warnings = @() }

        foreach ($log in $logs) {
            $logData[$logName].errors += @{
                time = $log.TimeCreated.ToString('o')
                level = $log.LevelDisplayName
                provider = $log.ProviderName
                id = $log.Id
                message = $log.Message -replace '\s+', ' ' -replace '^.{200}.*$', '$&...'
            }

            $sourceMatch = $logSources[$logName] | Where-Object { $log.ProviderName -match $_ }
            if ($sourceMatch -and $log.LevelDisplayName -match 'Error|Critical') {
                Add-Error "EventLog/$logName" "[$($log.LevelDisplayName)] [$($log.ProviderName)] $($log.Message -replace '\s+', ' ')" 'error'
            }
        }

        Write-Result "$logName: $($logs.Count) events in last $Hours hours" $(if ($logs.Count -gt 0) {'Warn'} else {'Ok'})
        if ($logs.Count -gt 100) {
            Write-Result "$logName has $($logs.Count) recent events (high)" Warn
            $anomalies += "$logName has $($logs.Count) events in last $Hours hours"
        }
    } catch {
        Write-Result "Cannot read $logName log (run as Administrator)" Warn
    }
}

$data.sources.event_log = $logData

# ═══════════════════════════════════════════════════════════════════════════
# 2. PHP Error Logs
# ═══════════════════════════════════════════════════════════════════════════
H "2. PHP Error Logs"

$phpLogPaths = @(
    "$ProjectRoot\backend\storage\logs\laravel.log",
    "$ProjectRoot\backend\storage\logs",
    "$env:TMP\php-errors.log"
)

$phpErrors = @()
foreach ($path in $phpLogPaths) {
    if (Test-Path $path) {
        if ((Get-Item $path).PSIsContainer) {
            $files = Get-ChildItem $path -Filter '*.log' -ErrorAction SilentlyContinue |
                Where-Object { $_.LastWriteTime -ge $since }
            foreach ($f in $files) {
                $content = Get-Content $f.FullName -Tail 200 -ErrorAction SilentlyContinue
                $errLines = $content | Select-String 'error|exception|fatal|stack trace|PHP Fatal|PHP Warning|PHP Notice' -CaseSensitive:$false
                foreach ($line in $errLines) {
                    $phpErrors += @{ file = $f.Name; line = $line }
                    if ($line -match 'Fatal|fatal|Exception|exception') {
                        Add-Error "PHP/$($f.Name)" "$line" 'error'
                    }
                }
                Write-Result "$($f.Name): $($errLines.Count) error lines" $(if ($errLines.Count -gt 0) {'Warn'} else {'Ok'})
            }
        } else {
            $content = Get-Content $path -Tail 200 -ErrorAction SilentlyContinue
            $errLines = $content | Select-String 'error|exception|fatal|stack trace|PHP Fatal|PHP Warning|PHP Notice' -CaseSensitive:$false
            foreach ($line in $errLines) {
                $phpErrors += @{ file = (Split-Path $path -Leaf); line = $line }
                Add-Error "PHP/$((Split-Path $path -Leaf))" "$line" 'error'
            }
            Write-Result "$(Split-Path $path -Leaf): $($errLines.Count) error lines" $(if ($errLines.Count -gt 0) {'Warn'} else {'Ok'})
        }
    } else {
        Write-Result "Log path not found: $path" Info
    }
}

$data.sources.php = @{ errors = $phpErrors; count = $phpErrors.Count }

# ═══════════════════════════════════════════════════════════════════════════
# 3. Python Logs & Tracebacks
# ═══════════════════════════════════════════════════════════════════════════
H "3. Python Error Logs"

$pyLogPaths = @(
    "$ProjectRoot\ai-gateway",
    "$env:LOCALAPPDATA\Corex\logs",
    "$env:USERPROFILE\.corex\logs"
)

$pyErrors = @()
$pyDirs = $pyLogPaths | Where-Object { Test-Path $_ }
foreach ($dir in $pyDirs) {
    $files = Get-ChildItem $dir -Recurse -Include '*.log', '*.txt' -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -ge $since }
    foreach ($f in $files) {
        $content = Get-Content $f.FullName -Tail 100 -ErrorAction SilentlyContinue
        $errLines = $content | Select-String 'Traceback|Error|Exception|WARNING|CRITICAL|ERROR' -CaseSensitive:$false
        foreach ($line in $errLines) {
            $pyErrors += @{ file = $f.Name; line = $line }
            if ($line -match 'Traceback|Error|Exception|CRITICAL') {
                Add-Error "Python/$($f.Name)" "$line" 'error'
            }
        }
        Write-Result "$($f.Name): $($errLines.Count) error lines" $(if ($errLines.Count -gt 0) {'Warn'} else {'Ok'})
    }
}

# Try to run Python traceback analysis
$pyexe = (Get-Command python -ErrorAction SilentlyContinue).Source
if (-not $pyexe) { $pyexe = "$ProjectRoot\.venv\Scripts\python.exe" }
if (-not $pyexe) { $pyexe = "$ProjectRoot\ai-gateway\.venv\Scripts\python.exe" }
if (Test-Path $pyexe) {
    try {
        $dllErrors = & $pyexe -c "
import sys, json, ctypes, os
results = {}
try:
    ctypes.windll.kernel32
    results['kernel32'] = 'ok'
except: results['kernel32'] = 'fail'
try:
    ctypes.windll.ws2_32
    results['ws2_32'] = 'ok'
except: results['ws2_32'] = 'fail'
try:
    ctypes.windll.ole32
    results['ole32'] = 'ok'
except: results['ole32'] = 'fail'
try:
    from cryptography.hazmat.bindings._openssl import ffi
    results['openssl'] = 'ok'
except: results['openssl'] = 'check'
results['python_dll_dir'] = os.environ.get('PATH', '').split(';')
print(json.dumps(results))" 2>&1 | Where-Object { $_ -match '^{' }

        if ($dllErrors) {
            $dllData = $dllErrors | ConvertFrom-Json
            Write-Result "Python DLL check: kernel32=$($dllData.kernel32) ws2_32=$($dllData.ws2_32) ole32=$($dllData.ole32) openssl=$($dllData.openssl)" Ok
        }
    } catch { }
}

$data.sources.python = @{ errors = $pyErrors; count = $pyErrors.Count }

# ═══════════════════════════════════════════════════════════════════════════
# 4. Docker Logs
# ═══════════════════════════════════════════════════════════════════════════
H "4. Docker Logs"

try {
    $containers = & docker ps -a --format '{{.Names}} {{.Status}}' 2>&1
    $dockerErrors = @()
    foreach ($line in $containers) {
        if ($line -match '(\S+)\s+(.*)') {
            $name = $matches[1]
            $status = $matches[2]
            if ($status -match 'Exited|unhealthy') {
                Add-Error "Docker/$name" "Container $name is $status" 'error'
                $dockerErrors += @{ container = $name; status = $status }
                # Get last 10 lines of logs
                try {
                    $logs = & docker logs $name --tail 10 --no-color 2>&1
                    $errorsInLogs = $logs | Select-String 'error|fatal|exception|CRITICAL' -CaseSensitive:$false
                    foreach ($el in $errorsInLogs) {
                        Add-Error "Docker/$name/log" "$el" 'error'
                    }
                } catch { }
            }
        }
    }
    $data.sources.docker = @{ errors = $dockerErrors; count = $dockerErrors.Count }
    Write-Result "Docker: $($containers.Count) containers checked" Ok
} catch {
    Write-Result "Docker not available for log analysis" Info
}

# ═══════════════════════════════════════════════════════════════════════════
# 5. Nginx Logs
# ═══════════════════════════════════════════════════════════════════════════
H "5. Nginx Logs"

$nginxPaths = @(
    "$ProjectRoot\windows\packaging\tools\nginx\logs\error.log",
    "$ProjectRoot\nginx\logs\error.log",
    "C:\tools\nginx\logs\error.log"
)

foreach ($path in $nginxPaths) {
    if ((Test-Path $path)) {
        $content = Get-Content $path -Tail 100 -ErrorAction SilentlyContinue
        $errLines = $content | Select-String 'error|fatal|emerg|crit' -CaseSensitive:$false
        foreach ($line in $errLines) {
            Add-Error "Nginx/$(Split-Path $path -Leaf)" "$line" 'error'
        }
        Write-Result "$(Split-Path $path -Leaf): $($errLines.Count) errors" $(if ($errLines.Count -gt 0) {'Warn'} else {'Ok'})
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 6. Redis Logs
# ═══════════════════════════════════════════════════════════════════════════
H "6. Redis Logs"

$redisPaths = @(
    "$ProjectRoot\windows\packaging\tools\redis\logs\redis.log",
    "C:\tools\redis\logs\redis.log"
)

foreach ($path in $redisPaths) {
    if (Test-Path $path) {
        $content = Get-Content $path -Tail 100 -ErrorAction SilentlyContinue
        $errLines = $content | Select-String '#\s|Error|WARNING|FATAL' -CaseSensitive:$false
        foreach ($line in $errLines) {
            Add-Error "Redis/$(Split-Path $path -Leaf)" "$line" 'error'
        }
        Write-Result "$(Split-Path $path -Leaf): $($errLines.Count) issues" $(if ($errLines.Count -gt 0) {'Warn'} else {'Ok'})
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 7. Analysis Summary
# ═══════════════════════════════════════════════════════════════════════════
H "ANALYSIS SUMMARY"

$errorCount = ($errors | Where-Object { $_.severity -eq 'error' }).Count
$warningCount = ($errors | Where-Object { $_.severity -eq 'warning' }).Count

Write-Host "Errors: $errorCount" -ForegroundColor $(if ($errorCount -gt 0) {'Red'} else {'Green'})
Write-Host "Warnings: $warningCount" -ForegroundColor $(if ($warningCount -gt 0) {'Yellow'} else {'Green'})
Write-Host "Anomalies: $($anomalies.Count)" -ForegroundColor $(if ($anomalies.Count -gt 0) {'Yellow'} else {'Green'})
Write-Host ""

if ($errors.Count -gt 0) {
    Write-Host "Top Errors (by source):" -ForegroundColor Yellow
    $errors | Group-Object source | Sort-Object Count -Descending | Select-Object -First 10 | ForEach-Object {
        $sample = ($_.Group | Select-Object -First 1).message
        Write-Host "  [$($_.Name)] ×$($_.Count): $(if ($sample.Length -gt 100) { $sample.Substring(0, 100) + '...' } else { $sample })" -ForegroundColor Red
    }
    Write-Host ""

    # Write error details
    $errors | ForEach-Object {
        $severity = if ($_.severity -eq 'error') { 'ERROR' } else { 'WARN' }
        "[$severity] [$($_.source)] $($_.message)" | Out-File $ReportFile -Encoding utf8 -Append
        if ($_.fix) { "  Fix: $($_.fix)" | Out-File $ReportFile -Encoding utf8 -Append }
    }
}

$data.errors = $errors
$data.anomalies = $anomalies
$data.error_count = $errorCount
$data | ConvertTo-Json -Depth 10 | Out-File $JsonFile -Encoding utf8

Write-Host "Report: $ReportFile" -ForegroundColor Gray
Write-Host "JSON: $JsonFile" -ForegroundColor Gray

exit ($errorCount -gt 0 ? 1 : 0)
