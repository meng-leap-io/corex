#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Install Corex AI Gateway as a Windows Service using pywin32 + NSSM.

.DESCRIPTION
    Two installation methods:
      1. pywin32 (recommended) — uses python -m app.core.windows_service install
      2. NSSM fallback — wraps uvicorn directly

    Also:
      - Registers Windows Event Log source
      - Writes registry settings (HKLM\SOFTWARE\Corex\AIGateway)
      - Configures service recovery (restart on failure)
      - Sets up log directory and Prometheus metrics directory

.PARAMETER Method
    Installation method: PyWin32 (default) or NSSM

.PARAMETER VenvPath
    Path to Python virtual environment (default: auto-detect)

.PARAMETER Port
    Service port (default: 8000)

.PARAMETER Workers
    Number of uvicorn workers (default: 2)

.PARAMETER NoRegistry
    Skip writing registry settings

.EXAMPLE
    PS> .\install-service.ps1
    PS> .\install-service.ps1 -Method NSSM -Port 8000 -Workers 4
    PS> .\install-service.ps1 -Uninstall
#>

param(
    [ValidateSet('PyWin32', 'NSSM')]
    [string]$Method = 'PyWin32',
    [string]$VenvPath = '',
    [int]$Port = 8000,
    [int]$Workers = 2,
    [switch]$NoRegistry,
    [switch]$Uninstall,
    [switch]$SkipPip
)

$ErrorActionPreference = 'Stop'
$ServiceName = 'CorexAIGateway'
$DisplayName = 'Corex AI Gateway'
$Description = 'FastAPI-based AI provider gateway with agent orchestration, rate limiting, and caching'

$ProjectRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$GatewayDir = Join-Path $ProjectRoot 'ai-gateway'

# Auto-detect venv
if (-not $VenvPath) {
    $candidates = @(
        Join-Path $GatewayDir '.venv\Scripts\python.exe',
        Join-Path $ProjectRoot '.venv\Scripts\python.exe',
        "$env:LOCALAPPDATA\Corex\ai-gateway\.venv\Scripts\python.exe"
    )
    foreach ($c in $candidates) {
        if (Test-Path $c) {
            $VenvPath = $c
            break
        }
    }
    if (-not $VenvPath) {
        $VenvPath = Join-Path $GatewayDir '.venv\Scripts\python.exe'
    }
}

$PythonExe = $VenvPath
$VenvDir = Split-Path (Split-Path $VenvPath)

# ── Helper Functions ───────────────────────────────────────────────────

function Write-Log {
    param([string]$Message, [ValidateSet('Info','Success','Warning','Error')][string]$Level = 'Info')
    $color = @{ 'Error'='Red'; 'Warning'='Yellow'; 'Success'='Green' }.GetValueRef($Level) ?? 'Cyan'
    Write-Host "[$Level] $Message" -ForegroundColor $color
}

function Write-Registry {
    param([string]$Key, [string]$Value)
    if ($NoRegistry) { return }
    $path = 'HKLM:\SOFTWARE\Corex\AIGateway'
    if (-not (Test-Path $path)) {
        New-Item -Path $path -Force | Out-Null
    }
    Set-ItemProperty -Path $path -Name $Key -Value $Value
    Write-Log "Registry: $Key = $Value" -Level Info
}

function Remove-Registry {
    $path = 'HKLM:\SOFTWARE\Corex\AIGateway'
    if (Test-Path $path) {
        Remove-Item -Path $path -Recurse -Force -ErrorAction SilentlyContinue
        Write-Log "Registry key removed" -Level Info
    }
}

# ── Install Dependencies ──────────────────────────────────────────────

function Install-PythonDeps {
    if ($SkipPip) { return }

    Write-Log "Installing Python dependencies..." -Level Info
    $reqFile = Join-Path $GatewayDir 'requirements.txt'

    if (-not (Test-Path $PythonExe)) {
        Write-Log "Creating virtual environment at $VenvDir..." -Level Info
        & python -m venv "$VenvDir"
        if ($LASTEXITCODE -ne 0) {
            Write-Log "Failed to create venv. Ensure Python 3.12+ is installed." -Level Error
            exit 1
        }
    }

    & "$PythonExe" -m pip install --upgrade pip --quiet
    & "$PythonExe" -m pip install -r "$reqFile" --quiet
    if ($LASTEXITCODE -eq 0) {
        Write-Log "Python dependencies installed" -Level Success
    } else {
        Write-Log "pip install had warnings" -Level Warning
    }
}

