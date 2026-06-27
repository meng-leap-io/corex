#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Sets up Windows services for Corex (Redis, PHP-FPM, Nginx, AI Gateway).

.DESCRIPTION
    Creates Windows services using sc.exe with proper recovery options,
    dependencies, and service accounts. Each service runs as a child process
    with auto-restart.

.PARAMETER InstallDir
    Installation directory containing binary dependencies.

.PARAMETER DataDir
    User data directory for logs and runtime files.

.PARAMETER LogDir
    Log output directory.

.PARAMETER ServiceUser
    Service account. Default: LocalSystem

.PARAMETER SkipServices
    Comma-separated list of services to skip.

.PARAMETER Uninstall
    Remove all Corex services instead of installing.
#>

param(
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs",
    [string]$ServiceUser = 'LocalSystem',
    [string]$SkipServices,
    [switch]$Uninstall
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\setup-windows-services.log"
$SkipList = ($SkipServices -split ',').Trim()

function Log { param([string]$M) Write-Host "$(Get-Date -Format 'HH:mm:ss') $M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green; "✓ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow; "⚠ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red; "✗ $M" | Out-File $LogFile -Encoding utf8 -Append }

function New-CorexService {
    param(
        [string]$Name,
        [string]$DisplayName,
        [string]$Description,
        [string]$BinaryPath,
        [string]$Dependencies = '',
        [string]$WorkingDir = $InstallDir,
        [string]$User = $ServiceUser
    )

    if ($Uninstall) {
        Log "Removing service: $Name..."
        $s = Get-Service -Name $Name -ErrorAction SilentlyContinue
        if ($s) {
            Stop-Service $Name -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
            sc.exe delete $Name 2>&1 | Out-Null
            Ok "Service removed: $Name"
        }
        return
    }

    if ($SkipList -contains $Name) { Log "Skipping: $Name"; return }

    # Remove existing service if present
    $existing = Get-Service -Name $Name -ErrorAction SilentlyContinue
    if ($existing) {
        Log "Updating existing service: $Name"
        Stop-Service $Name -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        sc.exe delete $Name 2>$null
        Start-Sleep -Seconds 1
    }

    Log "Creating service: $Name..."

    $binPath = $BinaryPath -replace '"', '\"'

    # Create service
    $createArgs = @(
        'create', $Name,
        "binPath=$binPath",
        "displayName=$DisplayName",
        "start=auto",
        "obj=$User"
    )

    $result = sc.exe @createArgs 2>&1
    if ($LASTEXITCODE -ne 0) {
        Err "Failed to create service $Name: $result"
        return
    }

    # Set description
    sc.exe description $Name $Description 2>$null

    # Set dependencies
    if ($Dependencies) {
        sc.exe depend $Name $Dependencies 2>$null
    }

    # Set recovery options (restart on failure)
    sc.exe failure $Name reset=86400 actions=restart/60000/restart/120000/restart/300000 2>$null
    sc.exe failureflag $Name 1 2>$null

    # Set working directory
    $regPath = "HKLM:\SYSTEM\CurrentControlSet\Services\$Name"
    try {
        Set-ItemProperty -Path $regPath -Name 'ImagePath' -Value $binPath -Force
    } catch { }

    # Start the service
    try {
        Start-Service $Name -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        $status = (Get-Service $Name).Status
        if ($status -eq 'Running') {
            Ok "Service started: $Name ($status)"
        } else {
            Warn "Service created but not running: $Name ($status)"
        }
    } catch {
        Warn "Service created but could not start: $_"
    }
}

# ═══════════════════════════════════════════════════════════════════════════
Log "=== Windows Services Setup ==="

if ($Uninstall) {
    Log "Uninstall mode: Removing all Corex services..."
    $allServices = @('CorexRedis', 'CorexPHP', 'CorexNginx', 'CorexAIGateway', 'CorexServiceHost')
    foreach ($s in $allServices) {
        New-CorexService -Name $s -Uninstall
    }
    Ok "All Corex services removed"
    return
}

# Ensure tools exist
$toolsDir = "$InstallDir\tools"
if (-not (Test-Path $toolsDir)) {
    Warn "Tools directory not found: $toolsDir"
    Warn "Run download-tools.ps1 first or check InstallDir"
}

# ── Redis Service ──────────────────────────────────────────────────────────
$redisBin = "$toolsDir\redis\redis-server.exe"
$redisConf = "$InstallDir\conf\redis.conf"

if (Test-Path $redisBin) {
    New-CorexService -Name 'CorexRedis' -DisplayName 'Corex Redis Server' -Description 'Redis in-memory cache for Corex' -BinaryPath "`"$redisBin`" `"$redisConf`"" -Dependencies '' -WorkingDir "$toolsDir\redis"
} else {
    Warn "Redis binary not found at $redisBin. Skipping CorexRedis service."
}

# ── PHP-FPM Service ────────────────────────────────────────────────────────
$phpBin = "$toolsDir\php\php-cgi.exe"
$phpIni = "$InstallDir\conf\php.ini"

if (Test-Path $phpBin) {
    New-CorexService -Name 'CorexPHP' -DisplayName 'Corex PHP-FPM' -Description 'PHP FastCGI Process Manager for Corex' -BinaryPath "`"$phpBin`" -b 127.0.0.1:9000 -c `"$phpIni`"" -Dependencies '' -WorkingDir "$toolsDir\php"
} else {
    Warn "PHP binary not found. Skipping CorexPHP service."
}

# ── Nginx Service ──────────────────────────────────────────────────────────
$nginxBin = "$toolsDir\nginx\nginx.exe"
$nginxConf = "$InstallDir\conf\nginx.conf"

if (Test-Path $nginxBin) {
    New-CorexService -Name 'CorexNginx' -DisplayName 'Corex Nginx Web Server' -Description 'Nginx reverse proxy and static file server for Corex' -BinaryPath "`"$nginxBin`" -p `"$toolsDir\nginx`" -c `"$nginxConf`"" -Dependencies 'CorexPHP' -WorkingDir "$toolsDir\nginx"
} else {
    Warn "Nginx binary not found. Skipping CorexNginx service."
}

# ── AI Gateway Service ─────────────────────────────────────────────────────
$pythonBin = "$toolsDir\python\python.exe"
$gatewayDir = "$InstallDir\ai-gateway"
$gatewayScript = "$gatewayDir\main.py"

if (Test-Path $pythonBin -and (Test-Path $gatewayScript)) {
    New-CorexService -Name 'CorexAIGateway' -DisplayName 'Corex AI Gateway' -Description 'FastAPI-based AI provider routing gateway' -BinaryPath "`"$pythonBin`" -m uvicorn app.main:app --host 127.0.0.1 --port 8001 --workers 2" -Dependencies 'CorexRedis' -WorkingDir $gatewayDir
} else {
    Warn "Python binary or gateway script not found. Skipping CorexAIGateway service."
}

# ── Service Host (C# unified wrapper) ─────────────────────────────────────
$serviceHostBin = "$InstallDir\windows\native-services\CorexServiceHost\bin\CorexServiceHost.exe"
if (Test-Path $serviceHostBin) {
    New-CorexService -Name 'CorexServiceHost' -DisplayName 'Corex Service Host' -Description 'Unified service host managing Corex child processes' -BinaryPath "`"$serviceHostBin`"" -Dependencies 'CorexRedis;CorexPHP;CorexNginx;CorexAIGateway' -WorkingDir "$InstallDir\windows\native-services\CorexServiceHost"
} else {
    Log "Service host binary not found at $serviceHostBin (optional, skipping)"
}

# ── Summary ──
Log ""
$installedServices = Get-Service -Name 'Corex*' -ErrorAction SilentlyContinue
if ($installedServices) {
    Ok "Installed services:"
    $installedServices | Format-Table Name, Status, StartType -AutoSize | Out-String | ForEach-Object { Log $_.Trim() }
} else {
    Warn "No Corex services installed. Check binary paths."
}

Log "Log: $LogFile"
