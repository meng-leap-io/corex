#Requires -Version 5.1

<#
.SYNOPSIS
    Downloads all portable Windows binaries needed for Corex desktop distribution.

.DESCRIPTION
    Downloads and extracts portable versions of:
    - PHP 8.3 (nts x64 + ARM64)
    - Python 3.12 (embeddable)
    - Nginx (windows portable)
    - Redis (windows portable)
    - Node.js (for NativePHP)
    - NSSM (service manager)

.PARAMETER OutputDir
    Directory to store downloaded binaries

.PARAMETER Architecture
    Target architecture: x64 or arm64

.PARAMETER Force
    Re-download even if files exist
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$OutputDir = (Join-Path $PSScriptRoot '..\runtime'),
    [ValidateSet('x64', 'arm64')]
    [string]$Architecture = 'x64',
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$tools = @()

# ── PHP 8.3 ────────────────────────────────────────────────────────────
$phpUrl = if ($Architecture -eq 'arm64') {
    'https://github.com/php-for-windows/php-sdk/releases/latest/download/php-8.3-nts-windows-arm64.zip'
} else {
    'https://windows.php.net/downloads/releases/php-8.3.10-nts-Win32-vs16-x64.zip'
}
$tools += @{
    Name = 'PHP'
    Url = $phpUrl
    Dir = 'php'
    ZipDir = $null  # some PHP zips have a top-level dir, some don't
    Required = @(
        'php.exe', 'php8ts.dll', 'php_cli.exe',
        'ext/php_mbstring.dll', 'ext/php_pdo_pgsql.dll', 'ext/php_pdo_sqlite.dll',
        'ext/php_redis.dll', 'ext/php_openssl.dll', 'ext/php_curl.dll',
        'ext/php_fileinfo.dll', 'ext/php_gd.dll', 'ext/php_intl.dll',
        'ext/php_json.dll', 'ext/php_sodium.dll', 'ext/php_tidy.dll',
        'ext/php_tokenizer.dll', 'ext/php_xml.dll', 'ext/php_xmlwriter.dll',
        'ext/php_zlib.dll'
    )
}

# ── Python 3.12 (embeddable) ───────────────────────────────────────────
$tools += @{
    Name = 'Python'
    Url = 'https://www.python.org/ftp/python/3.12.5/python-3.12.5-embed-amd64.zip'
    Dir = 'python'
    ZipDir = $null
    Required = @('python.exe', 'python3.dll', 'python312.dll')
}

# ── Nginx ──────────────────────────────────────────────────────────────
$tools += @{
    Name = 'Nginx'
    Url = 'https://nginx.org/download/nginx-1.26.1.zip'
    Dir = 'nginx'
    ZipDir = 'nginx-1.26.1'
    Required = @('nginx.exe')
}

# ── Redis ──────────────────────────────────────────────────────────────
$tools += @{
    Name = 'Redis'
    Url = 'https://github.com/redis-windows/redis-windows/releases/latest/download/Redis-7.2.5-Windows-x64.zip'
    Dir = 'redis'
    ZipDir = $null
    Required = @('redis-server.exe', 'redis-cli.exe')
}

# ── Node.js ────────────────────────────────────────────────────────────
$nodeUrl = if ($Architecture -eq 'arm64') {
    'https://nodejs.org/dist/v20.17.0/node-v20.17.0-win-arm64.zip'
} else {
    'https://nodejs.org/dist/v20.17.0/node-v20.17.0-win-x64.zip'
}
$tools += @{
    Name = 'Node.js'
    Url = $nodeUrl
    Dir = 'nodejs'
    ZipDir = $null
    Required = @('node.exe', 'npm.cmd', 'npx.cmd')
}

# ── NSSM ───────────────────────────────────────────────────────────────
$tools += @{
    Name = 'NSSM'
    Url = 'https://nssm.cc/release/nssm-2.24.zip'
    Dir = 'nssm'
    ZipDir = 'nssm-2.24\win64'
    Required = @('nssm.exe')
}

# ── Composer ───────────────────────────────────────────────────────────
$tools += @{
    Name = 'Composer'
    Url = 'https://getcomposer.org/download/latest-stable/composer.phar'
    Dir = 'php'
    ZipDir = $null
    Required = @('composer.phar')
    IsSingleFile = $true
}


function Write-ProgressHash {
    param([string]$Name, [string]$Status)
    $icon = switch ($Status) {
        'downloading' { '↓' }
        'extracting'  { '⏳' }
        'verified'    { '✓' }
        'skipped'     { '→' }
        'error'       { '✗' }
        default       { '?' }
    }
    Write-Host "  $icon $Name" -ForegroundColor $(if ($Status -eq 'error') { 'Red' } elseif ($Status -in @('verified', 'skipped')) { 'Green' } else { 'Cyan' })
}

