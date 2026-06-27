#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Install-Dependencies - Install or detect application dependencies

.DESCRIPTION
    Handles installation and detection of:
    - Docker Desktop
    - PHP 8.3+
    - Python 3.12+
    - Redis
    - Ollama (optional)
    - NSSM (for Windows services)

.EXAMPLE
    PS> .\Install-Dependencies.ps1
    PS> .\Install-Dependencies.ps1 -SkipOptional
#>

param(
    [Parameter(Mandatory=$false)]
    [switch]$SkipOptional,
    
    [Parameter(Mandatory=$false)]
    [switch]$Unattended
)

# ============================================================================
# Helper Functions
# ============================================================================

function Write-Header {
    param([string]$Message)
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║  $Message" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
}

function Test-CommandExists {
    param([string]$Command)
    
    try {
        & $Command --version 2>$null
        return $true
    } catch {
        return $false
    }
}

function Install-DockerDesktop {
    Write-Host "Installing Docker Desktop..." -ForegroundColor Yellow
    
    if ($Unattended) {
        Write-Host "Automated Docker installation not supported. Please install manually:" -ForegroundColor Yellow
        Write-Host "  https://www.docker.com/products/docker-desktop" -ForegroundColor Gray
        return $false
    }
    
    $response = Read-Host "Open Docker Desktop download page? (Y/n)"
    if ($response -ne 'n') {
        Start-Process 'https://www.docker.com/products/docker-desktop'
        Write-Host "Please install Docker Desktop and run this script again" -ForegroundColor Yellow
    }
    
    return $false
}

function Install-PHP {
    Write-Host "Installing PHP 8.3+..." -ForegroundColor Yellow
    
    Write-Host ""
    Write-Host "Recommended: Use Docker image (php:8.4-fpm)" -ForegroundColor Green
    Write-Host "  Automatic: Docker will provide PHP" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Manual installation:" -ForegroundColor Yellow
    Write-Host "  Download: https://windows.php.net/" -ForegroundColor Gray
    Write-Host "  Extract to: C:\PHP" -ForegroundColor Gray
    Write-Host "  Add to PATH environment variable" -ForegroundColor Gray
    Write-Host ""
    
    if (-not $Unattended) {
        $response = Read-Host "Open PHP download page? (Y/n)"
        if ($response -ne 'n') {
            Start-Process 'https://windows.php.net/'
        }
    }
    
    return $false
}

function Install-Python {
    Write-Host "Installing Python 3.12+..." -ForegroundColor Yellow
    
    Write-Host ""
    Write-Host "Recommended: Use Docker image (python:3.12)" -ForegroundColor Green
    Write-Host "  Automatic: Docker will provide Python" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Manual installation:" -ForegroundColor Yellow
    Write-Host "  Download: https://www.python.org/" -ForegroundColor Gray
    Write-Host "  Run installer with 'Add Python to PATH' checked" -ForegroundColor Gray
    Write-Host ""
    
    if (-not $Unattended) {
        $response = Read-Host "Open Python download page? (Y/n)"
        if ($response -ne 'n') {
            Start-Process 'https://www.python.org/'
        }
    }
    
    return $false
}

function Install-Redis {
    Write-Host "Installing Redis..." -ForegroundColor Yellow
    
    Write-Host ""
    Write-Host "Recommended: Use Docker image (redis:7-alpine)" -ForegroundColor Green
    Write-Host "  Automatic: Docker Compose will provide Redis" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Manual installation (Windows via Chocolatey):" -ForegroundColor Yellow
    Write-Host "  choco install redis-64" -ForegroundColor Gray
    Write-Host ""
    
    # Try Chocolatey installation if available
    try {
        $choco = Get-Command choco -ErrorAction Stop
        if ($choco) {
            Write-Host "Chocolatey detected. Installing Redis..." -ForegroundColor Green
            & choco install redis-64 -y
            Write-Host "Redis installed successfully" -ForegroundColor Green
            return $true
        }
    } catch {}
    
    return $false
}

function Install-Ollama {
    Write-Host "Installing Ollama (for local AI models)..." -ForegroundColor Yellow
    
    Write-Host ""
    Write-Host "Ollama enables local AI model support" -ForegroundColor Green
    Write-Host ""
    Write-Host "Download: https://ollama.ai/download/windows" -ForegroundColor Gray
    Write-Host ""
    
    if (-not $Unattended) {
        $response = Read-Host "Open Ollama download page? (Y/n)"
        if ($response -ne 'n') {
            Start-Process 'https://ollama.ai/download/windows'
        }
    }
    
    return $false
}

