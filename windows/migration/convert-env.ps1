#Requires -Version 5.1

<#
.SYNOPSIS
    Converts .env file entries to Windows system/user environment variables.

.DESCRIPTION
    Reads Laravel .env and ai-gateway .env files, converts them to Windows
    environment variables (both system and user scope), and creates backup
    of the original files.

.PARAMETER ProjectRoot
    Project root containing .env files.

.PARAMETER DataDir
    User data directory for scoped settings.

.PARAMETER LogDir
    Log output directory.

.PARAMETER InstallDir
    Install directory for system-scoped paths.

.PARAMETER Scope
    Environment variable scope: User, Machine, or Both. Default: Both
#>

param(
    [string]$ProjectRoot,
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs",
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [ValidateSet('User','Machine','Both')][string]$Scope = 'Both'
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\convert-env.log"

function Log { param([string]$M) $ts = Get-Date -Format 'HH:mm:ss'; Write-Host "$ts $M" -ForegroundColor Gray; "$ts $M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green; "✓ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow; "⚠ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Err  { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red; "✗ $M" | Out-File $LogFile -Encoding utf8 -Append }

Log "=== Environment Variable Migration ==="

$envFiles = @(
    @{ Path = "$ProjectRoot\.env"; Name = 'Laravel Backend' },
    @{ Path = "$ProjectRoot\ai-gateway\.env"; Name = 'AI Gateway' }
)

# Sensitive keys that should NOT be set as system env vars
$sensitiveKeys = @('APP_KEY', 'JWT_SECRET', 'DB_PASSWORD', 'REDIS_PASSWORD', 'PASSWORD', 'SECRET', 'KEY', 'TOKEN')
$skipValues = @('', 'null', 'true', 'false')

$totalSet = 0
$totalSkipped = 0

foreach ($ef in $envFiles) {
    if (-not (Test-Path $ef.Path)) {
        Warn ".env not found: $($ef.Path)"
        continue
    }

    Log "Processing: $($ef.Name) ($($ef.Path))"

    # Backup original
    $backupPath = "$($ef.Path).backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item $ef.Path $backupPath -Force
    Log "Backup saved: $backupPath"

    $lines = Get-Content $ef.Path
    foreach ($line in $lines) {
        $line = $line.Trim()
        if ($line -match '^\s*(export\s+)?(\w+)\s*=\s*(.*)$') {
            $name = $matches[2]
            $value = $matches[3].Trim()

            # Remove surrounding quotes
            $value = $value -replace '^["'']', '' -replace '["'']$', ''

            # Skip empty values
            if ([string]::IsNullOrEmpty($value) -or $skipValues -contains $value.ToLower()) {
                $totalSkipped++
                continue
            }

            # Skip evaluation comments
            if ($name -eq 'APP_KEY' -and $value -match '^base64:') {
                # Keep APP_KEY
            }

            # Expand Windows-specific paths
            $value = $value -replace '/var/www/html', $InstallDir
            $value = $value -replace '/var/log', "$DataDir\logs"
            $value = $value -replace '/tmp', $env:TMP
            $value = $value -replace '/run', "$DataDir\run"
            $value = $value -replace 'unix://', 'tcp://'
            $value = $value -replace '://localhost', '://127.0.0.1'
            $value = $value -replace 'pgsql:', 'sqlite:'  # Desktop default

            # Fix paths for Windows
            $value = $value -replace '/', '\'

            # Determine scope
            $isSensitive = $sensitiveKeys | Where-Object { $name -match $_ }
            if ($isSensitive -and $Scope -ne 'User') {
                Log "  Setting sensitive key as User scope: $name"
                $targetScope = 'User'
            } else {
                $targetScope = switch ($Scope) {
                    'User' { 'User' }
                    'Machine' { 'Machine' }
                    'Both' {
                        if ($name -match '^(PATH|COREX_|DB_|REDIS_|AI_GATEWAY)') { 'Machine' }
                        else { 'User' }
                    }
                }
            }

            try {
                [Environment]::SetEnvironmentVariable($name, $value, $targetScope)
                Log "  Set $name → $value ($targetScope)"
                $totalSet++
            } catch {
                Warn "  Failed to set $name ($targetScope): $_"
            }
        }
    }
}

# Special Windows-specific variables
$winVars = @(
    @{ Name = 'COREX_INSTALL_DIR'; Value = $InstallDir; Scope = 'Machine' },
    @{ Name = 'COREX_DATA_DIR'; Value = $DataDir; Scope = 'Machine' },
    @{ Name = 'COREX_LOG_DIR'; Value = "$DataDir\logs"; Scope = 'Machine' },
    @{ Name = 'COREX_DB_DRIVER'; Value = 'sqlite'; Scope = 'Machine' },
    @{ Name = 'PYTHONIOENCODING'; Value = 'utf-8'; Scope = 'User' },
    @{ Name = 'NODE_ENV'; Value = 'production'; Scope = 'User' }
)

foreach ($wv in $winVars) {
    try {
        [Environment]::SetEnvironmentVariable($wv.Name, $wv.Value, $wv.Scope)
        Log "  Set $($wv.Name) → $($wv.Value) ($($wv.Scope))"
        $totalSet++
    } catch {
        Warn "  Failed to set $($wv.Name): $_"
    }
}

Log ""
Ok "Migration complete: $totalSet variables set, $totalSkipped skipped"
Log "Log file: $LogFile"
Log ""
Log "Note: Environment variables will be available in new processes."
Log "Open a new PowerShell/CMD window or restart to pick them up."

# Generate .env.windows file for reference
$windowsEnvPath = "$DataDir\.env.windows"
Get-ChildItem Env:COREX_*, Env:DB_*, Env:REDIS_*, Env:AI_GATEWAY*, Env:APP_* -ErrorAction SilentlyContinue |
    ForEach-Object { "$($_.Name)=$($_.Value)" } |
    Out-File $windowsEnvPath -Encoding utf8
Log "Windows env snapshot: $windowsEnvPath"
