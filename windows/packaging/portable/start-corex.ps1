<#
.SYNOPSIS
    Corex Portable Launcher — starts all services from a self-contained directory.

.DESCRIPTION
    Starts Redis, Nginx, PHP-FPM, the AI Gateway, and the Electron shell
    from bundled portable binaries. No installation required.

    File structure expected:
    Corex/
    ├── app/backend/       # Laravel application
    ├── app/ai-gateway/    # Python AI Gateway
    ├── php/               # Portable PHP
    ├── python/            # Portable Python + venv
    ├── nginx/             # Portable Nginx
    ├── redis/             # Portable Redis
    ├── nodejs/            # Portable Node.js
    ├── electron/          # Electron shell
    ├── data/              # Runtime data
    ├── logs/              # Log files
    └── start-corex.ps1    # This script

.PARAMETER StartElectron
    Launch Electron shell after services are ready

.PARAMETER Stop
    Stop all running services

.PARAMETER Restart
    Restart all services

.PARAMETER Status
    Show status of all services
#>

param(
    [switch]$StartElectron,
    [switch]$Stop,
    [switch]$Restart,
    [switch]$Status
)

$ErrorActionPreference = 'Continue'
$RootDir = Split-Path -Parent $PSScriptRoot

# Config
$PhpPort = 9000
$GatewayPort = 8000
$NginxPort = 80
$RedisPort = 6379
$LogDir = Join-Path $RootDir 'logs'
$PidDir = Join-Path $RootDir 'data\pids'
$DataDir = Join-Path $RootDir 'data'

# Ensure directories
@($LogDir, $PidDir, $DataDir) | ForEach-Object {
    if (-not (Test-Path $_)) { New-Item -ItemType Directory -Path $_ -Force | Out-Null }
}

$services = @(
    @{ Name = 'Redis';     Exe = 'redis\redis-server.exe';  Args = 'redis.conf';                PidFile = 'redis.pid';   ReadyPattern = '*Ready to accept connections*' },
    @{ Name = 'PHP-FPM';   Exe = 'php\php-cgi.exe';         Args = "-b 127.0.0.1:$PhpPort -c php\php.ini"; PidFile = 'php-fpm.pid'; ReadyPattern = '' },
    @{ Name = 'Nginx';     Exe = 'nginx\nginx.exe';         Args = '-c conf\nginx.conf';         PidFile = 'nginx.pid';   ReadyPattern = '' },
    @{ Name = 'AIGateway'; Exe = 'python\Scripts\uvicorn.exe'; Args = "main:app --host 127.0.0.1 --port $GatewayPort --workers 1 --log-level info"; PidFile = 'gateway.pid'; ReadyPattern = '*Uvicorn running on*'; WorkDir = 'app\ai-gateway' }
)

# ── Logging ────────────────────────────────────────────────────────────

function Write-CorexLog {
    param([string]$Message, [string]$Level = 'Info')
    $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $line = "[$ts][$Level] $Message"
    Write-Host $line -ForegroundColor @{Info = 'Cyan'; Success = 'Green'; Warning = 'Yellow'; Error = 'Red' }[$Level]
    Add-Content -Path (Join-Path $LogDir 'corex.log') -Value $line
}

# ── Pid Management ─────────────────────────────────────────────────────

function Get-PidFilePath { param([string]$Name) Join-Path $PidDir "$Name.pid" }

function Write-PidFile {
    param([string]$Name, [int]$Pid)
    Set-Content -Path (Get-PidFilePath $Name) -Value $Pid -Encoding utf8
}

function Read-PidFile {
    param([string]$Name)
    $path = Get-PidFilePath $Name
    if (Test-Path $path) { return [int](Get-Content $path -Raw).Trim() }
    return $null
}

function Remove-PidFile { param([string]$Name) $p = Get-PidFilePath $Name; if (Test-Path $p) { Remove-Item $p -Force } }

# ── Process Management ─────────────────────────────────────────────────

function Get-ServiceStatus {
    param([string]$Name)
    $pid = Read-PidFile $Name
    if (-not $pid) { return 'stopped' }
    try { $p = Get-Process -Id $pid -ErrorAction Stop; if (-not $p.HasExited) { return 'running' } } catch {}
    Remove-PidFile $Name
    return 'stopped'
}

