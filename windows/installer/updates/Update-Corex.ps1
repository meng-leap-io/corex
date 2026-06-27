#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Update Manager - Handle application updates and version management

.DESCRIPTION
    Manages application updates including:
    - Checking for new versions
    - Downloading updates
    - Backing up current installation
    - Rolling back on failure
    - Auto-update configuration

.PARAMETER UpdateChannel
    Update channel: stable, beta, dev (default: stable)

.PARAMETER CheckOnly
    Only check for available updates without installing

.PARAMETER AutoUpdate
    Enable auto-update checks

.PARAMETER AutoUpdateInterval
    Interval in days for auto-update checks (default: 7)

.EXAMPLE
    PS> .\Update-Corex.ps1
    PS> .\Update-Corex.ps1 -CheckOnly
    PS> .\Update-Corex.ps1 -AutoUpdate -AutoUpdateInterval 7
#>

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet('stable', 'beta', 'dev')]
    [string]$UpdateChannel = 'stable',
    
    [Parameter(Mandatory=$false)]
    [switch]$CheckOnly,
    
    [Parameter(Mandatory=$false)]
    [switch]$AutoUpdate,
    
    [Parameter(Mandatory=$false)]
    [int]$AutoUpdateInterval = 7,
    
    [Parameter(Mandatory=$false)]
    [switch]$RollBack
)

# ============================================================================
# Configuration
# ============================================================================
$ErrorActionPreference = 'Stop'

$AppName = 'Corex'
$UpdateRepositoryURL = 'https://api.github.com/repos/corex-dev/corex'
$CurrentVersion = [version]'1.0.0'
$InstallPath = (Get-ItemProperty -Path 'HKLM:\Software\Corex' -Name 'InstallPath' -ErrorAction SilentlyContinue).InstallPath
$BackupPath = Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) "Corex\Backups"
$UpdateLogFile = Join-Path $InstallPath 'logs\updates.log'

# ============================================================================
# Helper Functions
# ============================================================================

function Write-UpdateLog {
    param(
        [string]$Message,
        [ValidateSet('Info', 'Warning', 'Error', 'Success')]
        [string]$Level = 'Info'
    )
    
    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $logMessage = "[$timestamp] [$Level] $Message"
    
    Write-Host $logMessage -ForegroundColor $(
        switch ($Level) {
            'Error' { 'Red' }
            'Warning' { 'Yellow' }
            'Success' { 'Green' }
            default { 'Cyan' }
        }
    )
    
    Add-Content -Path $UpdateLogFile -Value $logMessage
}

function Get-LatestVersion {
    try {
        Write-UpdateLog "Checking for updates from $UpdateChannel channel..."
        
        $uri = "$UpdateRepositoryURL/releases?per_page=20"
        $releases = Invoke-RestMethod -Uri $uri -Headers @{
            'User-Agent' = 'Corex-Update-Manager/1.0'
            'Accept' = 'application/vnd.github.v3+json'
        }
        
        $filteredReleases = $releases | Where-Object {
            -not $_.prerelease -or ($UpdateChannel -in @('beta', 'dev') -and $_.prerelease)
        }
        
        if ($filteredReleases) {
            $latest = $filteredReleases[0]
            $latestVersion = [version]($latest.tag_name -replace '^v', '')
            
            Write-UpdateLog "Latest version available: $latestVersion" -Level Success
            
            return @{
                Version = $latestVersion
                DownloadURL = $latest.zipball_url
                ReleaseNotes = $latest.body
                PublishedAt = $latest.published_at
                PreRelease = $latest.prerelease
            }
        }
        
        Write-UpdateLog "No updates available" -Level Info
        return $null
    } catch {
        Write-UpdateLog "Error checking for updates: $_" -Level Error
        return $null
    }
}

function Backup-Installation {
    param([string]$Version)
    
    try {
        Write-UpdateLog "Creating backup of current installation ($Version)..."
        
        if (-not (Test-Path $BackupPath)) {
            New-Item -ItemType Directory -Path $BackupPath -Force | Out-Null
        }
        
        $backupName = "backup-$Version-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
        $backupDir = Join-Path $BackupPath $backupName
        
        Copy-Item -Path $InstallPath -Destination $backupDir -Recurse -Force
        
        Write-UpdateLog "Backup created: $backupDir" -Level Success
        return $backupDir
    } catch {
        Write-UpdateLog "Error creating backup: $_" -Level Error
        return $null
    }
}

