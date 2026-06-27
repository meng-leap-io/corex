#Requires -Version 5.1
#Requires -RunAsAdministrator

param(
    [string]$InstallDir = "$env:ProgramFiles\Corex",
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\test-permissions.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red }

Log "=== File Permission Validation ==="

$allOk = $true

# Directories that need write access
$writeDirs = @(
    @{ Path = $DataDir; Desc = 'User data root' },
    @{ Path = "$DataDir\logs"; Desc = 'Log files' },
    @{ Path = "$DataDir\database"; Desc = 'Database files' },
    @{ Path = "$DataDir\cache"; Desc = 'Cache files' },
    @{ Path = "$DataDir\sessions"; Desc = 'PHP sessions' },
    @{ Path = "$DataDir\app"; Desc = 'Application data' },
    @{ Path = "$DataDir\tmp"; Desc = 'Temp files' },
    @{ Path = "$InstallDir\conf"; Desc = 'Configuration' },
    @{ Path = "$InstallDir\www"; Desc = 'Web root' },
    @{ Path = "$env:TMP"; Desc = 'System temp' }
)

foreach ($dir in $writeDirs) {
    $path = New-Item -ItemType Directory -Path $dir.Path -Force -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName
    if (-not $path) {
        Warn "Cannot create directory: $($dir.Path)"
        continue
    }

    # Test write
    $testFile = "$path\.corex-perm-test"
    try {
        Set-Content -Path $testFile -Value "test" -Force -ErrorAction Stop
        Remove-Item -Path $testFile -Force -ErrorAction Stop
        Ok "Write access: $($dir.Desc) ($path)"
    } catch {
        Err "NO write access: $($dir.Desc) ($path)"
        $allOk = $false
    }
}

# Check specific file permissions
Log ""
Log "Security-sensitive file permissions:"

$sensitiveFiles = @(
    @{ Path = "$InstallDir\conf\php.ini"; Desc = 'PHP config' },
    @{ Path = "$InstallDir\conf\nginx.conf"; Desc = 'Nginx config' },
    @{ Path = "$InstallDir\conf\redis.conf"; Desc = 'Redis config' },
    @{ Path = "$DataDir\.env"; Desc = 'Environment file' },
    @{ Path = "$DataDir\corex.sqlite"; Desc = 'SQLite database' }
)

foreach ($file in $sensitiveFiles) {
    if (Test-Path $file.Path) {
        try {
            $acl = Get-Acl -Path $file.Path -ErrorAction Stop
            $accessRules = $acl.GetAccessRules($true, $true, [System.Security.Principal.NTAccount])
            $overlyPermissive = $false

            foreach ($rule in $accessRules) {
                $identity = $rule.IdentityReference.Value
                $rights = $rule.FileSystemRights
                $inherited = $rule.IsInherited

                if ($identity -eq 'Everyone' -and $rights -match 'Write|Modify|FullControl') {
                    Warn "  $($file.Desc): Everyone has $rights — overly permissive!"
                    $overlyPermissive = $true
                    $allOk = $false
                }
            }

            if (-not $overlyPermissive) {
                $owner = (Get-Item $file.Path).GetAccessControl().Owner
                Ok "  $($file.Desc): owner=$owner, secure"
            }
        } catch {
            Warn "  Cannot check permissions for $($file.Desc): $_"
        }
    } else {
        Log "  $($file.Desc): not found (will be created by setup)"
    }
}

# Check service account permissions
Log ""
Log "Service account access:"
try {
    $localSystem = [System.Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $systemAccount = $localSystem.Translate([System.Security.Principal.NTAccount])

    foreach ($dir in $writeDirs) {
        if (Test-Path $dir.Path) {
            try {
                $acl = Get-Acl $dir.Path -ErrorAction Stop
                $systemAccess = $acl.Access | Where-Object {
                    $_.IdentityReference -eq $systemAccount.Value -or
                    $_.IdentityReference -eq 'NT AUTHORITY\SYSTEM'
                }
                if (-not $systemAccess) {
                    Warn "  $($dir.Desc): SYSTEM account has no explicit access"
                }
            } catch { }
        }
    }
} catch {
    Warn "  Could not check SYSTEM account access"
}

# Check for Windows Defender status on these paths
Log ""
Log "Antivirus exclusion status:"
try {
    $mp = Get-MpPreference -ErrorAction SilentlyContinue
    if ($mp) {
        $pathsToCheck = @($InstallDir, $DataDir, "$env:TMP")
        foreach ($p in $pathsToCheck) {
            $isExcluded = $mp.ExclusionPath -contains $p
            if ($isExcluded) {
                Ok "Defender exclusion: $p"
            } else {
                Warn "No Defender exclusion: $p (may impact performance)"
            }
        }
    }
} catch {
    Log "  Cannot check Defender status (not available or third-party AV)"
}

# Summary
Log ""
if ($allOk) {
    Ok "All permissions validated"
} else {
    Err "Some permissions need attention. Check warnings above."
}

Log "Log: $LogFile"
exit ($allOk ? 0 : 1)
