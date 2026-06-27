#Requires -Version 5.1
<#
.SYNOPSIS
    Master build script for Corex Windows desktop distribution.

.DESCRIPTION
    Complete build pipeline:
    1. Download portable runtimes (PHP, Python, Nginx, Redis, Node.js)
    2. Build PHP backend (composer install, optimize)
    3. Build Python AI Gateway (venv, pip install)
    4. Build Electron shell (npm install, build)
    5. Bundle everything into dist/
    6. Create Inno Setup installer (optional)
    7. Sign binaries (optional)
    8. Create portable zip (optional)

.PARAMETER Config
    Build configuration: Debug, Release

.PARAMETER Arch
    Target architecture: x64, arm64

.PARAMETER Version
    Version string (default: from electron/package.json)

.PARAMETER SkipDownloads
    Skip re-downloading portable runtimes

.PARAMETER SkipBackend
    Skip PHP composer install

.PARAMETER SkipGateway
    Skip Python pip install

.PARAMETER SkipElectron
    Skip Electron build

.PARAMETER InnoSetup
    Build Inno Setup installer (requires ISCC.exe)

.PARAMETER Portable
    Create portable zip archive

.PARAMETER Sign
    Authenticode sign the installer and binaries

.PARAMETER OutDir
    Output directory for build artifacts

.EXAMPLE
    PS> .\build.ps1 -Config Release -Arch x64
    PS> .\build.ps1 -Config Release -InnoSetup -Sign
    PS> .\build.ps1 -Config Release -Portable -SkipDownloads
#>

param(
    [ValidateSet('Debug', 'Release')]
    [string]$Config = 'Release',
    [ValidateSet('x64', 'arm64')]
    [string]$Arch = 'x64',
    [string]$Version = '',
    [switch]$SkipDownloads,
    [switch]$SkipBackend,
    [switch]$SkipGateway,
    [switch]$SkipElectron,
    [switch]$InnoSetup,
    [switch]$Portable,
    [switch]$Sign,
    [string]$OutDir = ''
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))
$BuildRoot = Join-Path $ProjectRoot 'build'
$DistDir = if ($OutDir) { $OutDir } else { Join-Path $ProjectRoot "dist\corex-$Arch" }

# Version
if (-not $Version) {
    $pkgJson = Get-Content (Join-Path $ProjectRoot 'electron\package.json') | ConvertFrom-Json
    $Version = $pkgJson.version
}
$BuildId = "$Version-$Arch-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$LogFile = Join-Path $BuildRoot "build-$BuildId.log"

# ── Helpers ────────────────────────────────────────────────────────────

function Log {
    param([string]$Msg, [string]$Level = 'Info')
    $ts = Get-Date -Format 'HH:mm:ss'
    $color = @{Info = 'Cyan'; Success = 'Green'; Warning = 'Yellow'; Error = 'Red' }
    Write-Host "[$ts] $Msg" -ForegroundColor $color[$Level]
    if (-not (Test-Path (Split-Path $LogFile -Parent))) { New-Item -ItemType Directory -Path (Split-Path $LogFile -Parent) -Force | Out-Null }
    Add-Content -Path $LogFile -Value "[$ts][$Level] $Msg"
}

function Exec {
    param([scriptblock]$Cmd, [string]$Label = '')
    if ($Label) { Log "Running: $Label" }
    try { & $Cmd } catch { Log "Failed: $Label`n$_" -Level Error; throw }
}

function New-Dir {
    param([string]$Path)
    if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Path $Path -Force | Out-Null }
}

# ── Phase 0: Setup ────────────────────────────────────────────────────

function Initialize-Build {
    Log "=== Corex Build v$Version ($Arch, $Config) ===" -Level Success
    Log "Project root: $ProjectRoot"
    Log "Dist dir:     $DistDir"
    Log "Build ID:     $BuildId"
    New-Dir $BuildRoot
    New-Dir $DistDir
}

# ── Phase 1: Download Runtimes ────────────────────────────────────────

function Download-Runtimes {
    if ($SkipDownloads) { Log "Skipping runtime downloads" -Level Warning; return }

    Log "=== Phase 1: Downloading portable runtimes ==="
    $dlScript = Join-Path $PSScriptRoot 'tools\download-tools.ps1'
    $runtimeDir = Join-Path $BuildRoot 'runtime'
    Exec -Label 'download-tools.ps1' -Cmd {
        & powershell -ExecutionPolicy Bypass -File $dlScript -OutputDir $runtimeDir -Architecture $Arch -Force:$IsForce
    }
}