function Download-Update {
    param(
        [string]$DownloadURL,
        [string]$Version
    )
    
    try {
        Write-UpdateLog "Downloading version $Version..."
        
        $downloadDir = Join-Path $InstallPath '_downloads'
        if (-not (Test-Path $downloadDir)) {
            New-Item -ItemType Directory -Path $downloadDir -Force | Out-Null
        }
        
        $downloadFile = Join-Path $downloadDir "corex-$Version.zip"
        
        Invoke-WebRequest -Uri $DownloadURL -OutFile $downloadFile -Headers @{
            'User-Agent' = 'Corex-Update-Manager/1.0'
        }
        
        Write-UpdateLog "Downloaded: $downloadFile" -Level Success
        return $downloadFile
    } catch {
        Write-UpdateLog "Error downloading update: $_" -Level Error
        return $null
    }
}

function Install-Update {
    param(
        [string]$UpdateFile,
        [string]$Version
    )
    
    try {
        Write-UpdateLog "Stopping services before update..."
        
        # Stop Corex services
        & powershell -ExecutionPolicy Bypass -NoProfile -File `
            "$InstallPath\scripts\CorexServiceWrapper.ps1" -Action Stop
        
        Start-Sleep -Seconds 2
        
        Write-UpdateLog "Installing update..."
        
        # Extract update
        $extractDir = Join-Path $InstallPath '_temp'
        if (Test-Path $extractDir) {
            Remove-Item -Path $extractDir -Recurse -Force
        }
        
        Expand-Archive -Path $UpdateFile -DestinationPath $extractDir -Force
        
        # Replace files
        Get-ChildItem -Path $extractDir -Directory | ForEach-Object {
            $dirName = $_.Name
            if (Test-Path (Join-Path $InstallPath $dirName)) {
                Remove-Item -Path (Join-Path $InstallPath $dirName) -Recurse -Force
            }
            Copy-Item -Path $_.FullName -Destination (Join-Path $InstallPath $dirName) -Recurse -Force
        }
        
        # Cleanup
        Remove-Item -Path $extractDir -Recurse -Force
        Remove-Item -Path $UpdateFile -Force
        
        # Update version registry
        Set-ItemProperty -Path 'HKLM:\Software\Corex' -Name 'Version' -Value $Version -Type String
        
        Write-UpdateLog "Update installed successfully" -Level Success
        
        # Restart services
        Write-UpdateLog "Restarting services..."
        & powershell -ExecutionPolicy Bypass -NoProfile -File `
            "$InstallPath\scripts\CorexServiceWrapper.ps1" -Action Start
        
        return $true
    } catch {
        Write-UpdateLog "Error installing update: $_" -Level Error
        return $false
    }
}

function Restore-Backup {
    param([string]$BackupDir)
    
    try {
        Write-UpdateLog "Restoring from backup: $BackupDir"
        
        # Stop services
        & powershell -ExecutionPolicy Bypass -NoProfile -File `
            "$InstallPath\scripts\CorexServiceWrapper.ps1" -Action Stop
        
        # Remove current installation
        Get-ChildItem -Path $InstallPath -Exclude 'logs', '_downloads', '_temp' | ForEach-Object {
            Remove-Item -Path $_.FullName -Recurse -Force
        }
        
        # Copy backup
        Copy-Item -Path (Join-Path $BackupDir '*') -Destination $InstallPath -Recurse -Force
        
        # Restart services
        & powershell -ExecutionPolicy Bypass -NoProfile -File `
            "$InstallPath\scripts\CorexServiceWrapper.ps1" -Action Start
        
        Write-UpdateLog "Restore completed" -Level Success
        return $true
    } catch {
        Write-UpdateLog "Error restoring from backup: $_" -Level Error
        return $false
    }
}

