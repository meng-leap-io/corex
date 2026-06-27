#Requires -Version 5.1
#Requires -RunAsAdministrator

param([string]$LogDir)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\test-services.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green; "✓ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow; "⚠ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red; "✗ $M" | Out-File $LogFile -Encoding utf8 -Append }

Log "=== Service Status Check ==="

$services = @(
    @{ Name = 'CorexRedis'; Display = 'Redis Cache' },
    @{ Name = 'CorexPHP'; Display = 'PHP-FPM' },
    @{ Name = 'CorexNginx'; Display = 'Nginx Web Server' },
    @{ Name = 'CorexAIGateway'; Display = 'AI Gateway' },
    @{ Name = 'CorexServiceHost'; Display = 'Service Host' }
)

$allOk = $true
foreach ($svc in $services) {
    try {
        $s = Get-Service -Name $svc.Name -ErrorAction SilentlyContinue
        if (-not $s) {
            Warn "Service not installed: $($svc.Name) ($($svc.Display))"
            $allOk = $false
            continue
        }

        $status = $s.Status.ToString()
        $startType = $s.StartType.ToString()
        $canPause = $s.CanPauseAndContinue

        # Query detailed config
        $config = sc.exe qc $svc.Name 2>&1
        $binPath = if ($config -match 'BINARY_PATH_NAME\s+:\s+(\S+)') { $matches[1] }

        if ($status -eq 'Running') {
            Ok "$($svc.Display) [$($svc.Name)]: Running (StartType: $startType)"
            if ($binPath) { Log "  Binary: $binPath" }
        } elseif ($status -eq 'Stopped' -and $startType -eq 'Auto') {
            Warn "$($svc.Display) [$($svc.Name)]: Stopped (should auto-start)"
            $allOk = $false
        } else {
            Warn "$($svc.Display) [$($svc.Name)]: $status (StartType: $startType)"
            $allOk = $false
        }

        # Check last 5 events from SCM for this service
        try {
            $events = Get-WinEvent -LogName System -MaxEvents 50 -ErrorAction SilentlyContinue |
                Where-Object { $_.Message -match $svc.Name -and $_.TimeCreated -gt (Get-Date).AddHours(-1) }
            if ($events) {
                foreach ($evt in $events) {
                    if ($evt.LevelDisplayName -match 'Error|Warning') {
                        Log "  Event: [$($evt.LevelDisplayName)] $($evt.Message -replace '\s+', ' ' -replace '^.{150}.*$', '$&...')"
                    }
                }
            }
        } catch { }
    } catch {
        Err "Service check failed for $($svc.Name): $_"
        $allOk = $false
    }
}

Log ""
if ($allOk) {
    Ok "All Corex services are running"
} else {
    Warn "Some services have issues. Check event logs or run setup-windows-services.ps1"
}

# Check process-level status (does the binary exist?)
Log ""
Log "Process-level verification:"
$processChecks = @(
    @{ Name = 'redis-server'; Service = 'CorexRedis' },
    @{ Name = 'php-cgi'; Service = 'CorexPHP' },
    @{ Name = 'nginx'; Service = 'CorexNginx' },
    @{ Name = 'python'; Service = 'CorexAIGateway' }
)

foreach ($pc in $processChecks) {
    $procs = Get-Process -Name $pc.Name -ErrorAction SilentlyContinue
    $svc = Get-Service -Name $pc.Service -ErrorAction SilentlyContinue
    if ($procs -and $svc.Status -eq 'Running') {
        $mem = [math]::Round(($procs | Measure-Object WorkingSet64 -Sum).Sum / 1MB, 1)
        $cpu = [math]::Round(($procs | Measure-Object CPU -Sum).Sum, 1)
        Ok "$($pc.Name): $($procs.Count) instances, ${mem}MB RAM, ${cpu}s CPU"
    } elseif ($svc.Status -eq 'Running' -and -not $procs) {
        Err "$($pc.Name): Service running but no process found!"
    }
}

Log "Log: $LogFile"
exit ($allOk ? 0 : 1)
