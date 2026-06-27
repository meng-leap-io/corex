#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Installation Framework - Master Installer

.DESCRIPTION
    Comprehensive installation script for Corex on Windows. Handles:
    - System validation
    - Dependency installation
    - Docker configuration
    - Service registration
    - Initial startup

.PARAMETER InstallPath
    Installation directory (default: C:\Program Files\Corex)

.PARAMETER SkipValidation
    Skip system requirements validation

.PARAMETER Unattended
    Run installation without user prompts

.EXAMPLE
    PS> .\Install-Corex.ps1
    PS> .\Install-Corex.ps1 -InstallPath "D:\Applications\Corex" -Unattended
#>

param(
    [Parameter(Mandatory=$false)]
    [string]$InstallPath = 'C:\Program Files\Corex',
    
    [Parameter(Mandatory=$false)]
    [switch]$SkipValidation,
    
    [Parameter(Mandatory=$false)]
    [switch]$Unattended,
    
    [Parameter(Mandatory=$false)]
    [switch]$Uninstall
)

# ============================================================================
# Configuration
# ============================================================================
$ErrorActionPreference = 'Stop'
$VerbosePreference = 'Continue'

$AppName = 'Corex'
$AppVersion = '1.0.0'
$Author = 'Corex Development'
$LogPath = Join-Path $InstallPath 'logs'
$LogFile = Join-Path $LogPath 'install-$(Get-Date -Format "yyyyMMdd-HHmmss").log'

# Dependency versions
$MinPHPVersion = '8.3'
$MinPythonVersion = '3.12'
$MinDockerVersion = '20.10'

# ============================================================================
# Helper Functions
# ============================================================================

function Write-InstallLog {
    param(
        [string]$Message,
        [ValidateSet('Info', 'Warning', 'Error', 'Success')]
        [string]$Level = 'Info'
    )
    
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $logMessage = "[$timestamp] [$Level] $Message"
    
    switch ($Level) {
        'Error'   { Write-Host $logMessage -ForegroundColor Red }
        'Warning' { Write-Host $logMessage -ForegroundColor Yellow }
        'Success' { Write-Host $logMessage -ForegroundColor Green }
        default   { Write-Host $logMessage -ForegroundColor Cyan }
    }
    
    if (-not (Test-Path $LogPath)) {
        New-Item -ItemType Directory -Path $LogPath -Force | Out-Null
    }
    Add-Content -Path $LogFile -Value $logMessage
}

