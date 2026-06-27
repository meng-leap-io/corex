#Requires -Version 5.1
#Requires -RunAsAdministrator

param([string]$LogDir)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\test-ports.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red }

Log "=== Port Availability Check ==="

$ports = @(
    @{ Port = 80; Name = 'HTTP (Nginx)' },
    @{ Port = 443; Name = 'HTTPS (Nginx SSL)' },
    @{ Port = 5432; Name = 'PostgreSQL' },
    @{ Port = 6379; Name = 'Redis' },
    @{ Port = 8000; Name = 'Laravel Dev' },
    @{ Port = 8001; Name = 'AI Gateway' },
    @{ Port = 8100; Name = 'NativePHP' },
    @{ Port = 11434; Name = 'Ollama AI' },
    @{ Port = 9000; Name = 'PHP-FPM' }
)

$conflicts = 0
foreach ($p in $ports) {
    $conn = netstat -ano | Select-String ":$($p.Port)\s" | Select-String "LISTEN" | Select-Object -First 1
    if ($conn) {
        $parts = $conn -split '\s+'
        $pid = $parts[-1]
        try {
            $proc = Get-Process -Id $pid -ErrorAction SilentlyContinue
            $name = $proc?.ProcessName ?? "PID $pid"
            $ownsIt = $name -match 'php|nginx|redis|python|httpd|mysqld'
            if ($ownsIt) {
                Ok "$($p.Name) ($($p.Port)): in use by $name ✓"
            } else {
                Warn "$($p.Name) ($($p.Port)): in use by $name (not a Corex process)"
                $conflicts++
            }
        } catch {
            Warn "$($p.Name) ($($p.Port)): LISTEN, PID $pid (unknown)"
            $conflicts++
        }
    } else {
        Ok "$($p.Name) ($($p.Port)): available"
    }
}

Log ""
if ($conflicts -eq 0) {
    Ok "All ports available"
} else {
    Warn "$conflicts port conflicts detected. Review warnings above."
    Log "Run: netstat -ano | findstr :PORT to identify the process"
    Log "Then: taskkill /PID <PID> /F to free the port"
}

# Check if Corex services are listening on their expected ports
Log ""
Log "Service endpoint verification:"
$endpoints = @(
    @{ Url = 'http://127.0.0.1:80/'; Desc = 'Nginx HTTP' },
    @{ Url = 'https://127.0.0.1:443/'; Desc = 'Nginx HTTPS' },
    @{ Url = 'http://127.0.0.1:8001/health'; Desc = 'AI Gateway' },
    @{ Url = 'http://127.0.0.1:8000/health'; Desc = 'Laravel' },
    @{ Url = 'http://127.0.0.1:11434/api/tags'; Desc = 'Ollama' }
)

foreach ($ep in $endpoints) {
    try {
        $req = [System.Net.Http.HttpClient]::new()
        $req.Timeout = [TimeSpan]::FromSeconds(5)
        $resp = $req.GetAsync($ep.Url).GetAwaiter().GetResult()
        $status = [int]$resp.StatusCode
        if ($status -lt 500) {
            Ok "$($ep.Desc): $($ep.Url) → $status"
        } else {
            Warn "$($ep.Desc): $($ep.Url) → $status"
        }
    } catch {
        Warn "$($ep.Desc): $($ep.Url) → unreachable ($($_.Exception.Message))"
    }
}

Log "Log: $LogFile"
exit ($conflicts -gt 0 ? 1 : 0)
