#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Web-to-Desktop Migration Master Script

.DESCRIPTION
    Orchestrates full migration of Corex from web/Linux environment to
    Windows desktop. Runs all migration phases in order: data, services,
    config, validation.

.PARAMETER Phase
    Run specific phase: All, Data, Services, Config, Test, Rollback

.PARAMETER ProjectRoot
    Root of the Corex project. Auto-detected if not provided.

.PARAMETER InstallDir
    Destination install directory. Default: C:\Program Files\Corex

.PARAMETER DataDir
    User data directory. Default: %LOCALAPPDATA%\Corex

.PARAMETER BackupDir
    Backup directory. Default: %USERPROFILE%\Corex-Backup\{timestamp}

.PARAMETER UseSQLite
    Migrate from PostgreSQL to SQLite. Default: true

.PARAMETER KeepPostgres
    Keep PostgreSQL data after migration. Default: false

.EXAMPLE
    .\migrate.ps1                              # Full migration
    .\migrate.ps1 -Phase Data                  # Data migration only
    .\migrate.ps1 -Phase Test                  # Validation only
    .\migrate.ps1 -Phase Rollback              # Rollback last migration
    .\migrate.ps1 -InstallDir "C:\Corex" -UseSQLite $false
#>

param(
    [ValidateSet('All', 'Data', 'Services', 'Config', 'Test', 'Rollback')]
    [string]$Phase = 'All',
    [string]$ProjectRoot,
    [string]$InstallDir,
    [string]$DataDir,
    [string]$BackupDir,
    [switch]$UseSQLite = $true,
    [switch]$KeepPostgres
)

$ErrorActionPreference = 'Stop'

# ── Paths ──────────────────────────────────────────────────────────────────
$ScriptDir = Split-Path -Parent $PSCommandPath
$ProjectRoot = $ProjectRoot ? (Resolve-Path $ProjectRoot) : (Resolve-Path (Join-Path $ScriptDir '..\..'))
$InstallDir = $InstallDir ? $InstallDir : "$env:ProgramFiles\Corex"
$DataDir = $DataDir ? $DataDir : "$env:LOCALAPPDATA\Corex"
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupDir = $BackupDir ? $BackupDir : "$env:USERPROFILE\Corex-Backup\$Timestamp"
$LogDir = "$DataDir\logs"
$StateFile = "$DataDir\.migration-state.json"
$ScriptsDir = $ScriptDir

New-Item -ItemType Directory -Path $LogDir -Force -ErrorAction SilentlyContinue | Out-Null

# ── State ──────────────────────────────────────────────────────────────────
$State = @{
    timestamp = $Timestamp
    phases = @{}
    errors = @()
    warnings = @()
}

function Write-Log {
    param([string]$M, [string]$L='Info', [string]$C='Gray')
    $c = @{Info = 'Gray'; Ok = 'Green'; Warn = 'Yellow'; Fail = 'Red'; H = 'Cyan'; Step = 'Magenta' }
    $p = @{Info = '  '; Ok = '✓ '; Warn = '⚠ '; Fail = '✗ '; H = '──'; Step = '▸ ' }
    Write-Host "$($p[$L])$M" -ForegroundColor $c[$L]
    "$(Get-Date -Format 'HH:mm:ss') [$L] $M" | Out-File "$LogDir\migration.log" -Encoding utf8 -Append
}

function H { param([string]$M) Write-Log $M H; "" }
function Step { param([string]$M) Write-Log $M Step; $M }
function Ok { param([string]$M) Write-Log $M Ok }
function Warn { param([string]$M) Write-Log $M Warn; $State.warnings += $M }
function Fail { param([string]$M) Write-Log $M Fail; $State.errors += $M }

function Invoke-MigrationPhase {
    param(
        [string]$Name,
        [scriptblock]$ScriptBlock,
        [string]$RollbackScript
    )

    Write-Log "Starting phase: $Name" Step
    $State.phases[$Name] = @{ status = 'running'; started = (Get-Date -Format o) }

    try {
        & $ScriptBlock
        $State.phases[$Name].status = 'completed'
        $State.phases[$Name].completed = (Get-Date -Format o)
        Ok "Phase '$Name' completed"
    } catch {
        $State.phases[$Name].status = 'failed'
        $State.phases[$Name].error = "$_"
        $State.phases[$Name].completed = (Get-Date -Format o)
        Fail "Phase '$Name' failed: $_"
    }

    # Save state after each phase
    $State | ConvertTo-Json -Depth 10 | Out-File $StateFile -Encoding utf8
}