function Test-Administrator {
    $isAdmin = ([Security.Principal.WindowsPrincipal] `
        [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
    
    if (-not $isAdmin) {
        Write-InstallLog 'Administrator privileges are required. Please run as Administrator.' -Level Error
        exit 1
    }
}

function Test-SystemRequirements {
    Write-InstallLog 'Checking system requirements...'
    
    # Windows Version
    $osVersion = [Environment]::OSVersion.Version
    if ($osVersion.Major -lt 10) {
        Write-InstallLog "Windows 10 or later is required. Current: Windows $($osVersion.Major).$($osVersion.Minor)" -Level Error
        return $false
    }
    
    # RAM
    $totalRam = (Get-CimInstance CIM_PhysicalMemory | Measure-Object -Property capacity -Sum).sum / 1GB
    if ($totalRam -lt 8) {
        Write-InstallLog "Minimum 8GB RAM required. Current: $totalRam GB" -Level Warning
    } else {
        Write-InstallLog "RAM check passed: $([math]::Round($totalRam)) GB" -Level Success
    }
    
    # Disk space
    $drive = Get-PSDrive C
    $freeSpace = $drive.Free / 1GB
    if ($freeSpace -lt 30) {
        Write-InstallLog "Minimum 30GB disk space required. Available: $freeSpace GB" -Level Error
        return $false
    }
    
    Write-InstallLog "System requirements validated" -Level Success
    return $true
}

function Install-Docker {
    Write-InstallLog 'Checking Docker Desktop...'
    
    # Check if Docker is already installed
    try {
        $dockerVersion = & docker --version 2>$null
        if ($?) {
            Write-InstallLog "Docker is already installed: $dockerVersion" -Level Success
            return $true
        }
    } catch {}
    
    Write-InstallLog 'Docker Desktop is not installed' -Level Warning
    Write-InstallLog 'Visit: https://www.docker.com/products/docker-desktop' -Level Info
    Write-InstallLog 'After installation, run this script again' -Level Info
    
    if (-not $Unattended) {
        $response = Read-Host 'Open Docker Desktop download page? (Y/n)'
        if ($response -ne 'n') {
            Start-Process 'https://www.docker.com/products/docker-desktop'
        }
    }
    
    return $false
}

function Install-PHP {
    Write-InstallLog 'Checking PHP...'
    
    # Check if PHP is in PATH
    try {
        $phpVersion = & php --version 2>$null
        if ($?) {
            Write-InstallLog "PHP is installed: $phpVersion" -Level Success
            return $true
        }
    } catch {}
    
    Write-InstallLog 'PHP 8.3+ is required but not found in PATH' -Level Warning
    Write-InstallLog 'Recommended: Use Docker (php:8.4-fpm image)' -Level Info
    Write-InstallLog 'Or download from: https://windows.php.net/' -Level Info
    
    return $false
}

function Install-Python {
    Write-InstallLog 'Checking Python...'
    
    # Check if Python is in PATH
    try {
        $pythonVersion = & python --version 2>$null
        if ($?) {
            Write-InstallLog "Python is installed: $pythonVersion" -Level Success
            return $true
        }
    } catch {}
    
    Write-InstallLog 'Python 3.12+ is required but not found in PATH' -Level Warning
    Write-InstallLog 'Recommended: Use Docker (python:3.12 image)' -Level Info
    Write-InstallLog 'Or download from: https://www.python.org/' -Level Info
    
    return $false
}

function Copy-ApplicationFiles {
    param([string]$SourcePath, [string]$DestPath)
    
    Write-InstallLog "Copying application files to $DestPath..."
    
    if (-not (Test-Path $DestPath)) {
        New-Item -ItemType Directory -Path $DestPath -Force | Out-Null
    }
    
    # Copy main directories
    $dirsToExclude = @('.git', '.github', 'vendor', 'node_modules', '__pycache__', '.mypy_cache')
    
    Get-ChildItem -Path $SourcePath -Directory | Where-Object { $_.Name -notin $dirsToExclude } | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination (Join-Path $DestPath $_.Name) -Recurse -Force
        Write-InstallLog "  Copied: $($_.Name)" -Level Success
    }
    
    # Copy key files
    $filesToCopy = @('docker-compose.yml', '.env.example', 'README.md', 'LICENSE')
    $filesToCopy | ForEach-Object {
        $sourceFile = Join-Path $SourcePath $_
        if (Test-Path $sourceFile) {
            Copy-Item -Path $sourceFile -Destination $DestPath -Force
            Write-InstallLog "  Copied: $_" -Level Success
        }
    }
    
    Write-InstallLog 'File copy completed' -Level Success
}

function Register-WindowsServices {
    param([string]$AppPath)
    
    Write-InstallLog 'Registering Windows services...'
    
    # Check for NSSM (Non-Sucking Service Manager)
    $nssmPath = Get-Command nssm -ErrorAction SilentlyContinue
    if (-not $nssmPath) {
        Write-InstallLog 'NSSM not found. Skipping service registration.' -Level Warning
        Write-InstallLog 'Download from: https://nssm.cc/download' -Level Info
        return $false
    }
    
    # Register Corex service
    try {
        $serviceName = 'CorexPlatform'
        $scriptPath = Join-Path $AppPath 'scripts\CorexServiceWrapper.ps1'
        
        & nssm install $serviceName `
            "powershell.exe" `
            "-ExecutionPolicy Bypass -NoProfile -File ""$scriptPath"" -Action ServiceLoop"
        
        & nssm set $serviceName DisplayName "Corex AI Development Platform"
        & nssm set $serviceName AppDirectory $AppPath
        & nssm set $serviceName AppNoConsole 1
        & nssm set $serviceName Start SERVICE_AUTO_START
        
        Write-InstallLog "Service registered: $serviceName" -Level Success
        return $true
    } catch {
        Write-InstallLog "Error registering service: $_" -Level Error
        return $false
    }
}

function Create-EnvironmentFile {
    param([string]$AppPath)
    
    Write-InstallLog 'Creating environment configuration...'
    
    $envFile = Join-Path $AppPath 'backend\.env'
    $envExample = Join-Path $AppPath 'backend\.env.example'
    
    if (Test-Path $envFile) {
        Write-InstallLog '.env file already exists' -Level Info
        return
    }
    
    if (Test-Path $envExample) {
        Copy-Item -Path $envExample -Destination $envFile
        Write-InstallLog '.env file created from template' -Level Success
    } else {
        Write-InstallLog '.env.example not found' -Level Warning
    }
    
    # Generate secure random APP_KEY
    $appKey = 'base64:' + [Convert]::ToBase64String((1..32 | ForEach-Object { [byte](Get-Random -Maximum 256) }))
    
    if (Test-Path $envFile) {
        $content = Get-Content $envFile -Raw
        $content = $content -replace 'APP_KEY=.*', "APP_KEY=$appKey"
        Set-Content -Path $envFile -Value $content
        Write-InstallLog 'APP_KEY generated' -Level Success
    }
}

function Create-DesktopShortcuts {
    param([string]$AppPath)
    
    Write-InstallLog 'Creating desktop shortcuts...'
    
    $desktopPath = [Environment]::GetFolderPath('Desktop')
    $shortcutPath = Join-Path $desktopPath 'Corex.lnk'
    
    # Create WScript.Shell COM object
    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($shortcutPath)
    $shortcut.TargetPath = 'http://localhost'
    $shortcut.Description = 'Corex AI Development Platform'
    $shortcut.IconLocation = Join-Path $AppPath 'corex.ico'
    $shortcut.Save()
    
    Write-InstallLog "Desktop shortcut created: $shortcutPath" -Level Success
}

function Register-ApplicationRegistry {
    param([string]$AppPath, [string]$Version)
    
    Write-InstallLog 'Registering application in Windows registry...'
    
    $regPath = 'HKLM:\Software\Corex'
    
    if (-not (Test-Path $regPath)) {
        New-Item -Path $regPath -Force | Out-Null
    }
    
    Set-ItemProperty -Path $regPath -Name 'InstallPath' -Value $AppPath -Type String
    Set-ItemProperty -Path $regPath -Name 'Version' -Value $Version -Type String
    Set-ItemProperty -Path $regPath -Name 'DisplayName' -Value 'Corex AI Development Platform' -Type String
    Set-ItemProperty -Path $regPath -Name 'Publisher' -Value $Author -Type String
    Set-ItemProperty -Path $regPath -Name 'InstallDate' -Value (Get-Date -Format 'yyyyMMdd') -Type String
    
    Write-InstallLog 'Application registered in registry' -Level Success
}

function Uninstall-Application {
    param([string]$AppPath)
    
    Write-InstallLog 'Uninstalling Corex...'
    
    # Stop services
    try {
        $pythonScript = Join-Path $AppPath 'scripts\CorexServiceWrapper.ps1'
        if (Test-Path $pythonScript) {
            & powershell -ExecutionPolicy Bypass -NoProfile -File $pythonScript -Action Stop
        }
    } catch {
        Write-InstallLog "Warning stopping services: $_" -Level Warning
    }
    
    # Unregister Windows service
    try {
        & nssm remove CorexPlatform confirm
        Write-InstallLog 'Windows service unregistered' -Level Success
    } catch {
        Write-InstallLog "Warning removing service: $_" -Level Warning
    }
    
    # Remove registry entries
    try {
        Remove-Item -Path 'HKLM:\Software\Corex' -Recurse -Force -ErrorAction SilentlyContinue
        Write-InstallLog 'Registry entries removed' -Level Success
    } catch {}
    
    # Remove application directory
    try {
        Remove-Item -Path $AppPath -Recurse -Force
        Write-InstallLog "Application directory removed: $AppPath" -Level Success
    } catch {
        Write-InstallLog "Warning removing directory: $_" -Level Warning
    }
    
    Write-InstallLog 'Uninstallation completed' -Level Success
}

# ============================================================================
# Main Installation Process
# ============================================================================

try {
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║  Corex AI Development Platform Setup   ║" -ForegroundColor Cyan
    Write-Host "║  Version $AppVersion                           ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    
    # Check permissions
    Test-Administrator
    
    # Handle uninstall
    if ($Uninstall) {
        Uninstall-Application -AppPath $InstallPath
        exit 0
    }
    
    # Validate system requirements
    if (-not $SkipValidation) {
        if (-not (Test-SystemRequirements)) {
            exit 1
        }
    }
    
    # Check and install dependencies
    Write-InstallLog '========== Checking Dependencies =========='
    $dockerOK = Install-Docker
    $phpOK = Install-PHP
    $pythonOK = Install-Python
    
    if (-not $dockerOK) {
        Write-InstallLog 'Docker Desktop is required. Please install it and run this script again.' -Level Error
        exit 1
    }
    
    # Create installation directory
    Write-InstallLog "Installation path: $InstallPath"
    if (-not (Test-Path $InstallPath)) {
        New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null
    }
    
    # Create logs directory
    if (-not (Test-Path $LogPath)) {
        New-Item -ItemType Directory -Path $LogPath -Force | Out-Null
    }
    
    # Copy application files
    Write-InstallLog '========== Copying Files =========='
    $sourceDir = Split-Path -Parent $PSScriptRoot
    Copy-ApplicationFiles -SourcePath $sourceDir -DestPath $InstallPath
    
    # Copy scripts directory
    $scriptsSrc = Join-Path (Split-Path -Parent $PSScriptRoot) 'windows'
    $scriptsDst = Join-Path $InstallPath 'scripts'
    if (Test-Path $scriptsSrc) {
        Copy-Item -Path $scriptsSrc -Destination $scriptsDst -Recurse -Force
        Write-InstallLog 'Management scripts copied' -Level Success
    }
    
    # Create environment file
    Write-InstallLog '========== Configuring Application =========='
    Create-EnvironmentFile -AppPath $InstallPath
    
    # Create shortcuts
    if (-not $Unattended) {
        Create-DesktopShortcuts -AppPath $InstallPath
    }
    
    # Register in Windows
    Write-InstallLog '========== Registering with Windows =========='
    Register-ApplicationRegistry -AppPath $InstallPath -Version $AppVersion
    
    # Register services (optional, requires NSSM)
    Write-InstallLog '========== Setting Up Services =========='
    Register-WindowsServices -AppPath $InstallPath | Out-Null
    
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║  Installation Complete!               ║" -ForegroundColor Green
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Green
    Write-Host ""
    Write-Host "Installation details logged to: $LogFile" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Cyan
    Write-Host "  1. Run: $InstallPath\scripts\start.bat" -ForegroundColor Gray
    Write-Host "  2. Open: http://localhost" -ForegroundColor Gray
    Write-Host "  3. Check status: $InstallPath\scripts\status.bat" -ForegroundColor Gray
    Write-Host ""
    
} catch {
    Write-InstallLog "Installation failed: $_" -Level Error
    Write-InstallLog $_.ScriptStackTrace -Level Error
    exit 1
}
