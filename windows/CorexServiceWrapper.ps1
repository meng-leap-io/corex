#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Multi-Service Windows Service Wrapper
    Manages Docker containers for Laravel backend, AI gateway, PostgreSQL, Redis, and Nginx

.DESCRIPTION
    This PowerShell script provides Windows service integration for the Corex development platform.
    It handles startup, shutdown, health checks, and service orchestration via Docker.

.PARAMETER Action
    The action to perform: Start, Stop, Restart, Status, Install, Uninstall, HealthCheck

.EXAMPLE
    PS> .\CorexServiceWrapper.ps1 -Action Start
    PS> .\CorexServiceWrapper.ps1 -Action HealthCheck
#>

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet('Start', 'Stop', 'Restart', 'Status', 'Install', 'Uninstall', 'HealthCheck', 'Logs')]
    [string]$Action = 'Status'
)

# ============================================================================
# Configuration
# ============================================================================
$ErrorActionPreference = 'Stop'
$VerbosePreference = 'Continue'

# Corex configuration
$ServiceName = 'CorexPlatform'
$DisplayName = 'Corex AI Development Platform'
$Description = 'Multi-service AI development platform (Laravel, FastAPI, PostgreSQL, Redis)'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$ComposePath = Join-Path $ProjectRoot 'docker-compose.yml'
$EnvPath = Join-Path $ProjectRoot 'backend' '.env'
$LogDir = Join-Path $ProjectRoot 'logs'
$ServiceLogFile = Join-Path $LogDir 'corex-service.log'
$HealthCheckLogFile = Join-Path $LogDir 'corex-health.log'

# Service containers
$Containers = @(
    'corex-postgres',
    'corex-redis',
    'corex-php',
    'corex-nginx',
    'corex-ai-gateway',
    'corex-queue',
    'corex-scheduler'
)

# Container health check endpoints (for those with HTTP)
$HealthChecks = @{
    'corex-php'       = 'http://localhost:8000/health'
    'corex-ai-gateway' = 'http://localhost:8001/health'
    'corex-nginx'     = 'http://localhost/health'
}

# ============================================================================
# Helper Functions
# ============================================================================

function Write-Log {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]
        [string]$Message,
        
        [Parameter(Mandatory=$false)]
        [ValidateSet('Info', 'Warning', 'Error', 'Success')]
        [string]$Level = 'Info',
        
        [Parameter(Mandatory=$false)]
        [switch]$ToFile
    )
    
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $logMessage = "[$timestamp] [$Level] $Message"
    
    switch ($Level) {
        'Error'   { Write-Host $logMessage -ForegroundColor Red }
        'Warning' { Write-Host $logMessage -ForegroundColor Yellow }
        'Success' { Write-Host $logMessage -ForegroundColor Green }
        default   { Write-Host $logMessage -ForegroundColor Cyan }
    }
    
    if ($ToFile) {
        if (-not (Test-Path $LogDir)) {
            New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
        }
        Add-Content -Path $ServiceLogFile -Value $logMessage
    }
}

function Test-DockerInstalled {
    try {
        $null = docker --version
        return $true
    } catch {
        Write-Log 'Docker is not installed or not in PATH' -Level Error
        return $false
    }
}

function Test-DockerCompose {
    try {
        $null = docker compose version
        return $true
    } catch {
        Write-Log 'Docker Compose is not installed or not in PATH' -Level Error
        return $false
    }
}

function Test-EnvFile {
    if (-not (Test-Path $EnvPath)) {
        Write-Log "Environment file not found at $EnvPath" -Level Warning
        Write-Log "Copy backend/.env.example to backend/.env and configure required variables" -Level Warning
        return $false
    }
    return $true
}

function Wait-Container {
    param(
        [string]$ContainerName,
        [int]$MaxWaitSeconds = 30
    )
    
    $startTime = Get-Date
    $timeout = $startTime.AddSeconds($MaxWaitSeconds)
    
    while ((Get-Date) -lt $timeout) {
        try {
            $state = docker inspect -f '{{.State.Running}}' $ContainerName 2>$null
            if ($state -eq 'true') {
                return $true
            }
        } catch {}
        
        Start-Sleep -Milliseconds 500
    }
    
    return $false
}

function Get-ContainerStatus {
    param([string]$ContainerName)
    
    try {
        $state = docker inspect -f '{{.State.Running}}' $ContainerName 2>$null
        $health = docker inspect -f '{{.State.Health.Status}}' $ContainerName 2>$null
        
        return @{
            Running = $state -eq 'true'
            Health  = $health
        }
    } catch {
        return @{
            Running = $false
            Health  = 'unknown'
        }
    }
}

