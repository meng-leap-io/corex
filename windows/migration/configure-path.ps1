#Requires -Version 5.1
#Requires -RunAsAdministrator

param(
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [string]$LogDir,
    [switch]$Remove
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\configure-path.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }

Log "=== Windows PATH Configuration ==="
Log "Installing tools at: $InstallDir"

$pathEntries = @(
    "$InstallDir\tools\php",
    "$InstallDir\tools\python",
    "$InstallDir\tools\python\Scripts",
    "$InstallDir\tools\redis",
    "$InstallDir\tools\nginx",
    "$InstallDir\tools\nodejs",
    "$InstallDir\bin",
    "$DataDir\bin"
)

$scope = 'Machine'

if ($Remove) {
    Log "Removing Corex entries from PATH..."
    $currentPath = [Environment]::GetEnvironmentVariable('Path', $scope)
    $newPath = ($currentPath -split ';' | Where-Object { $_ -notmatch 'Corex|corex' }) -join ';'
    [Environment]::SetEnvironmentVariable('Path', $newPath, $scope)
    Ok "Corex entries removed from PATH"
    return
}

$currentPath = [Environment]::GetEnvironmentVariable('Path', $scope)
$added = 0

foreach ($entry in $pathEntries) {
    if (Test-Path $entry) {
        if ($currentPath -split ';' -contains $entry) {
            Log "Already in PATH: $entry"
        } else {
            $currentPath = "$currentPath;$entry"
            Log "Added to PATH: $entry"
            $added++
        }
    } else {
        Log "Path not found (created on install): $entry"
        # Add anyway — directory will exist after setup
        if ($currentPath -split ';' -notcontains $entry) {
            $currentPath = "$currentPath;$entry"
            $added++
        }
    }
}

# Deduplicate
$uniquePath = ($currentPath -split ';' | Select-Object -Unique) -join ';'

# Check PATH length
if ($uniquePath.Length -gt 4096) {
    Warn "PATH length is $($uniquePath.Length) (max 4096). Consider removing unused entries."
}

[Environment]::SetEnvironmentVariable('Path', $uniquePath, $scope)

# Also set for current session
$env:Path = $uniquePath

# Add COREX_HOME
[Environment]::SetEnvironmentVariable('COREX_HOME', $InstallDir, 'Machine')
Log "Set COREX_HOME = $InstallDir"

Ok "PATH configured: $added entries added"
Log "Total PATH length: $($uniquePath.Length) characters"

# Verify key executables
$checks = @(
    @{ Name = 'PHP'; Cmd = 'php --version' },
    @{ Name = 'Python'; Cmd = 'python --version' },
    @{ Name = 'Redis'; Cmd = 'redis-server --version' },
    @{ Name = 'Nginx'; Cmd = 'nginx -v' }
)

Log ""
Log "Verifying executables in new PATH:"
foreach ($check in $checks) {
    try {
        $result = & cmd /c "$($check.Cmd) 2>&1" 2>$null
        if ($LASTEXITCODE -eq 0) {
            Ok "$($check.Name): $($result -join '')"
        } else {
            Warn "$($check.Name): not found in PATH"
        }
    } catch {
        Warn "$($check.Name): not found in PATH"
    }
}

Log "Log: $LogFile"