# ── Method: PyWin32 ───────────────────────────────────────────────────

function Install-PyWin32Service {
    Write-Log "Installing via pywin32..." -Level Info

    # Set environment for the install command
    $env:COREX_DATA_DIR = "$env:LOCALAPPDATA\Corex"
    $env:COREX_LOG_DIR = "$env:LOCALAPPDATA\Corex\logs"

    & "$PythonExe" -m app.core.windows_service install
    if ($LASTEXITCODE -ne 0) {
        Write-Log "pywin32 service installation failed" -Level Error
        exit 1
    }

    # Configure recovery options via sc.exe
    sc.exe failure $ServiceName reset= 86400 actions= restart/5000/restart/10000/restart/15000
    sc.exe failureflag $ServiceName flag= 1

    Write-Log "Service installed and recovery configured" -Level Success
}

function Uninstall-PyWin32Service {
    Write-Log "Uninstalling via pywin32..." -Level Info
    & "$PythonExe" -m app.core.windows_service remove
    Write-Log "Service removed" -Level Success
}

# ── Method: NSSM ──────────────────────────────────────────────────────

function Test-NSSM {
    try { $null = nssm status 2>$null; return $true } catch { return $false }
}

function Install-NSSMService {
    Write-Log "Installing via NSSM..." -Level Info

    if (-not (Test-NSSM)) {
        Write-Log "NSSM not found. Install from https://nssm.cc/download" -Level Error
        exit 1
    }

    $logDir = "$env:LOCALAPPDATA\Corex\logs"
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null

    # Create a wrapper script that activates venv and starts uvicorn
    $wrapperScript = "$env:LOCALAPPDATA\Corex\start-gateway.bat"
@"
@echo off
cd /d "$GatewayDir"
set COREX_DATA_DIR=$env:LOCALAPPDATA\Corex
set COREX_LOG_DIR=$logDir
set PROMETHEUS_MULTIPROC_DIR=$env:TMP\prometheus
set OLLAMA_BASE_URL=http://127.0.0.1:11434
set REDIS_HOST=127.0.0.1
"$PythonExe" -m uvicorn main:app --host 127.0.0.1 --port $Port --workers $Workers --log-level info --limit-max-requests 10000
"@ | Set-Content -Path $wrapperScript -Encoding ASCII

    # Install with NSSM
    nssm install $ServiceName $wrapperScript
    nssm set $ServiceName DisplayName $DisplayName
    nssm set $ServiceName Description $Description
    nssm set $ServiceName AppDirectory $GatewayDir
    nssm set $ServiceName AppNoConsole 1
    nssm set $ServiceName Start SERVICE_AUTO_START
    nssm set $ServiceName AppRestartDelay 5000
    nssm set $ServiceName AppStdout "$logDir\gateway-stdout.log"
    nssm set $ServiceName AppStderr "$logDir\gateway-stderr.log"
    nssm set $ServiceName AppRotateFiles 1
    nssm set $ServiceName AppRotateOnline 1
    nssm set $ServiceName AppRotateSeconds 86400
    nssm set $ServiceName AppRotateBytes 10485760

    # Dependencies
    nssm set $ServiceName DependOnService CorexRedis

    Write-Log "Service installed via NSSM" -Level Success
}

function Uninstall-NSSMService {
    if (-not (Test-NSSM)) {
        Write-Log "NSSM not found" -Level Warning
        return
    }
    Write-Log "Uninstalling via NSSM..." -Level Info
    nssm stop $ServiceName 2>$null
    nssm remove $ServiceName confirm
    Write-Log "Service removed" -Level Success
}

# ── Registry Configuration ────────────────────────────────────────────