# ── Phase 2: Build Backend (PHP) ──────────────────────────────────────

function Build-Backend {
    if ($SkipBackend) { Log "Skipping backend build" -Level Warning; return }

    Log "=== Phase 2: Building PHP backend ==="
    $backendDir = Join-Path $ProjectRoot 'backend'
    $phpDir = Join-Path $BuildRoot 'runtime\php'
    $phpExe = Join-Path $phpDir 'php.exe'

    if (-not (Test-Path $phpExe)) {
        Log "PHP not found at $phpExe — using system PHP" -Level Warning
        $phpExe = (Get-Command php -ErrorAction SilentlyContinue).Source
        if (-not $phpExe) { Log "PHP not available. Install PHP or remove -SkipDownloads" -Level Error; throw }
    }

    Log "Using PHP: $phpExe"

    # Install Composer dependencies
    Push-Location $backendDir
    try {
        if (Test-Path (Join-Path $phpDir 'composer.phar')) {
            Exec -Label 'composer install' -Cmd { & $phpExe (Join-Path $phpDir 'composer.phar') install --no-dev --optimize-autoloader --no-interaction --prefer-dist }
        } else {
            Exec -Label 'composer install' -Cmd { composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist }
        }
    } finally { Pop-Location }

    # Generate app key
    Push-Location $backendDir
    try {
        Exec -Label 'artisan key:generate' -Cmd { & $phpExe artisan key:generate --force }
    } finally { Pop-Location }

    # Optimize for production
    Push-Location $backendDir
    try {
        if ($Config -eq 'Release') {
            Exec -Label 'artisan optimize' -Cmd { & $phpExe artisan optimize --no-interaction }
            Exec -Label 'artisan route:cache' -Cmd { & $phpExe artisan route:cache --no-interaction }
            Exec -Label 'artisan config:cache' -Cmd { & $phpExe artisan config:cache --no-interaction }
            Exec -Label 'artisan event:cache' -Cmd { & $phpExe artisan event:cache --no-interaction }
            Exec -Label 'artisan view:cache' -Cmd { & $phpExe artisan view:cache --no-interaction }
        }
    } finally { Pop-Location }

    # Remove dev files
    $devDirs = @('tests', 'storage/logs/.gitignore', 'storage/framework/cache/data/.gitignore')
    foreach ($d in $devDirs) {
        $p = Join-Path $backendDir $d
        if (Test-Path $p) { Remove-Item -Path $p -Recurse -Force -ErrorAction SilentlyContinue }
    }

    Log "Backend build complete" -Level Success
}

# ── Phase 3: Build AI Gateway (Python) ────────────────────────────────

function Build-Gateway {
    if ($SkipGateway) { Log "Skipping gateway build" -Level Warning; return }

    Log "=== Phase 3: Building Python AI Gateway ==="
    $gatewayDir = Join-Path $ProjectRoot 'ai-gateway'
    $pythonDir = Join-Path $BuildRoot 'runtime\python'
    $venvDir = Join-Path $gatewayDir '.venv'
    $embedPython = Join-Path $pythonDir 'python.exe'

    if (-not (Test-Path $embedPython)) {
        Log "Embedded Python not found at $embedPython — using system Python" -Level Warning
        $embedPython = (Get-Command python -ErrorAction SilentlyContinue).Source
        if (-not $embedPython) { Log "Python not available" -Level Error; throw }
    }

    # Create virtual environment
    if (Test-Path $venvDir) { Remove-Item -Path $venvDir -Recurse -Force }
    Exec -Label 'python -m venv' -Cmd { & $embedPython -m venv $venvDir }

    $pipExe = Join-Path $venvDir 'Scripts\pip.exe'
    $pythonVenv = Join-Path $venvDir 'Scripts\python.exe'

    if (-not (Test-Path $pipExe)) {
        # Embedded Python doesn't include pip; bootstrap it
        Log "Bootstrapping pip for embedded Python" -Level Info
        $getPip = Join-Path $BuildRoot 'get-pip.py'
        Invoke-WebRequest -Uri 'https://bootstrap.pypa.io/get-pip.py' -OutFile $getPip -UseBasicParsing
        Exec -Label 'bootstrap pip' -Cmd { & $embedPython $getPip --no-setuptools --no-wheel }
    }

    # Install dependencies
    Exec -Label 'pip install' -Cmd { & $pipExe install -r (Join-Path $gatewayDir 'requirements.txt') --no-compile --quiet }

    # Freeze versions for reproducibility
    Exec -Label 'pip freeze' -Cmd { & $pipExe freeze | Out-File (Join-Path $BuildRoot 'requirements-lock.txt') -Encoding utf8 }

    # Clean cached wheels
    $pipCache = Join-Path $venvDir 'pip'
    if (Test-Path $pipCache) { Remove-Item -Path $pipCache -Recurse -Force }

    # Remove dev files
    $devDirs = @('tests')
    foreach ($d in $devDirs) {
        $p = Join-Path $gatewayDir $d
        if (Test-Path $p) { Remove-Item -Path $p -Recurse -Force -ErrorAction SilentlyContinue }
    }

    Log "Gateway build complete" -Level Success
}

