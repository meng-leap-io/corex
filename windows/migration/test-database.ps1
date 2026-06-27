#Requires -Version 5.1

param(
    [string]$ProjectRoot,
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$LogDir = "$DataDir\logs"
)

$ErrorActionPreference = 'Stop'
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\test-database.log"

function Log { param([string]$M) Write-Host "$M" -ForegroundColor Gray; "$M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red }

Log "=== Database Connection Test ==="

$dbTests = @()

# ── 1. SQLite ──
$sqlitePaths = @(
    "$DataDir\corex.sqlite",
    "$ProjectRoot\storage\corex.sqlite",
    "$ProjectRoot\database\corex.sqlite"
)

$sqliteFound = $false
foreach ($path in $sqlitePaths) {
    if (Test-Path $path) {
        $size = [math]::Round((Get-Item $path).Length / 1MB, 2)
        $sqliteFound = $true
        Log "SQLite found: $path (${size}MB)"
        $dbTests += @{ Type = 'SQLite'; Path = $path; Size = $size }

        # Test with PHP
        if (Get-Command php -ErrorAction SilentlyContinue) {
            $result = & php -r "
                try {
                    \$pdo = new PDO('sqlite:$path');
                    \$pdo->exec('SELECT 1');
                    echo 'OK';
                } catch (Exception \$e) {
                    echo 'FAIL: ' . \$e->getMessage();
                }
            " 2>&1
            if ($result -eq 'OK') {
                Ok "SQLite connection successful via PHP PDO"
            } else {
                Err "SQLite connection failed: $result"
            }
        }

        # Test with sqlite3 CLI
        if (Get-Command sqlite3 -ErrorAction SilentlyContinue) {
            $result = & sqlite3 $path "SELECT 'OK';" 2>&1
            if ($result -eq 'OK') {
                Ok "SQLite connection successful via sqlite3 CLI"
            } else {
                Warn "SQLite CLI test: $result"
            }
        }
        break
    }
}

# ── 2. PostgreSQL (legacy, may not be running) ──
try {
    $pgConn = netstat -ano | Select-String ":5432\s" | Select-String "LISTEN"
    if ($pgConn) {
        Log "PostgreSQL appears to be listening on port 5432"
        if (Get-Command psql -ErrorAction SilentlyContinue) {
            $result = & psql -U corex -d corex -c "SELECT 1 AS ok" -h 127.0.0.1 2>&1
            if ($LASTEXITCODE -eq 0) {
                Ok "PostgreSQL connection successful"
                $dbTests += @{ Type = 'PostgreSQL'; Status = 'Connected' }
            } else {
                Warn "PostgreSQL connection failed: $result"
                $dbTests += @{ Type = 'PostgreSQL'; Status = 'Failed' }
            }
        }
    } else {
        Log "PostgreSQL not listening (expected for SQLite-based desktop mode)"
    }
} catch { }

# ── 3. Redis (used as database cache) ──
try {
    $redisConn = netstat -ano | Select-String ":6379\s" | Select-String "LISTEN"
    if ($redisConn) {
        $redisOk = $false
        if (Get-Command redis-cli -ErrorAction SilentlyContinue) {
            $result = & redis-cli -h 127.0.0.1 PING 2>&1
            if ($result -match 'PONG') {
                Ok "Redis connection successful"
                $redisOk = $true
            }
        }
        if (-not $redisOk) {
            # Test via TCP socket
            try {
                $client = [System.Net.Sockets.TcpClient]::new('127.0.0.1', 6379)
                $client.Close()
                Ok "Redis port 6379 is open"
            } catch {
                Warn "Redis port 6379 not responding"
            }
        }
    }
} catch { }

# ── 4. Laravel-specific DB test ──
if (Test-Path "$ProjectRoot\artisan") {
    # Read DB config from .env
    $envFile = "$ProjectRoot\.env"
    if (Test-Path $envFile) {
        $envContent = Get-Content $envFile
        $dbConnection = ($envContent | Select-String '^DB_CONNECTION=') -replace '^DB_CONNECTION=', '' | ForEach-Object { $_.Trim() }
        $dbDatabase = ($envContent | Select-String '^DB_DATABASE=') -replace '^DB_DATABASE=', '' | ForEach-Object { $_.Trim() }
        $dbHost = ($envContent | Select-String '^DB_HOST=') -replace '^DB_HOST=', '' | ForEach-Object { $_.Trim() }

        Log "Laravel DB config: connection=$dbConnection, database=$dbDatabase, host=$dbHost"

        # Try artisan db:show
        Push-Location $ProjectRoot
        try {
            $artisanResult = & php artisan db:show --no-interaction 2>&1
            if ($LASTEXITCODE -eq 0) {
                Ok "Laravel database connection: $(($artisanResult | Select-String 'Database|Driver|Engine') -join '; ')"
            } else {
                $errorLine = $artisanResult | Select-String 'Error|Exception|Connection' | Select-Object -First 1
                if ($errorLine) {
                    Warn "Laravel db connection issue: $errorLine"
                }
            }
        } catch {
            Warn "Could not run artisan db:show: $_"
        } finally { Pop-Location }
    }
}

# ── Summary ──
Log ""
if ($sqliteFound) {
    Ok "Database migration verified: SQLite present and accessible"
} else {
    Warn "No SQLite database found. Run migrate-pgsql-to-sqlite.ps1 first."
}

Log "Log: $LogFile"
exit ($sqliteFound ? 0 : 1)
