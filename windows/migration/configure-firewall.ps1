#Requires -Version 5.1
#Requires -RunAsAdministrator

param(
    [string]$LogDir,
    [switch]$RemoveRules
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\configure-firewall.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }

Log "=== Windows Firewall Configuration ==="

$rules = @(
    @{ Name = 'Corex-HTTP'; Port = 80; Proto = 'tcp'; Desc = 'Corex Web Server (HTTP)' },
    @{ Name = 'Corex-HTTPS'; Port = 443; Proto = 'tcp'; Desc = 'Corex Web Server (HTTPS)' },
    @{ Name = 'Corex-AI-Gateway'; Port = 8001; Proto = 'tcp'; Desc = 'Corex AI Gateway API' },
    @{ Name = 'Corex-Redis'; Port = 6379; Proto = 'tcp'; Desc = 'Corex Redis Cache' },
    @{ Name = 'Corex-Ollama'; Port = 11434; Proto = 'tcp'; Desc = 'Corex Local Ollama AI' }
)

if ($RemoveRules) {
    Log "Removing all Corex firewall rules..."
    foreach ($rule in $rules) {
        netsh advfirewall firewall delete rule name="$($rule.Name)" 2>$null
    }
    netsh advfirewall firewall delete rule name="Corex-Laravel-Dev" 2>$null
    Ok "Firewall rules removed"
    return
}

# Delete existing rules first to avoid duplicates
foreach ($rule in $rules) {
    netsh advfirewall firewall delete rule name="$($rule.Name)" 2>$null
}

Log "Creating firewall rules..."

foreach ($rule in $rules) {
    $args = @(
        'advfirewall', 'firewall', 'add', 'rule',
        "name=$($rule.Name)",
        "dir=in",
        "action=allow",
        "protocol=$($rule.Proto)",
        "localport=$($rule.Port)",
        "profile=private,domain",
        "description=$($rule.Desc)",
        "remoteip=any"
    )

    $result = netsh @args 2>&1
    if ($LASTEXITCODE -eq 0) {
        Ok "Rule added: $($rule.Name) ($($rule.Port)/$($rule.Proto))"
    } else {
        Warn "Failed to add rule $($rule.Name): $result"
    }
}

# Only allow from local subnet for sensitive ports
$localRules = @(
    @{ Name = 'Corex-Laravel-Dev'; Port = 8000; Proto = 'tcp'; Desc = 'Corex Laravel Dev Server (local only)' }
)
foreach ($rule in $localRules) {
    netsh advfirewall firewall delete rule name="$($rule.Name)" 2>$null
    netsh advfirewall firewall add rule name="$($rule.Name)" dir=in action=allow protocol=$($rule.Proto) localport=$($rule.Port) remoteip=127.0.0.1,::1 profile=private,domain description="$($rule.Desc)" 2>$null
    Ok "Local-only rule: $($rule.Name)"
}

# Verify rules
$activeRules = netsh advfirewall firewall show rule name="Corex-*" verbose 2>&1 | Select-String 'Rule Name:' | ForEach-Object { $_ -replace 'Rule Name:\s+', '' }
Log "Active Corex firewall rules:"
foreach ($ar in $activeRules) { Log "  $ar" }

Ok "Firewall configuration complete"
Log "Log: $LogFile"
