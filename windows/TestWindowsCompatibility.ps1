#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Windows Compatibility Test Suite

.DESCRIPTION
    Comprehensive testing and validation of Corex application on Windows platform.
    Tests system requirements, dependencies, Docker setup, and service functionality.

.PARAMETER TestCategory
    Category of tests to run: All, System, Dependencies, Docker, Services, Tasks

.EXAMPLE
    PS> .\TestWindowsCompatibility.ps1 -TestCategory All
    PS> .\TestWindowsCompatibility.ps1 -TestCategory System
#>

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet('All', 'System', 'Dependencies', 'Docker', 'Services', 'Tasks')]
    [string]$TestCategory = 'All'
)

# ============================================================================
# Configuration
# ============================================================================
$ErrorActionPreference = 'Stop'
$VerbosePreference = 'Continue'
$WarningPreference = 'Continue'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$LogDir = Join-Path $ProjectRoot 'logs'
$TestLogFile = Join-Path $LogDir 'windows-compatibility-test.log'
$ComposePath = Join-Path $ProjectRoot 'docker-compose.yml'
$EnvPath = Join-Path $ProjectRoot 'backend' '.env'

$TestResults = @{
    Passed = @()
    Failed = @()
    Warnings = @()
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
        [ValidateSet('Info', 'Warning', 'Error', 'Success', 'Test')]
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
        'Test'    { Write-Host $logMessage -ForegroundColor Cyan }
        default   { Write-Host $logMessage -ForegroundColor Gray }
    }
    
    if ($ToFile) {
        if (-not (Test-Path $LogDir)) {
            New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
        }
        Add-Content -Path $TestLogFile -Value $logMessage
    }
}

function Record-TestResult {
    param(
        [string]$TestName,
        [bool]$Passed,
        [string]$Message = ''
    )
    
    if ($Passed) {
        $TestResults.Passed += $TestName
        Write-Log "✓ $TestName" -Level Success -ToFile
        if ($Message) { Write-Log "  $Message" -Level Info -ToFile }
    } else {
        $TestResults.Failed += $TestName
        Write-Log "✗ $TestName" -Level Error -ToFile
        if ($Message) { Write-Log "  $Message" -Level Error -ToFile }
    }
}

function Record-TestWarning {
    param(
        [string]$TestName,
        [string]$Message
    )
    
    $TestResults.Warnings += @{Name = $TestName; Message = $Message}
    Write-Log "⚠ $TestName - $Message" -Level Warning -ToFile
}

# ============================================================================
# System Requirements Tests
# ============================================================================

function Test-SystemRequirements {
    Write-Log "=== System Requirements Tests ===" -Level Test
    
    # Windows Version
    $osVersion = [Environment]::OSVersion.Version
    $isWindows10Plus = $osVersion.Major -ge 10
    Record-TestResult "Windows 10 or higher" $isWindows10Plus "Detected: Windows $($osVersion.Major).$($osVersion.Minor)"
    
    # RAM
    $totalRam = (Get-CimInstance CIM_PhysicalMemory | Measure-Object -Property capacity -Sum).sum / 1GB
    $ramSufficient = $totalRam -ge 8
    Record-TestResult "Minimum RAM (8GB)" $ramSufficient "Detected: $([math]::Round($totalRam)) GB"
    if ($totalRam -lt 16) {
        Record-TestWarning "RAM" "Recommended: 16GB for optimal performance. Current: $([math]::Round($totalRam)) GB"
    }
    
    # CPU Cores
    $cpuCores = (Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).sum
    $cpuSufficient = $cpuCores -ge 4
    Record-TestResult "Minimum CPU cores (4)" $cpuSufficient "Detected: $cpuCores cores"
    
    # Disk Space
    $systemDrive = Get-Item C:\
    $freeSpace = $systemDrive.PSDrive.Free / 1GB
    $spaceSufficient = $freeSpace -ge 30
    Record-TestResult "Disk space (30GB)" $spaceSufficient "Available: $([math]::Round($freeSpace)) GB"
    
    # Admin privileges
    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
    Record-TestResult "Administrator privileges" $isAdmin
    
    Write-Log ""
}

# ============================================================================
# Dependencies Tests
# ============================================================================

