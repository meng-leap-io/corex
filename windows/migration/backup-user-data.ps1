#Requires -Version 5.1

param(
    [string]$Source = "$ProjectRoot",
    [string]$BackupDir,
    [string]$LogDir = "$LogDir"
)

$ErrorActionPreference = 'Stop'
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupDir = $BackupDir ? $BackupDir : "$env:USERPROFILE\Corex-Backup\$Timestamp"
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\backup-user-data.log"
$Source = Resolve-Path $Source

function Log { param([string]$M) Write-Host "$(Get-Date -Format 'HH:mm:ss') $M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }
function Chg { param([string]$M) Write-Host "→ $M" -ForegroundColor Cyan }

Log "=== User Data Backup ==="
Log "Source: $Source"
Log "Backup: $BackupDir"

# Create backup structure
$backupDirs = @(
    'storage\app', 'storage\framework\cache', 'storage\framework\sessions',
    'storage\framework\views', 'storage\logs', 'storage\database',
    'backend\bootstrap\cache', 'config', 'database\migrations',
    'database\seeders', '.env*'
)

$totalSize = 0
$totalFiles = 0

foreach ($pattern in $backupDirs) {
    $items = Get-ChildItem "$Source\$pattern" -ErrorAction SilentlyContinue
    foreach ($item in $items) {
        $relPath = $item.FullName.Substring($Source.Length).TrimStart('\')
        $dest = "$BackupDir\$relPath"
        $destDir = Split-Path $dest -Parent

        try {
            New-Item -ItemType Directory -Path $destDir -Force | Out-Null

            if ($item.PSIsContainer) {
                Copy-Item $item.FullName $dest -Recurse -Force -ErrorAction SilentlyContinue
                $fileCount = (Get-ChildItem $item.FullName -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
                $totalFiles += $fileCount
                Chg "$relPath ($fileCount files)"
            } else {
                Copy-Item $item.FullName $dest -Force
                $size = (Get-Item $dest).Length
                $totalSize += $size
                $totalFiles++
                if ($size -gt 1MB) {
                    Chg "$relPath ($([math]::Round($size/1MB,2))MB)"
                }
            }
        } catch {
            Warn "Error backing up $relPath : $_"
        }
    }
}

# Backup databases
$dbFiles = Get-ChildItem "$Source\*.sqlite", "$Source\*.sql", "$Source\*.dump" -ErrorAction SilentlyContinue
foreach ($db in $dbFiles) {
    $dest = "$BackupDir\databases\$($db.Name)"
    New-Item -ItemType Directory -Path (Split-Path $dest -Parent) -Force | Out-Null
    Copy-Item $db.FullName $dest -Force
    $size = [math]::Round($db.Length / 1MB, 2)
    Chg "databases/$($db.Name) (${size}MB)"
    $totalSize += $db.Length
    $totalFiles++
}

# Backup environment variables
$envBackup = "$BackupDir\environment-variables.txt"
Get-ChildItem Env: | Sort-Object Name |
    Where-Object { $_.Name -match '^(COREX_|DB_|REDIS_|AI_|APP_|JWT_|SANCTUM_|SESSION_)' } |
    ForEach-Object { "$($_.Name)=$($_.Value)" } |
    Out-File $envBackup -Encoding utf8
Log "Environment variables backed up: $envBackup"

# Backup registry keys
$regBackup = "$BackupDir\registry-backup.reg"
reg export "HKLM\SOFTWARE\Corex" $regBackup /y 2>$null
if (Test-Path $regBackup) {
    Log "Registry backup: $regBackup"
}

# Create manifest
$manifest = @{
    timestamp = $Timestamp
    source = $Source
    backup = $BackupDir
    total_files = $totalFiles
    total_size_mb = [math]::Round($totalSize / 1MB, 2)
    hostname = $env:COMPUTERNAME
    user = $env:USERNAME
}
$manifest | ConvertTo-Json | Out-File "$BackupDir\backup-manifest.json" -Encoding utf8

# Create restore script
@"
# Restore script for Corex backup: $Timestamp
# Source: $Source
# Run from an admin PowerShell prompt

`$ErrorActionPreference = 'Stop'
`$BackupDir = "$BackupDir"
`$Source = "$Source"

Write-Host "Restoring Corex backup from $BackupDir..." -ForegroundColor Cyan

`$dirs = Get-ChildItem `$BackupDir -Directory
foreach (`$dir in `$dirs) {
    `$target = Join-Path `$Source `$dir.Name
    Write-Host "Restoring `$(`$dir.Name) → `$target" -ForegroundColor Gray
    Copy-Item "`$(`$dir.FullName)\*" `$target -Recurse -Force
}

Write-Host "Restore complete." -ForegroundColor Green
"@ | Out-File "$BackupDir\restore.ps1" -Encoding utf8

Ok "Backup complete: $totalFiles files, $([math]::Round($totalSize / 1MB, 2)) MB"
Log "Backup directory: $BackupDir"
Log "Manifest: $BackupDir\backup-manifest.json"
Log "Restore script: $BackupDir\restore.ps1"
Log "Log: $LogFile"
