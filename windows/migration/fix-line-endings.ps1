#Requires -Version 5.1

param(
    [string]$ProjectRoot,
    [string]$LogDir = "$LogDir"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\fix-line-endings.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Chg { param([string]$M) Write-Host "→ $M" -ForegroundColor Cyan }

Log "=== Line Ending Conversion ==="
Log "Converting LF → CRLF for Windows compatibility..."

# Files that should use CRLF (Windows-native)
$crlfExtensions = @(
    '*.bat', '*.ps1', '*.cmd', '*.reg', '*.ini', '*.cfg',
    '*.conf', '*.env', '*.env.example', '*.yml', '*.yaml',
    '*.xml', '*.json', '*.md', '*.txt', '*.csv'
)

# Files that should stay LF (cross-platform)
$lfExtensions = @(
    '*.php', '*.py', '*.js', '*.ts', '*.vue', '*.css', '*.scss',
    '*.sh', '*.bash', '*.zsh', '*.Makefile', 'Dockerfile*',
    '*.gitignore', '*.editorconfig', '*.nginx', '*.htaccess'
)

$excludeDirs = @('vendor', 'node_modules', '.git', '.venv', 'venv', '__pycache__', '.idea', '.vscode')

$changed = 0

# Convert to CRLF
foreach ($pattern in $crlfExtensions) {
    $files = Get-ChildItem $ProjectRoot -Recurse -Filter $pattern -ErrorAction SilentlyContinue |
        Where-Object {
            $exclude = $false
            foreach ($ed in $excludeDirs) {
                if ($_.DirectoryName -match [regex]::Escape($ed)) { $exclude = $true; break }
            }
            -not $exclude -and $_.Length -lt 5MB
        }

    foreach ($file in $files) {
        try {
            $content = [System.IO.File]::ReadAllBytes($file.FullName)
            $hasLF = $content -match 0x0A
            $hasCRLF = $content -match 0x0D0A

            if ($hasLF -and -not $hasCRLF) {
                $text = [System.Text.Encoding]::UTF8.GetString($content)
                # Replace LF that aren't preceded by CR
                $text = $text -replace '(?<!\r)\n', "`r`n"
                [System.IO.File]::WriteAllText($file.FullName, $text, [System.Text.Encoding]::UTF8)
                $relative = $file.FullName.Substring($ProjectRoot.Length + 1)
                Chg "CRLF: $relative"
                $changed++
            }
        } catch {
            Log "  Error converting $($file.Name): $_"
        }
    }
}

# Verify LF files stay LF
$lfCheck = 0
foreach ($pattern in $lfExtensions) {
    $files = Get-ChildItem $ProjectRoot -Recurse -Filter $pattern -ErrorAction SilentlyContinue |
        Where-Object {
            $exclude = $false
            foreach ($ed in $excludeDirs) {
                if ($_.DirectoryName -match [regex]::Escape($ed)) { $exclude = $true; break }
            }
            -not $exclude -and $_.Length -lt 5MB
        }

    foreach ($file in $files) {
        try {
            $content = [System.IO.File]::ReadAllBytes($file.FullName)
            $hasCRLF = ($content -join ' ') -match '0D 0A'

            if ($hasCRLF) {
                $text = [System.Text.Encoding]::UTF8.GetString($content)
                $text = $text -replace "`r`n", "`n"
                [System.IO.File]::WriteAllText($file.FullName, $text, [System.Text.Encoding]::UTF8)
                $lfCheck++
            }
        } catch { }
    }
}

# Add .gitattributes if missing
$gitattrs = "$ProjectRoot\.gitattributes"
if (-not (Test-Path $gitattrs)) {
    @"
# Auto-detect text files
* text=auto

# PHP stays LF
*.php text eol=lf
*.py text eol=lf
*.js text eol=lf
*.ts text eol=lf
*.vue text eol=lf
*.css text eol=lf

# Windows-specific: CRLF
*.bat text eol=crlf
*.ps1 text eol=crlf
*.cmd text eol=crlf
*.reg text eol=crlf
*.env text eol=crlf
*.yml text eol=crlf
*.yaml text eol=crlf

# Binary files
*.png binary
*.jpg binary
*.gif binary
*.ico binary
*.woff2 binary
*.eot binary
*.ttf binary
"@ | Out-File $gitattrs -Encoding ascii -NoNewline
    Ok ".gitattributes created"
}

Ok "Line ending conversion complete: $changed files converted to CRLF, $lfCheck reverted to LF"
Log "Log: $LogFile"