function Install-NSSM {
    Write-Host "Installing NSSM (Non-Sucking Service Manager)..." -ForegroundColor Yellow
    
    Write-Host ""
    Write-Host "NSSM enables Windows Service integration" -ForegroundColor Green
    Write-Host ""
    
    # Try Chocolatey first
    try {
        $choco = Get-Command choco -ErrorAction Stop
        if ($choco) {
            Write-Host "Using Chocolatey to install NSSM..." -ForegroundColor Green
            & choco install nssm -y
            Write-Host "NSSM installed successfully" -ForegroundColor Green
            return $true
        }
    } catch {}
    
    # Manual installation option
    Write-Host "Manual installation:" -ForegroundColor Yellow
    Write-Host "  1. Download: https://nssm.cc/download" -ForegroundColor Gray
    Write-Host "  2. Extract to: C:\Program Files\NSSM" -ForegroundColor Gray
    Write-Host "  3. Add to PATH: C:\Program Files\NSSM\win64" -ForegroundColor Gray
    Write-Host ""
    
    if (-not $Unattended) {
        $response = Read-Host "Open NSSM download page? (Y/n)"
        if ($response -ne 'n') {
            Start-Process 'https://nssm.cc/download'
        }
    }
    
    return $false
}

# ============================================================================
# Dependency Detection
# ============================================================================

function Get-DependencyStatus {
    Write-Header "Checking Dependencies"
    
    $status = @{
        Docker = $false
        PHP = $false
        Python = $false
        Redis = $false
        Ollama = $false
        NSSM = $false
    }
    
    # Check Docker
    if (Test-CommandExists 'docker') {
        Write-Host "✓ Docker Desktop" -ForegroundColor Green
        $status.Docker = $true
    } else {
        Write-Host "✗ Docker Desktop" -ForegroundColor Red
    }
    
    # Check PHP
    if (Test-CommandExists 'php') {
        Write-Host "✓ PHP" -ForegroundColor Green
        $status.PHP = $true
    } else {
        Write-Host "⊗ PHP (optional - Docker will provide)" -ForegroundColor Yellow
    }
    
    # Check Python
    if (Test-CommandExists 'python') {
        Write-Host "✓ Python" -ForegroundColor Green
        $status.Python = $true
    } else {
        Write-Host "⊗ Python (optional - Docker will provide)" -ForegroundColor Yellow
    }
    
    # Check Redis
    if (Test-CommandExists 'redis-cli') {
        Write-Host "✓ Redis" -ForegroundColor Green
        $status.Redis = $true
    } else {
        Write-Host "⊗ Redis (optional - Docker will provide)" -ForegroundColor Yellow
    }
    
    # Check Ollama
    if (Test-CommandExists 'ollama') {
        Write-Host "✓ Ollama" -ForegroundColor Green
        $status.Ollama = $true
    } else {
        Write-Host "⊗ Ollama (optional)" -ForegroundColor Yellow
    }
    
    # Check NSSM
    if (Test-CommandExists 'nssm') {
        Write-Host "✓ NSSM" -ForegroundColor Green
        $status.NSSM = $true
    } else {
        Write-Host "⊗ NSSM (optional - Windows Service support)" -ForegroundColor Yellow
    }
    
    Write-Host ""
    return $status
}

# ============================================================================
# Main Process
# ============================================================================

try {
    Write-Header "Corex Dependency Installer"
    
    # Check current status
    $status = Get-DependencyStatus
    
    # Verify Docker (required)
    if (-not $status.Docker) {
        Write-Host "Docker Desktop is REQUIRED for Corex" -ForegroundColor Red
        Install-DockerDesktop
        Write-Host "Please install Docker Desktop first" -ForegroundColor Red
        exit 1
    }
    
    Write-Host "All critical dependencies are available!" -ForegroundColor Green
    Write-Host ""
    
    # Optional installations
    if (-not $SkipOptional) {
        Write-Host "Installing optional components..." -ForegroundColor Cyan
        Write-Host ""
        
        if (-not $status.PHP) {
            Install-PHP
        }
        
        if (-not $status.Python) {
            Install-Python
        }
        
        if (-not $status.Redis) {
            Install-Redis
        }
        
        if (-not $status.NSSM) {
            Install-NSSM
        }
        
        if (-not $status.Ollama) {
            Install-Ollama
        }
    }
    
    Write-Header "Dependency Check Complete"
    Write-Host "Status: READY FOR INSTALLATION" -ForegroundColor Green
    Write-Host ""
    
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    exit 1
}