# ============================================================================
# Service Actions
# ============================================================================

function Start-CorexServices {
    Write-Log "Starting Corex services..." -ToFile
    
    if (-not (Test-DockerInstalled)) { exit 1 }
    if (-not (Test-DockerCompose)) { exit 1 }
    if (-not (Test-EnvFile)) { exit 1 }
    
    try {
        # Navigate to project root and start containers
        Push-Location $ProjectRoot
        
        Write-Log "Building and starting Docker containers..." -ToFile
        & docker compose --file $ComposePath up -d
        
        if ($LASTEXITCODE -ne 0) {
            throw "Docker Compose failed with exit code $LASTEXITCODE"
        }
        
        Write-Log "Waiting for services to become healthy..." -ToFile
        
        # Wait for critical services
        $criticalServices = @('corex-postgres', 'corex-redis', 'corex-php', 'corex-nginx')
        foreach ($service in $criticalServices) {
            Write-Log "Waiting for $service to start..." -ToFile
            if (Wait-Container -ContainerName $service) {
                Write-Log "$service is running" -Level Success -ToFile
            } else {
                Write-Log "$service failed to start" -Level Error -ToFile
            }
        }
        
        # Brief delay for services to fully initialize
        Start-Sleep -Seconds 3
        
        # Perform health checks
        Write-Log "Performing health checks..." -ToFile
        Get-CorexHealthStatus | Out-Null
        
        Write-Log "All services started successfully" -Level Success -ToFile
        
        Pop-Location
    } catch {
        Write-Log "Error starting services: $_" -Level Error -ToFile
        Pop-Location
        exit 1
    }
}

function Stop-CorexServices {
    Write-Log "Stopping Corex services..." -ToFile
    
    if (-not (Test-DockerInstalled)) { exit 1 }
    
    try {
        Push-Location $ProjectRoot
        
        Write-Log "Stopping Docker containers..." -ToFile
        & docker compose --file $ComposePath down
        
        if ($LASTEXITCODE -ne 0) {
            throw "Docker Compose failed with exit code $LASTEXITCODE"
        }
        
        Write-Log "All services stopped successfully" -Level Success -ToFile
        
        Pop-Location
    } catch {
        Write-Log "Error stopping services: $_" -Level Error -ToFile
        Pop-Location
        exit 1
    }
}

function Restart-CorexServices {
    Write-Log "Restarting Corex services..." -ToFile
    Stop-CorexServices
    Start-Sleep -Seconds 2
    Start-CorexServices
}

function Get-CorexStatus {
    Write-Log "Checking Corex service status..." -ToFile
    
    if (-not (Test-DockerInstalled)) { exit 1 }
    
    $allRunning = $true
    
    foreach ($container in $Containers) {
        $status = Get-ContainerStatus -ContainerName $container
        $statusText = if ($status.Running) { 'Running' } else { 'Stopped' }
        $healthText = if ($status.Health) { "Health: $($status.Health)" } else { '' }
        
        if (-not $status.Running) {
            $allRunning = $false
            Write-Log "$container : $statusText $healthText" -Level Warning
        } else {
            Write-Log "$container : $statusText $healthText" -Level Success
        }
    }
    
    if ($allRunning) {
        Write-Log "All services are running" -Level Success -ToFile
    } else {
        Write-Log "Some services are not running" -Level Warning -ToFile
    }
}

function Get-CorexHealthStatus {
    Write-Log "Performing health checks..." -ToFile
    
    if (-not (Test-DockerInstalled)) { exit 1 }
    
    $healthyCount = 0
    $totalChecks = $HealthChecks.Count
    
    foreach ($container in $HealthChecks.Keys) {
        $endpoint = $HealthChecks[$container]
        $status = Get-ContainerStatus -ContainerName $container
        
        if (-not $status.Running) {
            Write-Log "$container : Not running (cannot health check)" -Level Warning -ToFile
            continue
        }
        
        try {
            $response = Invoke-WebRequest -Uri $endpoint -TimeoutSec 5 -ErrorAction Stop
            if ($response.StatusCode -eq 200) {
                Write-Log "$container : Healthy ($endpoint)" -Level Success -ToFile
                $healthyCount++
            }
        } catch {
            Write-Log "$container : Unhealthy (health check failed)" -Level Warning -ToFile
        }
    }
    
    Write-Log "Health check summary: $healthyCount/$totalChecks services healthy" -ToFile
}