function Set-AutoUpdate {
    param(
        [int]$Interval
    )
    
    try {
        Write-UpdateLog "Configuring auto-update (interval: $Interval days)..."
        
        $scriptPath = "$InstallPath\scripts\Update-Corex.ps1"
        $taskName = 'Corex-AutoUpdate'
        $taskPath = '\Corex'
        
        # Create scheduled task
        $trigger = New-ScheduledTaskTrigger -Daily -At 02:00am
        $action = New-ScheduledTaskAction -Execute 'powershell.exe' `
            -Argument "-ExecutionPolicy Bypass -NoProfile -File ""$scriptPath"" -CheckOnly"
        
        Register-ScheduledTask -TaskName $taskName `
            -TaskPath $taskPath `
            -Action $action `
            -Trigger $trigger `
            -Force | Out-Null
        
        Write-UpdateLog "Auto-update task created" -Level Success
    } catch {
        Write-UpdateLog "Error configuring auto-update: $_" -Level Error
    }
}

function Cleanup-OldBackups {
    param([int]$RetentionDays = 30)
    
    try {
        if (-not (Test-Path $BackupPath)) {
            return
        }
        
        $oldBackups = Get-ChildItem -Path $BackupPath -Directory |
            Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays) }
        
        if ($oldBackups) {
            $oldBackups | ForEach-Object {
                Remove-Item -Path $_.FullName -Recurse -Force
                Write-UpdateLog "Removed old backup: $($_.Name)" -Level Info
            }
        }
    } catch {
        Write-UpdateLog "Warning cleaning up old backups: $_" -Level Warning
    }
}

# ============================================================================
# Main Process
# ============================================================================

try {
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║  Corex Update Manager                  ║" -ForegroundColor Cyan
    Write-Host "║  Current Version: $CurrentVersion" -ForegroundColor Cyan
    Write-Host "║  Channel: $UpdateChannel" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    
    # Verify installation
    if (-not $InstallPath -or -not (Test-Path $InstallPath)) {
        Write-UpdateLog "Corex installation not found" -Level Error
        exit 1
    }
    
    # Create logs directory
    $logDir = Split-Path -Parent $UpdateLogFile
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    
    Write-UpdateLog "Update Check started"
    Write-UpdateLog "Installation: $InstallPath"
    
    # Handle rollback
    if ($RollBack) {
        $latestBackup = Get-ChildItem -Path $BackupPath -Directory -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTime -Descending | Select-Object -First 1
        
        if ($latestBackup) {
            Restore-Backup -BackupDir $latestBackup.FullName
        } else {
            Write-UpdateLog "No backups available for rollback" -Level Error
            exit 1
        }
        exit 0
    }
    
    # Check for updates
    $latestRelease = Get-LatestVersion
    
    if (-not $latestRelease) {
        exit 1
    }
    
    # Compare versions
    if ($latestRelease.Version -le $CurrentVersion) {
        Write-UpdateLog "You are already running the latest version" -Level Info
        exit 0
    }
    
    Write-UpdateLog "New version available: $($latestRelease.Version)" -Level Success
    Write-UpdateLog "Release Notes:`n$($latestRelease.ReleaseNotes)"
    
    # Check only mode
    if ($CheckOnly) {
        Write-UpdateLog "Check only mode - not installing" -Level Info
        exit 0
    }
    
    # Confirm installation
    $response = Read-Host "Install version $($latestRelease.Version)? (Y/n)"
    if ($response -eq 'n') {
        Write-UpdateLog "Update cancelled" -Level Info
        exit 0
    }
    
    # Create backup
    $backup = Backup-Installation -Version $CurrentVersion
    if (-not $backup) {
        Write-UpdateLog "Update aborted - backup failed" -Level Error
        exit 1
    }
    
    # Download and install update
    $updateFile = Download-Update -DownloadURL $latestRelease.DownloadURL -Version $latestRelease.Version
    if (-not $updateFile -or -not (Test-Path $updateFile)) {
        Write-UpdateLog "Update download failed - restoring from backup" -Level Error
        Restore-Backup -BackupDir $backup
        exit 1
    }
    
    if (-not (Install-Update -UpdateFile $updateFile -Version $latestRelease.Version)) {
        Write-UpdateLog "Installation failed - restoring from backup" -Level Error
        Restore-Backup -BackupDir $backup
        exit 1
    }
    
    # Configure auto-update if requested
    if ($AutoUpdate) {
        Set-AutoUpdate -Interval $AutoUpdateInterval
    }
    
    # Cleanup old backups
    Cleanup-OldBackups
    
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║  Update Completed!                     ║" -ForegroundColor Green
    Write-Host "║  New Version: $($latestRelease.Version)" -ForegroundColor Green
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Green
    Write-Host ""
    
} catch {
    Write-UpdateLog "Fatal error: $_" -Level Error
    Write-UpdateLog $_.ScriptStackTrace -Level Error
    exit 1
}