function Get-Tool {
    param(
        [hashtable]$Tool,
        [string]$BaseDir
    )

    $toolDir = Join-Path $BaseDir $Tool.Dir
    $extractDir = Join-Path $BaseDir '__tmp__' + $Tool.Name

    # Check if already installed
    if (-not $Force) {
        $allOk = $true
        foreach ($f in $Tool.Required) {
            if (-not (Test-Path (Join-Path $toolDir $f))) {
                $allOk = $false
                break
            }
        }
        if ($allOk) {
            Write-ProgressHash $Tool.Name 'skipped'
            return
        }
    }

    Write-ProgressHash $Tool.Name 'downloading'

    # Download
    $zipFile = Join-Path $BaseDir "__dl__$($Tool.Name -replace '\.', '_').zip"
    try {
        Invoke-WebRequest -Uri $Tool.Url -OutFile $zipFile -UseBasicParsing -TimeoutSec 120
    } catch {
        Write-ProgressHash $Tool.Name 'error'
        Write-Host "    Download failed: $_" -ForegroundColor Red
        return
    }

    # Single file (composer.phar)
    if ($Tool.ContainsKey('IsSingleFile') -and $Tool.IsSingleFile) {
        if (-not (Test-Path $toolDir)) { New-Item -ItemType Directory -Path $toolDir -Force | Out-Null }
        Move-Item -Path $zipFile -Destination (Join-Path $toolDir 'composer.phar') -Force
        Write-ProgressHash $Tool.Name 'verified'
        return
    }

    Write-ProgressHash $Tool.Name 'extracting'

    # Clean and extract
    if (Test-Path $toolDir) { Remove-Item -Path $toolDir -Recurse -Force -ErrorAction SilentlyContinue }
    New-Item -ItemType Directory -Path $toolDir -Force | Out-Null

    if ($Tool.ZipDir) {
        # Extract zip with nested directory structure
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $archive = [System.IO.Compression.ZipFile]::OpenRead($zipFile)
        foreach ($entry in $archive.Entries) {
            if ($entry.FullName -like "$($Tool.ZipDir)/*") {
                $relativePath = $entry.FullName.Substring($Tool.ZipDir.Length + 1)
                if ($relativePath) {
                    $destPath = Join-Path $toolDir $relativePath
                    $destDir = Split-Path $destPath -Parent
                    if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
                    if (-not $entry.Name) { continue } # directory entry
                    [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $destPath, $true)
                }
            }
        }
        $archive.Dispose()
    } else {
        Microsoft.PowerShell.Archive\Expand-Archive -Path $zipFile -DestinationPath $toolDir -Force
    }

    Remove-Item -Path $zipFile -Force -ErrorAction SilentlyContinue

    # Verify
    $missing = @()
    foreach ($f in $Tool.Required) {
        if (-not (Test-Path (Join-Path $toolDir $f))) {
            $missing += $f
        }
    }
    if ($missing) {
        Write-ProgressHash $Tool.Name 'error'
        Write-Host "    Missing files: $($missing -join ', ')" -ForegroundColor Red
    } else {
        Write-ProgressHash $Tool.Name 'verified'
    }
}


# ── Main ───────────────────────────────────────────────────────────────

Write-Host "`n=== Corex Runtime Downloader ===" -ForegroundColor Cyan
Write-Host "Architecture: $Architecture" -ForegroundColor Cyan
Write-Host "Output:       $OutputDir`n" -ForegroundColor Cyan

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

foreach ($tool in $tools) {
    Get-Tool -Tool $tool -BaseDir $OutputDir
}

# Create php.ini from template
$phpIniPath = Join-Path $OutputDir 'php\php.ini'
if (-not (Test-Path $phpIniPath) -or $Force) {
    $phpIni = @"
[PHP]
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
extension_dir = "ext"
extension=mbstring
extension=curl
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=fileinfo
extension=gd
extension=intl
extension=json
extension=sodium
extension=tidy
extension=tokenizer
extension=xml
extension=xmlwriter
extension=zlib
date.timezone = UTC
memory_limit = 512M
max_execution_time = 300
post_max_size = 100M
upload_max_filesize = 100M
"@
    Set-Content -Path $phpIniPath -Value $phpIni
    Write-Host "  ✓ php.ini created" -ForegroundColor Green
}

Write-Host "`n=== Download Complete ===" -ForegroundColor Green