function Show-CorexLogs {
    param(
        [Parameter(Mandatory=$false)]
        [string]$Container,
        
        [Parameter(Mandatory=$false)]
        [int]$Lines = 50
    )
    
    if (-not (Test-DockerInstalled)) { exit 1 }
    
    try {
        if ($Container) {
            Write-Log "Showing logs for $Container (last $Lines lines)..." -ToFile
            & docker compose --file $ComposePath logs --tail=$Lines $Container
        } else {
            Write-Log "Showing logs for all services (last $Lines lines)..." -ToFile
            & docker compose --file $ComposePath logs --tail=$Lines
        }
    } catch {
        Write-Log "Error retrieving logs: $_" -Level Error -ToFile
        exit 1
    }
}

# ============================================================================
# Windows Service Management (NSSM)
# ============================================================================

function Test-NSSM {
    try {
        $null = nssm status 2>$null
        return $true
    } catch {
        return $false
    }
}

function Install-CorexService {
    Write-Log "Installing Corex as Windows service..." -ToFile
    
    if (-not (Test-NSSM)) {
        Write-Log "NSSM (Non-Sucking Service Manager) is not installed" -Level Error
        Write-Log "Download NSSM from https://nssm.cc/download" -Level Warning
        Write-Log "Extract to a folder in PATH or provide full path to nssm.exe" -Level Warning
        exit 1
    }
    
    $serviceScript = Join-Path $PSScriptRoot 'CorexService.ps1'
    
    try {
        # Install service using NSSM
        nssm install $ServiceName "powershell.exe" "-ExecutionPolicy Bypass -File `"$serviceScript`" -Action ServiceLoop"
        
        # Configure service
        nssm set $ServiceName DisplayName $DisplayName
        nssm set $ServiceName Description $Description
        nssm set $ServiceName AppDirectory $ProjectRoot
        nssm set $ServiceName AppNoConsole 1
        nssm set $ServiceName Type SERVICE_WIN32_OWN_PROCESS
        nssm set $ServiceName Start SERVICE_AUTO_START
        
        # Set service to restart on failure
        nssm set $ServiceName AppRestartDelay 5000
        nssm set $ServiceName AppRestartThrottle 1500
        nssm set $ServiceName AppExit Default Restart
        
        Write-Log "Service installed successfully" -Level Success -ToFile
        Write-Log "Run: net start $ServiceName" -Level Info -ToFile
    } catch {
        Write-Log "Error installing service: $_" -Level Error -ToFile
        exit 1
    }
}

function Uninstall-CorexService {
    Write-Log "Uninstalling Corex Windows service..." -ToFile
    
    if (-not (Test-NSSM)) {
        Write-Log "NSSM is not installed" -Level Error
        exit 1
    }
    
    try {
        nssm stop $ServiceName
        nssm remove $ServiceName confirm
        Write-Log "Service uninstalled successfully" -Level Success -ToFile
    } catch {
        Write-Log "Error uninstalling service: $_" -Level Error -ToFile
        exit 1
    }
}

# Service loop for Windows Service Manager
function Start-ServiceLoop {
    Write-Log "Starting Corex service loop..." -ToFile
    
    Start-CorexServices
    
    try {
        while ($true) {
            # Periodic health check
            Start-Sleep -Seconds 30
            
            $healthStatus = @{
                Timestamp = Get-Date
                Healthy   = $true
            }
            
            foreach ($container in $Containers) {
                $status = Get-ContainerStatus -ContainerName $container
                if (-not $status.Running) {
                    Write-Log "Container $container is not running, restarting services..." -Level Warning -ToFile
                    $healthStatus.Healthy = $false
                    break
                }
            }
            
            if (-not $healthStatus.Healthy) {
                Write-Log "Health check failed, restarting services..." -ToFile
                Stop-CorexServices
                Start-Sleep -Seconds 2
                Start-CorexServices
            }
        }
    } catch {
        Write-Log "Service error: $_" -Level Error -ToFile
        Stop-CorexServices
        exit 1
    }
}

# ============================================================================
# Main Execution
# ============================================================================

try {
    switch ($Action) {
        'Start' {
            Start-CorexServices
        }
        'Stop' {
            Stop-CorexServices
        }
        'Restart' {
            Restart-CorexServices
        }
        'Status' {
            Get-CorexStatus
        }
        'HealthCheck' {
            Get-CorexHealthStatus
        }
        'Logs' {
            Show-CorexLogs
        }
        'Install' {
            Install-CorexService
        }
        'Uninstall' {
            Uninstall-CorexService
        }
        'ServiceLoop' {
            Start-ServiceLoop
        }
        default {
            Write-Log "Unknown action: $Action" -Level Error
            exit 1
        }
    }
} catch {
    Write-Log "Fatal error: $_" -Level Error -ToFile
    exit 1
}