function Configure-Registry {
    Write-Log "Writing registry settings..." -Level Info
    Write-Registry -Key 'host' -Value '127.0.0.1'
    Write-Registry -Key 'port' -Value "$Port"
    Write-Registry -Key 'workers' -Value "$Workers"
    Write-Registry -Key 'log_level' -Value 'info'
    Write-Registry -Key 'redis_host' -Value '127.0.0.1'
    Write-Registry -Key 'redis_local' -Value 'true'
    Write-Registry -Key 'ollama_enabled' -Value 'true'
    Write-Registry -Key 'ollama_default_model' -Value 'llama3.2'
    Write-Registry -Key 'service_status' -Value 'installed'
    Write-Registry -Key 'limit_max_requests' -Value '10000'
    Write-Registry -Key 'data_dir' -Value "$env:LOCALAPPDATA\Corex"
    Write-Registry -Key 'log_dir' -Value "$env:LOCALAPPDATA\Corex\logs"

    # Service dependency: start after Redis
    sc.exe config $ServiceName depend= CorexRedis
}

# ── Event Log ─────────────────────────────────────────────────────────

function Register-EventLog {
    try {
        if (-not [System.Diagnostics.EventLog]::SourceExists('CorexAIGateway')) {
            [System.Diagnostics.EventLog]::CreateEventSource('CorexAIGateway', 'Application')
            Write-Log "Event log source 'CorexAIGateway' created" -Level Success
        }
    } catch {
        Write-Log "Cannot create event log source: $_" -Level Warning
    }
}

function Remove-EventLog {
    try {
        if ([System.Diagnostics.EventLog]::SourceExists('CorexAIGateway')) {
            [System.Diagnostics.EventLog]::DeleteEventSource('CorexAIGateway')
        }
    } catch {
        Write-Log "Cannot remove event log source: $_" -Level Warning
    }
}

# ── Main ──────────────────────────────────────────────────────────────

try {
    if ($Uninstall) {
        Write-Log "=== Uninstalling Corex AI Gateway Service ===" -Level Info
        if ($Method -eq 'PyWin32') { Uninstall-PyWin32Service }
        else { Uninstall-NSSMService }
        Remove-Registry
        Remove-EventLog
        Write-Log "Uninstall complete" -Level Success
        exit 0
    }

    Write-Log "=== Installing Corex AI Gateway Windows Service ===" -Level Info

    # Validate paths
    if (-not (Test-Path $GatewayDir\main.py)) {
        Write-Log "Gateway not found at $GatewayDir" -Level Error
        exit 1
    }

    if (-not (Test-Path $PythonExe)) {
        Write-Log "Python not found at $PythonExe" -Level Warning
        Write-Log "Run: python -m venv `"$VenvDir`" && `"$PythonExe`" -m pip install -r `"$GatewayDir\requirements.txt`"" -Level Info
    }

    Install-PythonDeps

    # Create required directories
    $dirs = @(
        "$env:LOCALAPPDATA\Corex\logs",
        "$env:LOCALAPPDATA\Corex\data",
        "$env:TMP\prometheus"
    )
    foreach ($d in $dirs) {
        New-Item -ItemType Directory -Path $d -Force | Out-Null
    }

    Register-EventLog

    if ($Method -eq 'PyWin32') {
        Install-PyWin32Service
    } else {
        Install-NSSMService
    }

    Configure-Registry

    Write-Log "=== Installation Complete ===" -Level Success
    Write-Log "Service: $ServiceName ($DisplayName)" -Level Info
    Write-Log "Python: $PythonExe" -Level Info
    Write-Log "Port: $Port, Workers: $Workers" -Level Info
    Write-Log ""
    Write-Log "Start with: Start-Service $ServiceName" -Level Info
    Write-Log "  or:       net start $ServiceName" -Level Info
    Write-Log "  or:       .\install-service.ps1 -Start" -Level Info
    Write-Log ""
    Write-Log "Check with: Get-Service $ServiceName" -Level Info

} catch {
    Write-Log "Fatal error: $_" -Level Error
    exit 1
}
