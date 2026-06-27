<#
.SYNOPSIS
    Manage Redis configuration: view status, flush cache, set memory limit, etc.

.EXAMPLE
    PS> .\manage-redis.ps1 -Action Info
    PS> .\manage-redis.ps1 -Action MemoryStats
    PS> .\manage-redis.ps1 -Action SetMaxMemory -Value 512mb
#>

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Info', 'MemoryStats', 'SetMaxMemory', 'FlushCache', 'Ping', 'SlowLog')]
    [string]$Action,

    [Parameter(Mandatory = $false)]
    [string]$Value = '',

    [Parameter(Mandatory = $false)]
    [int]$Port = 6379
)

$redisCli = "$env:ProgramFiles\Redis\redis-cli.exe"
if (-not (Test-Path $redisCli)) {
    $redisCli = "redis-cli"
}

try {
    switch ($Action) {
        'Info' {
            & $redisCli -p $Port INFO
        }
        'MemoryStats' {
            & $redisCli -p $Port INFO memory
        }
        'SetMaxMemory' {
            & $redisCli -p $Port CONFIG SET maxmemory $Value
            & $redisCli -p $Port CONFIG REWRITE
            Write-Host "maxmemory set to $Value" -ForegroundColor Green
        }
        'FlushCache' {
            $confirm = Read-Host "This will flush ALL Redis data. Continue? (y/N)"
            if ($confirm -eq 'y') {
                & $redisCli -p $Port FLUSHALL
                Write-Host "Cache flushed" -ForegroundColor Yellow
            }
        }
        'Ping' {
            $result = & $redisCli -p $Port PING
            Write-Host "Redis: $result" -ForegroundColor $(if ($result -eq 'PONG') { 'Green' } else { 'Red' })
        }
        'SlowLog' {
            & $redisCli -p $Port SLOWLOG GET 20
        }
    }
} catch {
    Write-Host "Redis command failed: $_" -ForegroundColor Red
}