# ═══════════════════════════════════════════════════════════════════════════
# PHASE 1: Data Migration
# ═══════════════════════════════════════════════════════════════════════════

function Invoke-DataMigration {
    H "Phase 1: Data Migration"

    Invoke-MigrationPhase 'Backup' {
        & "$ScriptsDir\backup-user-data.ps1" -Source $ProjectRoot -BackupDir $BackupDir -LogDir $LogDir
    }

    if ($UseSQLite) {
        Invoke-MigrationPhase 'PostgreSQL-to-SQLite' {
            & "$ScriptsDir\migrate-pgsql-to-sqlite.ps1" -ProjectRoot $ProjectRoot -DataDir $DataDir -BackupDir $BackupDir -LogDir $LogDir
        }
    }

    Invoke-MigrationPhase 'User-Data' {
        & "$ScriptsDir\migrate-user-data.ps1" -Source $ProjectRoot -DataDir $DataDir -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Convert-Paths' {
        & "$ScriptsDir\convert-paths.ps1" -ProjectRoot $ProjectRoot -DataDir $DataDir -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Line-Endings' {
        & "$ScriptsDir\fix-line-endings.ps1" -ProjectRoot $ProjectRoot -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Env-Variables' {
        & "$ScriptsDir\convert-env.ps1" -ProjectRoot $ProjectRoot -DataDir $DataDir -LogDir $LogDir -InstallDir $InstallDir
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# PHASE 2: Services Setup
# ═══════════════════════════════════════════════════════════════════════════

function Invoke-ServicesSetup {
    H "Phase 2: Services Setup"

    Invoke-MigrationPhase 'Firewall' {
        & "$ScriptsDir\configure-firewall.ps1" -LogDir $LogDir
    }

    Invoke-MigrationPhase 'SSL-Certificates' {
        & "$ScriptsDir\setup-ssl.ps1" -InstallDir $InstallDir -DataDir $DataDir -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Windows-PATH' {
        & "$ScriptsDir\configure-path.ps1" -InstallDir $InstallDir -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Windows-Services' {
        & "$ScriptsDir\setup-windows-services.ps1" -InstallDir $InstallDir -DataDir $DataDir -LogDir $LogDir
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# PHASE 3: Configuration
# ═══════════════════════════════════════════════════════════════════════════

function Invoke-ConfigMigration {
    H "Phase 3: Configuration Migration"

    Invoke-MigrationPhase 'PHP-Configuration' {
        & "$ScriptsDir\optimize-php-windows.ps1" -InstallDir $InstallDir -DataDir $DataDir -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Nginx-Configuration' {
        & "$ScriptsDir\setup-nginx-windows.ps1" -InstallDir $InstallDir -DataDir $DataDir -LogDir $LogDir
    }

    Invoke-MigrationPhase 'Redis-Configuration' {
        & "$ScriptsDir\setup-redis-windows.ps1" -InstallDir $InstallDir -DataDir $DataDir -LogDir $LogDir
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# PHASE 4: Validation
# ═══════════════════════════════════════════════════════════════════════════

function Invoke-Validation {
    H "Phase 4: Validation"

    Invoke-MigrationPhase 'Port-Check' {
        & "$ScriptsDir\test-ports.ps1 -LogDir $LogDir"
    }

    Invoke-MigrationPhase 'Database-Test' {
        & "$ScriptsDir\test-database.ps1 -ProjectRoot $ProjectRoot -DataDir $DataDir -LogDir $LogDir"
    }

    Invoke-MigrationPhase 'Services-Test' {
        & "$ScriptsDir\test-services.ps1 -LogDir $LogDir"
    }

    Invoke-MigrationPhase 'AI-Gateway-Test' {
        & "$ScriptsDir\test-ai-gateway.ps1 -ProjectRoot $ProjectRoot -LogDir $LogDir"
    }

    Invoke-MigrationPhase 'Permissions-Test' {
        & "$ScriptsDir\test-permissions.ps1 -InstallDir $InstallDir -DataDir $DataDir -LogDir $LogDir"
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# ROLLBACK
# ═══════════════════════════════════════════════════════════════════════════

function Invoke-Rollback {
    H "Rollback"

    if (-not (Test-Path $StateFile)) {
        Warn "No migration state file found at $StateFile"
        return
    }

    $prevState = Get-Content $StateFile | ConvertFrom-Json
    $rollbackDir = "$env:USERPROFILE\Corex-Backup\$($prevState.timestamp)"

    if (-not (Test-Path $rollbackDir)) {
        Warn "Backup directory not found: $rollbackDir"
        return
    }

    Write-Log "Rolling back to: $rollbackDir" Step

    # Restore backed up files
    $backupDirs = Get-ChildItem $rollbackDir -Directory
    foreach ($dir in $backupDirs) {
        $target = $dir.Name -replace '^backup-', ''
        if ($target -eq 'root') { $target = $ProjectRoot }
        Write-Log "Restoring: $($dir.FullName) → $target" Info
        Copy-Item "$($dir.FullName)\*" $target -Recurse -Force -ErrorAction SilentlyContinue
    }

    # Uninstall services
    $serviceNames = @('CorexRedis', 'CorexPHP', 'CorexNginx', 'CorexAIGateway', 'CorexServiceHost')
    foreach ($svc in $serviceNames) {
        $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
        if ($s) {
            Stop-Service $svc -Force -ErrorAction SilentlyContinue
            sc.exe delete $svc 2>$null
            Write-Log "Removed service: $svc" Info
        }
    }

    # Remove firewall rules
    $ruleNames = @('Corex-HTTP', 'Corex-HTTPS', 'Corex-AI-Gateway', 'Corex-Redis', 'Corex-Ollama')
    foreach ($rule in $ruleNames) {
        netsh advfirewall firewall delete rule name="$rule" 2>$null
    }

    # Remove PATH entries
    $currentPath = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $newPath = ($currentPath -split ';' | Where-Object { $_ -notmatch 'Corex' }) -join ';'
    [Environment]::SetEnvironmentVariable('Path', $newPath, 'Machine')

    Write-Log "Rollback complete. Restart required." Step
}

# ═══════════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════════

try {
    # Ensure admin
    $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) {
        Write-Host "ERROR: This script must be run as Administrator." -ForegroundColor Red
        exit 1
    }

    H "Corex Web → Desktop Migration"
    Write-Log "Project root: $ProjectRoot"
    Write-Log "Install to: $InstallDir"
    Write-Log "User data: $DataDir"
    Write-Log "Backup: $BackupDir"
    Write-Log "Log: $LogDir"
    Write-Log ""

    switch ($Phase) {
        'All' {
            Invoke-DataMigration
            if ($State.errors.Count -eq 0 -or (Prompt-Continue "Data migration had errors. Continue?")) {
                Invoke-ServicesSetup
            }
            if ($State.errors.Count -eq 0 -or (Prompt-Continue "Services setup had errors. Continue?")) {
                Invoke-ConfigMigration
            }
            if ($State.errors.Count -eq 0 -or (Prompt-Continue "Config migration had errors. Continue?")) {
                Invoke-Validation
            }
        }
        'Data' { Invoke-DataMigration }
        'Services' { Invoke-ServicesSetup }
        'Config' { Invoke-ConfigMigration }
        'Test' { Invoke-Validation }
        'Rollback' { Invoke-Rollback }
    }

    # ── Summary ──
    H "Migration Summary"
    Write-Log "Completed phases:" Ok
    $State.phases.Keys | ForEach-Object {
        $s = $State.phases[$_]
        $icon = if ($s.status -eq 'completed') { '✓' } elseif ($s.status -eq 'failed') { '✗' } else { '?' }
        Write-Log "  $icon $_ → $($s.status)" ($s.status -eq 'completed' ? 'Ok' : 'Warn')
    }

    Write-Log ""
    Write-Log "Errors: $($State.errors.Count)  Warnings: $($State.warnings.Count)" ($State.errors.Count -gt 0 ? 'Fail' : 'Ok')

    if ($State.errors.Count -gt 0) {
        Write-Log "Errors:" Fail
        $State.errors | ForEach-Object { Write-Log "  • $_" Fail }
    }

    Write-Log ""
    Write-Log "State file: $StateFile"
    Write-Log "Log: $LogDir\migration.log"
    Write-Log "Backup: $BackupDir"

    if ($Phase -ne 'Rollback' -and $State.errors.Count -eq 0) {
        Write-Log "Migration complete! Restart your computer and run test-services.ps1 to verify." Ok
    }

    exit ($State.errors.Count -gt 0 ? 1 : 0)

} catch {
    Write-Log "FATAL: $_" Fail
    exit 2
}
