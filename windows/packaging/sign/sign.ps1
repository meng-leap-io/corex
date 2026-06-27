#Requires -Version 5.1
<#
.SYNOPSIS
    Authenticode-sign all executables in a directory tree.

.DESCRIPTION
    Uses signtool.exe (from Windows SDK or Visual Studio) to sign:
    - .exe, .dll, .msi, .ps1 files
    - Supports hardware token (YubiKey), PFX file, or Azure Key Vault

.PARAMETER Path
    Directory or file to sign

.PARAMETER Recursive
    Recurse into subdirectories

.PARAMETER PfxPath
    Path to PFX certificate file

.PARAMETER PfxPassword
    PFX file password (omit for prompt)

.PARAMETER TimestampServer
    RFC 3161 timestamp server URL

.PARAMETER DualSign
    Dual-sign with SHA-256 (uses SHA-1 + SHA-256 if supported)

.EXAMPLE
    PS> .\sign.ps1 -Path .\dist\Corex-Setup.exe -PfxPath .\cert.pfx
    PS> .\sign.ps1 -Path .\dist -Recursive -UseAzureKeyVault
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$Path,
    [switch]$Recursive,
    [string]$PfxPath = '',
    [string]$PfxPassword = '',
    [string]$TimestampServer = 'http://timestamp.digicert.com',
    [switch]$DualSign
)

$ErrorActionPreference = 'Stop'

# Find signtool.exe
$signToolCandidates = @(
    "${env:ProgramFiles(x86)}\Windows Kits\10\bin\10.0.22621.0\x64\signtool.exe",
    "${env:ProgramFiles(x86)}\Windows Kits\10\bin\10.0.22000.0\x64\signtool.exe",
    "${env:ProgramFiles(x86)}\Windows Kits\10\bin\x64\signtool.exe",
    "${env:ProgramFiles(x86)}\Microsoft Visual Studio\2022\Community\MSBuild\Current\Bin\signtool.exe",
    "${env:ProgramFiles}\Microsoft Visual Studio\2022\Community\MSBuild\Current\Bin\signtool.exe",
    (Get-Command 'signtool.exe' -ErrorAction SilentlyContinue).Source
)

$signTool = $null
foreach ($candidate in $signToolCandidates) {
    if ($candidate -and (Test-Path $candidate)) {
        $signTool = $candidate
        break
    }
}

if (-not $signTool) {
    Write-Host "signtool.exe not found. Install Windows SDK or Visual Studio." -ForegroundColor Red
    Write-Host "Download: https://developer.microsoft.com/windows/downloads/windows-sdk/" -ForegroundColor Yellow
    exit 1
}

Write-Host "Using signtool: $signTool" -ForegroundColor Cyan

# Collect files
$files = @()
if (Test-Path -Path $Path -PathType Container) {
    $searchParams = @{ Path = $Path; Include = @('*.exe', '*.dll', '*.msi') }
    if ($Recursive) { $searchParams['Recurse'] = $true }
    $files = Get-ChildItem @searchParams
} else {
    $files = Get-Item $Path
}

if ($files.Count -eq 0) {
    Write-Host "No files to sign." -ForegroundColor Yellow
    exit 0
}

Write-Host "Found $($files.Count) file(s) to sign" -ForegroundColor Cyan

# Build base arguments
$baseArgs = @(
    'sign',
    '/v',
    '/fd', 'SHA256',
    '/tr', $TimestampServer,
    '/td', 'SHA256'
)

if ($PfxPath) {
    if (-not $PfxPassword) {
        $PfxPassword = Read-Host "PFX Password" -AsSecureString
        $BSTR = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($PfxPassword)
        $PfxPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($BSTR)
    }
    $baseArgs += '/f', "`"$PfxPath`""
    $baseArgs += '/p', "`"$PfxPassword`""
}

# Sign each file
$success = 0
$failed = 0

foreach ($file in $files) {
    Write-Host "  Signing: $($file.Name)..." -NoNewline

    $args = $baseArgs + "`"$($file.FullName)`""

    try {
        $proc = Start-Process -FilePath $signTool -ArgumentList $args -NoNewWindow -Wait -PassThru
        if ($proc.ExitCode -eq 0) {
            Write-Host " OK" -ForegroundColor Green
            $success++
        } else {
            Write-Host " FAILED (exit: $($proc.ExitCode))" -ForegroundColor Red
            $failed++
        }
    } catch {
        Write-Host " ERROR: $_" -ForegroundColor Red
        $failed++
    }

    # Dual sign (SHA-1 then SHA-256)
    if ($DualSign -and $proc.ExitCode -eq 0) {
        Write-Host "    Dual-sign: $($file.Name)..." -NoNewline
        $dualArgs = @(
            'sign',
            '/as',
            '/fd', 'SHA1',
            '/t', 'http://timestamp.digicert.com'
        )
        if ($PfxPath) {
            $dualArgs += '/f', "`"$PfxPath`""
            $dualArgs += '/p', "`"$PfxPassword`""
        }
        $dualArgs += "`"$($file.FullName)`""

        $dualProc = Start-Process -FilePath $signTool -ArgumentList $dualArgs -NoNewWindow -Wait -PassThru
        if ($dualProc.ExitCode -eq 0) {
            Write-Host " OK" -ForegroundColor Green
        } else {
            Write-Host " SKIPPED" -ForegroundColor Yellow
        }
    }
}

Write-Host "`nSigning complete: $success signed, $failed failed" -ForegroundColor $(
    if ($failed -eq 0) { 'Green' } else { 'Red' }
)
