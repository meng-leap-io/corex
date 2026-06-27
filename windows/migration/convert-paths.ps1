#Requires -Version 5.1

param(
    [string]$ProjectRoot,
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\convert-paths.log"
$Changes = @()

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green; "✓ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Chg { param([string]$M) Write-Host "→ $M" -ForegroundColor Cyan; $Changes += $M }

Log "=== Path Conversion ==="
Log "Converting Unix paths to Windows format..."

$Replacements = @(
    # Common Unix → Windows path mappings
    @{ Pattern = '/var/www/html'; Replacement = "$env:ProgramFiles\Corex" },
    @{ Pattern = '/var/www';      Replacement = "$env:ProgramFiles\Corex" },
    @{ Pattern = '/var/log/corex'; Replacement = "$DataDir\logs" },
    @{ Pattern = '/var/log';       Replacement = "$DataDir\logs" },
    @{ Pattern = '/var/run';       Replacement = "$DataDir\run" },
    @{ Pattern = '/tmp/corex';     Replacement = "$env:TMP\Corex" },
    @{ Pattern = '/tmp';           Replacement = $env:TMP },
    @{ Pattern = '/run/corex';     Replacement = "$DataDir\run" },
    @{ Pattern = '/etc/nginx';     Replacement = "$DataDir\nginx" },
    @{ Pattern = '/etc/redis';     Replacement = "$DataDir\redis" },
    @{ Pattern = '/etc/php';       Replacement = "$DataDir\php" },
    @{ Pattern = '/home/corex';    Replacement = $DataDir },
    @{ Pattern = '~/corex';        Replacement = "$env:USERPROFILE\Corex" }
)

# Files to scan
$filePatterns = @(
    '*.env', '*.yml', '*.yaml', '*.xml', '*.conf', '*.ini',
    '*.json', '*.php', '*.bat', '*.ps1', '*.cfg', '*.toml',
    '*.md', '*.txt', '*.service', 'Dockerfile*', 'docker-compose*'
)

$searchDirs = @(
    "$ProjectRoot",
    "$ProjectRoot\backend",
    "$ProjectRoot\ai-gateway",
    "$ProjectRoot\electron"
)

$processed = 0
$modified = 0

foreach ($dir in $searchDirs) {
    if (-not (Test-Path $dir)) { continue }

    $files = Get-ChildItem $dir -Recurse -Include $filePatterns -ErrorAction SilentlyContinue |
        Where-Object { $_.Length -lt 1MB -and $_.DirectoryName -notmatch '\\vendor\\|\\node_modules\\|\\\.git\\|\\\.venv\\' }

    foreach ($file in $files) {
        try {
            $content = Get-Content $file.FullName -Raw -ErrorAction SilentlyContinue
            if (-not $content) { continue }

            $original = $content
            $processed++

            foreach ($rep in $Replacements) {
                if ($content -match [regex]::Escape($rep.Pattern)) {
                    $content = $content -replace [regex]::Escape($rep.Pattern), $rep.Replacement
                }
            }

            # Convert any remaining absolute Unix paths
            $content = $content -replace '(?<=["''= ])/[a-z]+/[a-z0-9_/-]+', { param($m)
                $path = $m.Value -replace '/', '\'
                # Only replace if it looks like a filesystem path (starts with common prefixes)
                if ($path -match '\\bin\\|\\etc\\|\\var\\|\\usr\\|\\opt\\|\\home\\|\\root\\|\\srv\\|\\tmp\\') {
                    # Convert to Windows equivalent
                    $path = $path -replace '^\\bin\\', "$env:SystemRoot\System32\"
                    $path = $path -replace '^\\etc\\', "$DataDir\etc\"
                    $path = $path -replace '^\\var\\', "$DataDir\var\"
                    $path = $path -replace '^\\tmp\\', "$env:TMP\"
                    $path
                } else {
                    $path  # Return as-is if not recognizable
                }
            }

            if ($content -ne $original) {
                $content | Set-Content $file.FullName -Encoding utf8 -NoNewline
                $relative = $file.FullName.Substring($ProjectRoot.Length + 1)
                Chg "$relative"
                $modified++
            }
        } catch {
            Log "  Error processing $($file.Name): $_"
        }
    }
}

Log ""
Ok "Scan complete: $processed files processed, $modified modified"
Log "Files modified: $($Changes.Count)"
foreach ($c in $Changes) { Log "  $c" }
Log "Log: $LogFile"
