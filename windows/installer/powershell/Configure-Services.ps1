#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Configure-Services - Setup Windows services for Corex

.DESCRIPTION
    Configures:
    - Windows services via NSSM
    - Windows Task Scheduler jobs
    - Registry entries
    - Environment variables
    - Firewall rules

.EXAMPLE
    PS> .\Configure-Services.ps1
    PS> .\Configure-Services.ps1 -ServiceType Auto
#>

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet('Auto', 'Manual', 'Disabled')]
    [string]$ServiceStartType = 'Auto',
    
    [Parameter(Mandatory=$false)]
    [switch]$ConfigureFirewall,
    
    [Parameter(Mandatory=$false)]
    [switch]$Verbose
)

# ============================================================================
# Configuration
# ============================================================================
$ErrorActionPreference = 'Stop'

$ServiceName = 'CorexPlatform'
$DisplayName = 'Corex AI Development Platform'
$Description = 'Multi-service AI development platform with Laravel backend and FastAPI gateway'
$InstallPath = (Get-ItemProperty -Path 'HKLM:\Software\Corex' -Name 'InstallPath' -ErrorAction SilentlyContinue).InstallPath

# ============================================================================
# Helper Functions
# ============================================================================

function Write-ConfigLog {
    param(
        [string]$Message,
        [ValidateSet('Info', 'Success', 'Warning', 'Error')]
        [string]$Level = 'Info'
    )
    
    $color = switch ($Level) {
        'Error' { 'Red' }
        'Warning' { 'Yellow' }
        'Success' { 'Green' }
        default { 'Cyan' }
    }
    
    Write-Host $Message -ForegroundColor $color
}

function Test-NSSM {
    try {
        & nssm status 2>$null | Out-Null
        return $true
    } catch {
        return $false
    }
}

function Register-NSSmService {
    Write-ConfigLog "Registering NSSM service..."
    
    $serviceScript = Join-Path $InstallPath 'scripts\CorexServiceWrapper.ps1'
    
    if (-not (Test-Path $serviceScript)) {
        Write-ConfigLog "Service script not found: $serviceScript" -Level Error
        return $false
    }
    
    try {
        # Install service
        & nssm install $ServiceName `
            "powershell.exe" `
            "-ExecutionPolicy Bypass -NoProfile -File ""$serviceScript"" -Action ServiceLoop"
        
        # Configure service
        & nssm set $ServiceName DisplayName $DisplayName
        & nssm set $ServiceName Description $Description
        & nssm set $ServiceName AppDirectory $InstallPath
        & nssm set $ServiceName AppNoConsole 1
        & nssm set $ServiceName AppRestartDelay 5000
        & nssm set $ServiceName Type SERVICE_WIN32_OWN_PROCESS
        & nssm set $ServiceName Start $(if ($ServiceStartType -eq 'Auto') { 'SERVICE_AUTO_START' } else { 'SERVICE_DEMAND_START' })
        
        Write-ConfigLog "Service registered successfully" -Level Success
        return $true
    } catch {
        Write-ConfigLog "Error registering service: $_" -Level Error
        return $false
    }
}

function Configure-TaskScheduler {
    Write-ConfigLog "Configuring Windows Task Scheduler..."
    
    $taskScript = Join-Path $InstallPath 'scripts\SetupTaskScheduler.ps1'
    
    if (-not (Test-Path $taskScript)) {
        Write-ConfigLog "Task scheduler script not found: $taskScript" -Level Warning
        return $false
    }
    
    try {
        & powershell -ExecutionPolicy Bypass -NoProfile -File $taskScript -Action Install
        Write-ConfigLog "Task Scheduler configured successfully" -Level Success
        return $true
    } catch {
        Write-ConfigLog "Error configuring Task Scheduler: $_" -Level Warning
        return $false
    }
}

function Configure-RegistryEntries {
    Write-ConfigLog "Configuring registry entries..."
    
    try {
        $regPath = 'HKLM:\Software\Corex'
        
        if (-not (Test-Path $regPath)) {
            New-Item -Path $regPath -Force | Out-Null
        }
        
        # Service configuration
        Set-ItemProperty -Path $regPath -Name 'ServiceName' -Value $ServiceName -Type String
        Set-ItemProperty -Path $regPath -Name 'ServiceStartType' -Value $ServiceStartType -Type String
        
        # Installation details
        Set-ItemProperty -Path $regPath -Name 'InstallDate' -Value (Get-Date -Format 'yyyyMMdd') -Type String
        Set-ItemProperty -Path $regPath -Name 'InstallTime' -Value (Get-Date -Format 'HHmmss') -Type String
        
        Write-ConfigLog "Registry entries configured successfully" -Level Success
        return $true
    } catch {
        Write-ConfigLog "Error configuring registry: $_" -Level Error
        return $false
    }
}

