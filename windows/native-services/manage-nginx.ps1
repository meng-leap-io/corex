#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Manage Nginx configuration: reload, test, stop, start.

.EXAMPLE
    PS> .\manage-nginx.ps1 -Action Reload
    PS> .\manage-nginx.ps1 -Action Test
#>

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Reload', 'Test', 'Start', 'Stop', 'Quit')]
    [string]$Action,

    [Parameter(Mandatory = $false)]
    [string]$NginxRoot = 'C:\Program Files\Corex\nginx'
)

$nginxExe = "$NginxRoot\nginx.exe"
$signal = switch ($Action) {
    'Reload' { '-s reload' }
    'Test'   { '-t' }
    'Start'  { '' }
    'Stop'   { '-s stop' }
    'Quit'   { '-s quit' }
}

try {
    & $nginxExe -p "$NginxRoot" $signal
    if ($LASTEXITCODE -eq 0) {
        Write-Host "nginx $Action successful" -ForegroundColor Green
    } else {
        Write-Host "nginx $Action failed (exit $LASTEXITCODE)" -ForegroundColor Red
    }
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}