# ── Phase 4: Build Electron Shell ─────────────────────────────────────

function Build-Electron {
    if ($SkipElectron) { Log "Skipping Electron build" -Level Warning; return }

    Log "=== Phase 4: Building Electron shell ==="
    $electronDir = Join-Path $ProjectRoot 'electron'

    # Ensure Node.js is available
    $nodeDir = Join-Path $BuildRoot 'runtime\nodejs'
    $nodeExe = Join-Path $nodeDir 'node.exe'
    if (-not (Test-Path $nodeExe)) {
        $nodeExe = (Get-Command node -ErrorAction SilentlyContinue).Source
        if (-not $nodeExe) { Log "Node.js not available" -Level Error; throw }
        Log "Using system Node.js: $nodeExe" -Level Warning
    }

    # Temporarily add to PATH
    $env:Path = "$nodeDir;$env:Path"

    Push-Location $electronDir
    try {
        Exec -Label 'npm install' -Cmd { npm install --no-optional --ignore-scripts }
        Exec -Label 'electron-builder' -Cmd {
            if ($Config -eq 'Release') {
                npx electron-builder --win --config build-config.js --$Arch
            } else {
                npx electron-builder --win --config build-config.js --$Arch --config.extraMetadata.env=development
            }
        }
    } finally { Pop-Location }

    # Copy Electron build to dist
    $electronDist = Join-Path $electronDir 'dist'
    if (Test-Path $electronDist) {
        Copy-Item -Path "$electronDist\*" -Destination $DistDir -Recurse -Force
        Log "Electron build copied to $DistDir" -Level Success
    }

    Log "Electron build complete" -Level Success
}

# ── Phase 5: Bundle Distribution ──────────────────────────────────────

function Bundle-Distribution {
    Log "=== Phase 5: Bundling distribution ==="

    $appDir = Join-Path $DistDir 'app'

    # Copy runtime binaries
    $runtimeDir = Join-Path $BuildRoot 'runtime'
    if (Test-Path $runtimeDir) {
        Copy-Item -Path "$runtimeDir\*" -Destination $DistDir -Recurse -Force
        Log "Runtimes bundled" -Level Success
    }

    # Copy PHP backend (without dev dirs)
    $backendDest = Join-Path $appDir 'backend'
    New-Dir $backendDest
    Get-ChildItem (Join-Path $ProjectRoot 'backend') -Exclude @('node_modules', 'tests') | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination (Join-Path $backendDest $_.Name) -Recurse -Force
    }
    Log "Backend bundled" -Level Success

    # Copy AI Gateway (without dev dirs)
    $gatewayDest = Join-Path $appDir 'ai-gateway'
    New-Dir $gatewayDest
    Get-ChildItem (Join-Path $ProjectRoot 'ai-gateway') -Exclude @('__pycache__', '.venv', 'tests', '.mypy_cache') | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination (Join-Path $gatewayDest $_.Name) -Recurse -Force
    }
    Log "AI Gateway bundled" -Level Success

    # Copy launcher scripts
    Copy-Item -Path (Join-Path $PSScriptRoot 'portable\*') -Destination $DistDir -Recurse -Force
    Log "Launchers bundled" -Level Success

    # Copy Windows management scripts
    $scriptsDest = Join-Path $DistDir 'scripts'
    New-Dir $scriptsDest
    Copy-Item -Path (Join-Path $ProjectRoot 'windows\*.ps1') -Destination $scriptsDest
    Copy-Item -Path (Join-Path $ProjectRoot 'windows\*.bat') -Destination $scriptsDest
    Log "Management scripts bundled" -Level Success

    # Create VERSION file
    @{
        version = $Version
        build_id = $BuildId
        build_date = (Get-Date -Format 'o')
        arch = $Arch
        config = $Config
    } | ConvertTo-Json | Set-Content (Join-Path $DistDir 'version.json') -Encoding utf8

    # Create .env from template
    $envExample = Join-Path $ProjectRoot 'backend\.env.example'
    $envDest = Join-Path $DistDir 'app\backend\.env'
    if (Test-Path $envExample -and -not (Test-Path $envDest)) {
        Copy-Item $envExample $envDest
    }

    Log "Distribution bundled at: $DistDir" -Level Success
}