function Start-ServiceProcess {
    param(
        [string]$Name,
        [string]$Exe,
        [string]$Args,
        [string]$WorkDir = '',
        [string]$ReadyPattern = '',
        [int]$ReadyTimeout = 30
    )

    $status = Get-ServiceStatus $Name
    if ($status -eq 'running') { Write-CorexLog "$Name already running" -Level Warning; return $true }

    $exePath = Join-Path $RootDir $Exe
    if (-not (Test-Path $exePath)) { Write-CorexLog "Binary not found: $exePath" -Level Error; return $false }

    $workDir = if ($WorkDir) { Join-Path $RootDir $WorkDir } else { $RootDir }
    $logFile = Join-Path $LogDir "$Name.log"

    Write-CorexLog "Starting $Name..."

    try {
        $psi = New-Object System.Diagnostics.ProcessStartInfo
        $psi.FileName = $exePath
        $psi.Arguments = $Args
        $psi.WorkingDirectory = $workDir
        $psi.RedirectStandardOutput = $true
        $psi.RedirectStandardError = $true
        $psi.UseShellExecute = $false
        $psi.CreateNoWindow = $true
        $psi.EnvironmentVariables["PATH"] = "$RootDir\php;$RootDir\python;$RootDir\nodejs;$RootDir\redis;$env:PATH"

        $p = [System.Diagnostics.Process]::Start($psi)

        # Log stdout/stderr asynchronously
        $jobName = "log-$Name-$(Get-Random)"
        Start-Job -Name $jobName -ScriptBlock {
            param($reader, $logPath)
            $reader | ForEach-Object { Add-Content -Path $logPath -Value $_ }
        } -ArgumentList $p.StandardOutput, $logFile | Out-Null
        Start-Job -Name "$jobName-err" -ScriptBlock {
            param($reader, $logPath)
            $reader | ForEach-Object { Add-Content -Path $logPath -Value "[ERR] $_" }
        } -ArgumentList $p.StandardError, $logFile | Out-Null

        Write-PidFile $Name $p.Id
        Write-CorexLog "$Name started (PID: $($p.Id))" -Level Success
        return $true
    } catch {
        Write-CorexLog "Failed to start $Name`: $_" -Level Error
        return $false
    }
}

function Stop-ServiceProcess {
    param([string]$Name)
    $status = Get-ServiceStatus $Name
    if ($status -ne 'running') { Write-CorexLog "$Name is not running" -Level Warning; return }

    $pid = Read-PidFile $Name
    try {
        $p = Get-Process -Id $pid -ErrorAction Stop
        $p.CloseMainWindow()
        Start-Sleep -Milliseconds 500
        if (-not $p.HasExited) { $p.Kill() }
        $p.WaitForExit(5000) | Out-Null
        Write-CorexLog "$Name stopped (PID: $pid)" -Level Success
    } catch {
        Write-CorexLog "Failed to stop $Name`: $_" -Level Error
    }
    Remove-PidFile $Name
}

function Show-Status {
    Write-Host "`n=== Corex Service Status ===" -ForegroundColor Cyan
    $anyRunning = $false
    foreach ($svc in $services) {
        $s = Get-ServiceStatus $svc.Name
        $icon = switch ($s) { 'running' { '✓' } 'stopped' { '✗' } default { '?' } }
        $color = switch ($s) { 'running' { 'Green' } 'stopped' { 'Red' } default { 'Yellow' } }
        Write-Host "  $icon $($svc.Name)" -ForegroundColor $color
        if ($s -eq 'running') { $anyRunning = $true }
    }
    Write-Host ""
    return $anyRunning
}

# ── Main ──────────────────────────────────────────────────────────────

if ($Stop -or $Restart) {
    Write-CorexLog "Stopping all services..." -Level Info
    for ($i = $services.Count - 1; $i -ge 0; $i--) { Stop-ServiceProcess $services[$i].Name }
    if ($Stop) { Write-CorexLog "All services stopped" -Level Success; return }
}

if ($Status) { Show-Status; return }

if ($Restart -or -not ($Stop -or $Status)) {
    Write-CorexLog "Starting Corex services..." -Level Info

    foreach ($svc in $services) {
        Start-ServiceProcess -Name $svc.Name -Exe $svc.Exe -Args $svc.Args -WorkDir $svc.WorkDir -ReadyPattern $svc.ReadyPattern
    }

    # Quick health check
    Start-Sleep -Seconds 2
    Show-Status | Out-Null

    # Launch Electron if requested
    if ($StartElectron) {
        $electronDir = Join-Path $RootDir 'electron'
        $electronExe = Get-ChildItem -Path $electronDir -Recurse -Filter 'corex-desktop.exe' | Select-Object -First 1
        if ($electronExe) {
            Write-CorexLog "Launching Electron shell..." -Level Info
            Start-Process -FilePath $electronExe.FullName -WorkingDirectory $electronDir
        } else {
            $npxPath = Join-Path $RootDir 'nodejs\npx.cmd'
            if (Test-Path $npxPath) {
                Start-Process -FilePath $npxPath -ArgumentList "electron $electronDir" -WorkingDirectory $electronDir -NoNewWindow
            }
        }
    }

    Write-CorexLog "Corex is ready — http://127.0.0.1:$NginxPort" -Level Success
}