function Configure-EnvironmentVariables {
    Write-ConfigLog "Configuring environment variables..."
    
    try {
        # User environment
        [Environment]::SetEnvironmentVariable('COREX_HOME', $InstallPath, 'User')
        [Environment]::SetEnvironmentVariable('COREX_SCRIPTS', (Join-Path $InstallPath 'scripts'), 'User')
        
        # System environment (requires admin)
        [Environment]::SetEnvironmentVariable('COREX_HOME', $InstallPath, 'Machine')
        [Environment]::SetEnvironmentVariable('COREX_SCRIPTS', (Join-Path $InstallPath 'scripts'), 'Machine')
        
        Write-ConfigLog "Environment variables configured successfully" -Level Success
        return $true
    } catch {
        Write-ConfigLog "Error configuring environment variables: $_" -Level Warning
        return $false
    }
}

function Configure-FirewallRules {
    if (-not $ConfigureFirewall) {
        Write-ConfigLog "Skipping firewall configuration (use -ConfigureFirewall to enable)" -Level Info
        return $true
    }
    
    Write-ConfigLog "Configuring firewall rules..."
    
    try {
        # HTTP
        New-NetFirewallRule -DisplayName "Corex HTTP" -Direction Inbound `
            -Action Allow -Protocol TCP -LocalPort 80 -ErrorAction SilentlyContinue | Out-Null
        
        # HTTPS
        New-NetFirewallRule -DisplayName "Corex HTTPS" -Direction Inbound `
            -Action Allow -Protocol TCP -LocalPort 443 -ErrorAction SilentlyContinue | Out-Null
        
        # PostgreSQL (local only)
        New-NetFirewallRule -DisplayName "Corex PostgreSQL" -Direction Inbound `
            -Action Allow -Protocol TCP -LocalPort 5432 -RemoteAddress LocalSubnet `
            -ErrorAction SilentlyContinue | Out-Null
        
        # Redis (local only)
        New-NetFirewallRule -DisplayName "Corex Redis" -Direction Inbound `
            -Action Allow -Protocol TCP -LocalPort 6379 -RemoteAddress LocalSubnet `
            -ErrorAction SilentlyContinue | Out-Null
        
        Write-ConfigLog "Firewall rules configured successfully" -Level Success
        return $true
    } catch {
        Write-ConfigLog "Error configuring firewall: $_" -Level Warning
        return $false
    }
}

function Test-ServiceConfiguration {
    Write-ConfigLog "Testing service configuration..."
    
    try {
        if (Test-NSSM) {
            $svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
            if ($svc) {
                Write-ConfigLog "Service status: $($svc.Status)" -Level Info
                Write-ConfigLog "Service startup type: $($svc.StartType)" -Level Info
            }
        }
        
        Write-ConfigLog "Service configuration test completed" -Level Success
        return $true
    } catch {
        Write-ConfigLog "Error testing service: $_" -Level Warning
        return $false
    }
}

# ============================================================================
# Main Process
# ============================================================================

try {
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║  Corex Service Configuration           ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    
    if (-not $InstallPath -or -not (Test-Path $InstallPath)) {
        Write-ConfigLog "Corex installation not found" -Level Error
        exit 1
    }
    
    Write-ConfigLog "Installation path: $InstallPath"
    Write-ConfigLog "Service name: $ServiceName"
    Write-ConfigLog "Start type: $ServiceStartType"
    Write-Host ""
    
    # Configure registry
    Configure-RegistryEntries
    
    # Configure environment
    Configure-EnvironmentVariables
    
    # Configure NSSM service (if available)
    if (Test-NSSM) {
        Register-NSSmService
        Test-ServiceConfiguration
    } else {
        Write-ConfigLog "NSSM not found - skipping Windows service registration" -Level Warning
        Write-ConfigLog "Install NSSM to enable Windows Service support" -Level Info
    }
    
    # Configure Task Scheduler
    Configure-TaskScheduler
    
    # Configure firewall (optional)
    Configure-FirewallRules
    
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║  Configuration Complete!              ║" -ForegroundColor Green
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Green
    Write-Host ""
    
} catch {
    Write-ConfigLog "Configuration failed: $_" -Level Error
    exit 1
}
