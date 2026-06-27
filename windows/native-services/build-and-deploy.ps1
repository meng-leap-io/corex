#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Builds the CorexServiceHost C# Windows Service and deploys it alongside the native service scripts.

.DESCRIPTION
    This script:
      1. Builds CorexServiceHost using dotnet publish (self-contained, single-file)
      2. Copies binaries to CorexRoot
      3. Registers CorexServiceHost as a Windows service
      4. Sets up recovery, dependencies, and event logging

.EXAMPLE
    PS> .\build-and-deploy.ps1 -BuildOnly
    PS> .\build-and-deploy.ps1 -CorexRoot "D:\Corex"
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$CorexRoot = 'C:\Program Files\Corex',

    [Parameter(Mandatory = $false)]
    [switch]$BuildOnly,

    [Parameter(Mandatory = $false)]
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'

$ServiceName   = 'CorexServiceHost'
$DisplayName   = 'Corex Service Host'
$Description   = 'Unified Windows service host for Corex PHP-FPM, Nginx, AI Gateway, and Redis'
$ProjectPath   = Join-Path $PSScriptRoot 'CorexServiceHost'
$PublishDir    = Join-Path $ProjectPath 'bin\publish'
$InstallDir    = Join-Path $CorexRoot 'service-host'

# ---------------------------------------------------------------------------
# Check prerequisites
# ---------------------------------------------------------------------------
Write-Host "=== CorexServiceHost Build & Deploy ===" -ForegroundColor Cyan

$dotnet = Get-Command dotnet -ErrorAction SilentlyContinue
if (-not $dotnet) {
    Write-Host "ERROR: .NET SDK not found. Install .NET 8 SDK from https://dotnet.microsoft.com/download" -ForegroundColor Red
    exit 1
}

Write-Host "Using: $($dotnet.Source)" -ForegroundColor Gray
$dotnetVersion = dotnet --version
Write-Host ".NET SDK version: $dotnetVersion" -ForegroundColor Gray

# ---------------------------------------------------------------------------
# Build
# ---------------------------------------------------------------------------
if (-not $SkipBuild) {
    Write-Host "`nBuilding CorexServiceHost..." -ForegroundColor Cyan

    # Clean previous publish
    if (Test-Path $PublishDir) { Remove-Item -Path $PublishDir -Recurse -Force }

    dotnet publish "$ProjectPath\CorexServiceHost.csproj" `
        --configuration Release `
        --runtime win-x64 `
        --self-contained true `
        --output "$PublishDir" `
        -p:PublishSingleFile=true `
        -p:IncludeNativeLibrariesForSelfExtract=true `
        -p:DebugType=embedded

    if ($LASTEXITCODE -ne 0) {
        Write-Host "Build FAILED" -ForegroundColor Red
        exit 1
    }
    Write-Host "Build successful: $PublishDir" -ForegroundColor Green
}

if ($BuildOnly) {
    Write-Host "`nBuild complete. Deploy with: .\build-and-deploy.ps1 -SkipBuild" -ForegroundColor Cyan
    exit 0
}

# ---------------------------------------------------------------------------
# Deploy
# ---------------------------------------------------------------------------
Write-Host "`nDeploying to $InstallDir..." -ForegroundColor Cyan

if (-not (Test-Path $InstallDir)) {
    New-Item -ItemType Directory -Path $InstallDir -Force | Out-Null
}

# Copy published files
Copy-Item -Path "$PublishDir\*" -Destination $InstallDir -Recurse -Force
Write-Host "Files copied to $InstallDir" -ForegroundColor Green

# ---------------------------------------------------------------------------
# Install / Update Windows Service
# ---------------------------------------------------------------------------
$binPath = "`"$InstallDir\CorexServiceHost.exe`""

try {
    $existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue

    if ($existing) {
        Write-Host "Service '$ServiceName' exists — stopping and reconfiguring..." -ForegroundColor Yellow
        Stop-Service -Name $ServiceName -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
        sc.exe config $ServiceName binPath= $binPath
        sc.exe description $ServiceName $Description
    } else {
        Write-Host "Creating service '$ServiceName'..." -ForegroundColor Cyan
        New-Service -Name $ServiceName `
            -BinaryPathName $binPath `
            -DisplayName $DisplayName `
            -Description $Description `
            -StartupType Automatic
    }

    # Recovery options
    sc.exe failure $ServiceName reset= 86400 actions= restart/5000/restart/10000/restact/15000
    sc.exe failureflag $ServiceName flag= 1

    # Pre-shutdown timeout (30 seconds to drain)
    sc.exe config $ServiceName pri= 30000

    Write-Host "Service '$ServiceName' configured successfully" -ForegroundColor Green

} catch {
    Write-Host "Service configuration failed: $_" -ForegroundColor Red
    exit 1
}

# ---------------------------------------------------------------------------
# Register Event Log source
# ---------------------------------------------------------------------------
try {
    if (-not [System.Diagnostics.EventLog]::SourceExists($ServiceName)) {
        [System.Diagnostics.EventLog]::CreateEventSource($ServiceName, 'Application')
        Write-Host "Event log source '$ServiceName' created" -ForegroundColor Green
    }
} catch {
    Write-Host "Warning: cannot create event log source: $_" -ForegroundColor Yellow
}

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
Write-Host ""
Write-Host "=== Deployment Complete ===" -ForegroundColor Green
Write-Host "Service:    $ServiceName ($DisplayName)"
Write-Host "Binary:     $binPath"
Write-Host "Start with: Start-Service $ServiceName"
Write-Host "  or:       net start $ServiceName"
Write-Host ""
Write-Host "To also install individual native services (Redis, PHP, Nginx, AI Gateway) run:" -ForegroundColor Cyan
Write-Host "  .\Install-CorexNativeServices.ps1 -Action Install" -ForegroundColor Gray