function Test-Dependencies {
    Write-Log "=== Dependencies Tests ===" -Level Test
    
    # PowerShell Version
    $psVersion = $PSVersionTable.PSVersion
    $psSufficient = $psVersion.Major -ge 5 -and $psVersion.Minor -ge 1
    Record-TestResult "PowerShell 5.1 or higher" $psSufficient "Detected: $psVersion"
    
    # Docker
    $dockerInstalled = $false
    try {
        $dockerVersion = & docker --version 2>$null
        $dockerInstalled = $?
        if ($dockerInstalled) {
            Record-TestResult "Docker installed" $true $dockerVersion
        }
    } catch {}
    
    if (-not $dockerInstalled) {
        Record-TestResult "Docker installed" $false "Docker is required. Download from https://www.docker.com/products/docker-desktop"
    }
    
    # Docker Compose
    $composeInstalled = $false
    try {
        $composeVersion = & docker compose version 2>$null
        $composeInstalled = $?
        if ($composeInstalled) {
            Record-TestResult "Docker Compose installed" $true $composeVersion
        }
    } catch {}
    
    if (-not $composeInstalled) {
        Record-TestResult "Docker Compose installed" $false "Docker Compose v2 is required"
    }
    
    # PHP
    $phpInstalled = $false
    try {
        $phpVersion = & php --version 2>$null | Select-Object -First 1
        $phpInstalled = $?
        if ($phpInstalled) {
            Record-TestResult "PHP installed" $true $phpVersion
        }
    } catch {}
    
    if (-not $phpInstalled) {
        Record-TestWarning "PHP" "PHP not in PATH (required for Task Scheduler jobs)"
    }
    
    # Git
    $gitInstalled = $false
    try {
        $gitVersion = & git --version 2>$null
        $gitInstalled = $?
        if ($gitInstalled) {
            Record-TestResult "Git installed" $true $gitVersion
        }
    } catch {}
    
    if (-not $gitInstalled) {
        Record-TestWarning "Git" "Git not found (recommended for version control)"
    }
    
    Write-Log ""
}

# ============================================================================
# Docker Configuration Tests
# ============================================================================

function Test-DockerConfiguration {
    Write-Log "=== Docker Configuration Tests ===" -Level Test
    
    if (-not (Test-Path $ComposePath)) {
        Record-TestResult "Docker Compose file exists" $false "Not found: $ComposePath"
        return
    }
    
    Record-TestResult "Docker Compose file exists" $true $ComposePath
    
    if (-not (Test-Path $EnvPath)) {
        Record-TestWarning ".env file" "Not found: $EnvPath. Copy from .env.example and configure"
    } else {
        Record-TestResult ".env file exists" $true
    }
    
    # Test Docker daemon
    $dockerDaemonRunning = $false
    try {
        $info = & docker info 2>$null
        $dockerDaemonRunning = $?
    } catch {}
    
    Record-TestResult "Docker daemon running" $dockerDaemonRunning
    
    # Test Docker network
    $networkExists = $false
    try {
        $network = & docker network ls --filter name=corex-network --quiet 2>$null
        $networkExists = -not [string]::IsNullOrWhiteSpace($network)
    } catch {}
    
    if ($dockerDaemonRunning) {
        Record-TestWarning "Docker network" "Network will be created on first service start"
    }
    
    Write-Log ""
}

# ============================================================================
# Service Readiness Tests
# ============================================================================

function Test-ServiceReadiness {
    Write-Log "=== Service Readiness Tests ===" -Level Test
    
    if (-not (Test-Path $ComposePath)) {
        Record-TestResult "Service configuration exists" $false
        return
    }
    
    Record-TestResult "Service configuration exists" $true
    
    # Parse docker-compose for services
    $services = @('postgres', 'redis', 'php', 'nginx', 'ai-gateway', 'queue', 'scheduler')
    
    foreach ($service in $services) {
        $serviceInCompose = (Get-Content $ComposePath) -match "^\s+$service\s*:"
        Record-TestResult "Service defined: $service" $serviceInCompose
    }
    
    Write-Log ""
}

# ============================================================================
# Windows Task Scheduler Tests
# ============================================================================