# ── Phase 6: Create Portable Zip ──────────────────────────────────────

function Build-Portable {
    if (-not $Portable) { return }

    Log "=== Phase 6: Creating portable archive ==="
    $zipFile = Join-Path (Split-Path $DistDir -Parent) "Corex-$Version-$Arch-portable.zip"
    if (Test-Path $zipFile) { Remove-Item $zipFile -Force }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($DistDir, $zipFile, [System.IO.Compression.CompressionLevel]::Optimal, $false)
    Log "Portable archive: $zipFile" -Level Success
}

# ── Phase 7: Create Inno Setup Installer ──────────────────────────────

function Build-Installer {
    if (-not $InnoSetup) { return }

    Log "=== Phase 7: Building Inno Setup installer ==="

    $isccPath = Get-Command 'ISCC.exe' -ErrorAction SilentlyContinue
    if (-not $isccPath) {
        $isccPath = "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe"
        if (-not (Test-Path $isccPath)) {
            $isccPath = "${env:ProgramFiles}\Inno Setup 6\ISCC.exe"
        }
    }

    if (-not (Test-Path $isccPath)) {
        Log "ISCC.exe not found. Install Inno Setup 6+ from https://jrsoftware.org/isdl.php" -Level Error
        return
    }

    $issFile = Join-Path $PSScriptRoot 'installer.iss'
    $issContent = Get-Content $issFile -Raw
    $issContent = $issContent -replace '#define MyAppVersion ".*"', "#define MyAppVersion `"$Version`""
    $issContent = $issContent -replace '#define MyAppArch ".*"', "#define MyAppArch `"$Arch`""
    $tempIss = Join-Path $BuildRoot "installer-$BuildId.iss"
    Set-Content -Path $tempIss -Value $issContent -Encoding utf8

    Exec -Label 'ISCC.exe' -Cmd { & $isccPath $tempIss }

    Log "Installer built" -Level Success
}

# ── Phase 8: Sign Binaries ────────────────────────────────────────────

function Sign-Binaries {
    if (-not $Sign) { return }
    Log "=== Phase 8: Signing binaries ==="

    $signScript = Join-Path $PSScriptRoot 'sign\sign.ps1'
    if (Test-Path $signScript) {
        Exec -Label 'sign.ps1' -Cmd {
            & powershell -ExecutionPolicy Bypass -File $signScript -Path $DistDir -Recurse
        }
    } else {
        Log "Signing script not found at $signScript" -Level Warning
    }
}


# ── Main ──────────────────────────────────────────────────────────────

try {
    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()

    Initialize-Build
    Download-Runtimes
    Build-Backend
    Build-Gateway
    Build-Electron
    Bundle-Distribution
    Build-Portable
    Build-Installer
    Sign-Binaries

    $stopwatch.Stop()
    Log "=== Build Complete: $($stopwatch.Elapsed.TotalMinutes.ToString('0.0')) minutes ===" -Level Success
    Log "Output: $DistDir" -Level Success
    Log "Log:    $LogFile" -Level Success
} catch {
    Log "BUILD FAILED: $_" -Level Error
    Log $_.ScriptStackTrace -Level Error
    exit 1
}
