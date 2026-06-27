#Requires -Version 5.1
#Requires -RunAsAdministrator

param(
    [string]$ProjectRoot,
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs",
    [string]$BackupDir
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\migrate-user-data.log"
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupDir = $BackupDir ? $BackupDir : "$env:USERPROFILE\Corex-Backup\$Timestamp"

function Log { param([string]$M) Write-Host "$(Get-Date -Format 'HH:mm:ss') $M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green; "✓ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow; "⚠ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red; "✗ $M" | Out-File $LogFile -Encoding utf8 -Append }

Log "=== User Data Migration ==="

# Directories to migrate (in ProjectRoot)
$sourceDirs = @(
    @{ Source = "storage\app"; Dest = "app" },
    @{ Source = "storage\app\public"; Dest = "public" },
    @{ Source = "storage\framework\cache"; Dest = "cache" },
    @{ Source = "storage\framework\sessions"; Dest = "sessions" },
    @{ Source = "storage\framework\views"; Dest = "views" },
    @{ Source = "storage\logs"; Dest = "logs" },
    @{ Source = "storage\database"; Dest = "database" }
)

$dataDir = New-Item -ItemType Directory -Path $DataDir -Force | Select-Object -ExpandProperty FullName
$dataSubdirs = @('app', 'public', 'cache', 'sessions', 'views', 'logs', 'database', 'run', 'tmp')

# Create data directories
foreach ($sub in $dataSubdirs) {
    New-Item -ItemType Directory -Path "$DataDir\$sub" -Force | Out-Null
}
Ok "Created data directory structure: $DataDir"

# Backup existing storage
$backupStorageDir = "$BackupDir\storage"
New-Item -ItemType Directory -Path $backupStorageDir -Force | Out-Null

if (Test-Path "$ProjectRoot\storage") {
    Log "Backing up storage to: $backupStorageDir"
    Copy-Item "$ProjectRoot\storage\*" $backupStorageDir -Recurse -Force -ErrorAction SilentlyContinue
    Ok "Storage backed up"
}

# Migrate storage directories
foreach ($dir in $sourceDirs) {
    $src = "$ProjectRoot\$($dir.Source)"
    $dst = "$DataDir\$($dir.Dest)"

    if (Test-Path $src) {
        try {
            $count = (Get-ChildItem $src -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
            if ($count -gt 0) {
                Copy-Item "$src\*" $dst -Recurse -Force -ErrorAction SilentlyContinue
                Log "Migrated $($dir.Source): $count files → $dst"
            }
        } catch {
            Warn "Could not migrate $($dir.Source): $_"
        }
    } else {
        Log "Source not found: $src (skipping)"
    }
}

# Migrate .env if exists
$envFile = "$ProjectRoot\.env"
$envDest = "$DataDir\.env"
if (Test-Path $envFile) {
    Copy-Item $envFile $envDest -Force
    Log "Migrated .env → $envDest"
}

# Migrate any sqlite database if exists
$sqliteFiles = Get-ChildItem "$ProjectRoot\storage\*.sqlite" -ErrorAction SilentlyContinue
foreach ($sf in $sqliteFiles) {
    Copy-Item $sf.FullName "$DataDir\database\" -Force
    Log "Migrated database: $($sf.Name)"
}

# Create storage symlinks from old locations to new
Log "Creating junction points for backward compatibility..."

$linkPairs = @(
    @{ Link = "$ProjectRoot\storage\app"; Target = "$DataDir\app" },
    @{ Link = "$ProjectRoot\storage\logs"; Target = "$DataDir\logs" },
    @{ Link = "$ProjectRoot\storage\framework\cache"; Target = "$DataDir\cache" },
    @{ Link = "$ProjectRoot\storage\framework\sessions"; Target = "$DataDir\sessions" },
    @{ Link = "$ProjectRoot\storage\framework\views"; Target = "$DataDir\views" }
)

foreach ($pair in $linkPairs) {
    $link = $pair.Link
    $target = $pair.Target

    # Remove old directory but save if it has data
    if (Test-Path $link) {
        $hasData = (Get-ChildItem $link -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count -gt 0
        if ($hasData -and -not (Test-Path "$link.old")) {
            Rename-Item $link "$link.old" -Force
            Log "Renamed existing: $link → $link.old"
        } else {
            Remove-Item $link -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    # Create junction
    if (Test-Path $target) {
        New-Item -ItemType Junction -Path $link -Target $target -Force | Out-Null
        Log "Junction created: $link → $target"
    }
}

# Update storage_path in Laravel config
$configFile = "$ProjectRoot\bootstrap\app.php"
if (Test-Path $configFile) {
    $content = Get-Content $configFile -Raw
    if ($content -notmatch 'storage_path') {
        Log "Note: Update bootstrap/app.php to set storage_path to $DataDir if needed."
    }
}

Ok "User data migration complete"
Log "Data directory: $DataDir"
Log "Backup: $BackupDir"
Log "Log: $LogFile"