function Test-TaskScheduler {
    Write-Log "=== Windows Task Scheduler Tests ===" -Level Test
    
    try {
        $tasks = Get-ScheduledTask -TaskPath '\Corex' -ErrorAction SilentlyContinue
        if ($tasks) {
            $taskCount = ($tasks | Measure-Object).Count
            Record-TestResult "Task Scheduler folder exists" $true "Found $taskCount tasks"
        } else {
            Record-TestWarning "Task Scheduler" "Corex tasks not yet installed. Run: task-install.bat"
        }
    } catch {
        Record-TestWarning "Task Scheduler" "Unable to read task scheduler"
    }
    
    Write-Log ""
}

# ============================================================================
# Integration Tests
# ============================================================================

function Test-Integration {
    Write-Log "=== Integration Tests ===" -Level Test
    
    # Path accessibility
    $pathAccessible = Test-Path $ProjectRoot
    Record-TestResult "Project root accessible" $pathAccessible $ProjectRoot
    
    # Logs directory
    $logsWritable = $false
    try {
        if (-not (Test-Path $LogDir)) {
            New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
        }
        $testFile = Join-Path $LogDir '.test-write'
        "test" | Out-File -FilePath $testFile -Force
        Remove-Item -Path $testFile -Force
        $logsWritable = $true
    } catch {}
    
    Record-TestResult "Logs directory writable" $logsWritable
    
    Write-Log ""
}

# ============================================================================
# Test Report
# ============================================================================

function Show-TestReport {
    Write-Log "=== Test Summary ===" -Level Test
    
    $totalTests = $TestResults.Passed.Count + $TestResults.Failed.Count
    
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║    Corex Windows Compatibility Tests   ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    
    Write-Host "✓ Passed: $($TestResults.Passed.Count)" -ForegroundColor Green
    Write-Host "✗ Failed: $($TestResults.Failed.Count)" -ForegroundColor Red
    Write-Host "⚠ Warnings: $($TestResults.Warnings.Count)" -ForegroundColor Yellow
    Write-Host ""
    
    if ($TestResults.Failed.Count -gt 0) {
        Write-Host "Failed tests:" -ForegroundColor Red
        foreach ($test in $TestResults.Failed) {
            Write-Host "  ✗ $test" -ForegroundColor Red
        }
        Write-Host ""
    }
    
    if ($TestResults.Warnings.Count -gt 0) {
        Write-Host "Warnings:" -ForegroundColor Yellow
        foreach ($warning in $TestResults.Warnings) {
            Write-Host "  ⚠ $($warning.Name): $($warning.Message)" -ForegroundColor Yellow
        }
        Write-Host ""
    }
    
    $status = if ($TestResults.Failed.Count -eq 0) { "READY" } else { "ISSUES FOUND" }
    $color = if ($TestResults.Failed.Count -eq 0) { "Green" } else { "Red" }
    
    Write-Host "Status: $status" -ForegroundColor $color
    Write-Host "Log file: $TestLogFile" -ForegroundColor Gray
    Write-Host ""
}

# ============================================================================
# Main Execution
# ============================================================================

try {
    "Corex Windows Compatibility Test" | Out-File -FilePath $TestLogFile -Force
    "Test started: $(Get-Date)" | Add-Content -Path $TestLogFile
    Add-Content -Path $TestLogFile -Value ""
    
    switch ($TestCategory) {
        'All' {
            Test-SystemRequirements
            Test-Dependencies
            Test-DockerConfiguration
            Test-ServiceReadiness
            Test-TaskScheduler
            Test-Integration
        }
        'System' { Test-SystemRequirements }
        'Dependencies' { Test-Dependencies }
        'Docker' { Test-DockerConfiguration }
        'Services' { Test-ServiceReadiness }
        'Tasks' { Test-TaskScheduler }
    }
    
    Show-TestReport
    
    Add-Content -Path $TestLogFile -Value ""
    Add-Content -Path $TestLogFile -Value "Test completed: $(Get-Date)"
    
    exit if ($TestResults.Failed.Count -gt 0) { 1 } else { 0 }
} catch {
    Write-Log "Fatal error: $_" -Level Error -ToFile
    exit 1
}
