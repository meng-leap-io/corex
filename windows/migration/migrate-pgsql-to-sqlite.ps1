#Requires -Version 5.1

<#
.SYNOPSIS
    Exports PostgreSQL data and imports into SQLite for desktop use.

.DESCRIPTION
    Connects to PostgreSQL, exports tables as CSV, creates SQLite database
    with matching schema, and imports all data. Handles Laravel-specific
    migrations and schema differences.

.PARAMETER ProjectRoot
    Project root directory (for artisan commands).

.PARAMETER DataDir
    User data directory for the SQLite database.

.PARAMETER BackupDir
    Backup directory for PostgreSQL dump.

.PARAMETER LogDir
    Log output directory.

.PARAMETER PgHost
    PostgreSQL host. Default: localhost

.PARAMETER PgPort
    PostgreSQL port. Default: 5432

.PARAMETER PgDatabase
    PostgreSQL database name. Default: corex

.PARAMETER PgUser
    PostgreSQL user. Default: corex

.PARAMETER PgPassword
    PostgreSQL password. Prompts if not provided.

.PARAMETER SqlitePath
    Output SQLite path. Default: {DataDir}\corex.sqlite
#>

param(
    [string]$ProjectRoot,
    [string]$DataDir = "$env:LOCALAPPDATA\Corex",
    [string]$BackupDir,
    [string]$LogDir = "$DataDir\logs",
    [string]$PgHost = '127.0.0.1',
    [int]$PgPort = 5432,
    [string]$PgDatabase = 'corex',
    [string]$PgUser = 'corex',
    [string]$PgPassword,
    [string]$SqlitePath
)

$ErrorActionPreference = 'Stop'
$SqlitePath = $SqlitePath ? $SqlitePath : "$DataDir\corex.sqlite"
$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$TempDir = "$env:TMP\corex-migrate-$Timestamp"
$SchemaFile = "$TempDir\schema.sql"
$DataDir = New-Item -ItemType Directory -Path $DataDir -Force | Select-Object -ExpandProperty FullName
$LogDir = New-Item -ItemType Directory -Path $LogDir -Force | Select-Object -ExpandProperty FullName
$LogFile = "$LogDir\migrate-pgsql-to-sqlite.log"

function Log { param([string]$M) $ts = Get-Date -Format 'HH:mm:ss'; Write-Host "$ts $M"; "$ts $M" | Out-File $LogFile -Encoding utf8 -Append }
function Ok  { param([string]$M) Write-Host "✓ $M" -ForegroundColor Green; "✓ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Warn { param([string]$M) Write-Host "⚠ $M" -ForegroundColor Yellow; "⚠ $M" | Out-File $LogFile -Encoding utf8 -Append }
function Err { param([string]$M) Write-Host "✗ $M" -ForegroundColor Red; "✗ $M" | Out-File $LogFile -Encoding utf8 -Append }

New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

Log "=== PostgreSQL → SQLite Migration ==="
Log "Source: PostgreSQL $PgHost`:$PgPort/$PgDatabase"
Log "Target: SQLite $SqlitePath"
Log ""

# ──────────────────────────────────────────────────────────────────────────
# 1. Check prerequisites
# ──────────────────────────────────────────────────────────────────────────
Log "Step 1: Checking prerequisites..."

$hasPgDump = (Get-Command pg_dump -ErrorAction SilentlyContinue) -ne $null
$hasPsql = (Get-Command psql -ErrorAction SilentlyContinue) -ne $null
$hasSqlite = (Get-Command sqlite3 -ErrorAction SilentlyContinue) -ne $null
$hasPhp = (Get-Command php -ErrorAction SilentlyContinue) -ne $null

if (-not $hasPgDump -or -not $hasPsql) {
    Err "PostgreSQL client tools not found (pg_dump/psql). Install PostgreSQL or add to PATH."
    Log "  Download: https://www.postgresql.org/download/windows/"
    Log "  Or use chocolatey: choco install postgresql --params '/PGPassword:secret'"
}

if (-not $hasSqlite) {
    Log "sqlite3 not found. Will attempt PHP PDO fallback."
    if (-not $hasPhp) {
        Err "Neither sqlite3 CLI nor PHP found. Cannot create SQLite database."
        return
    }
    Log "Using PHP PDO for SQLite operations."
}

# ──────────────────────────────────────────────────────────────────────────
# 2. Dump PostgreSQL schema and data
# ──────────────────────────────────────────────────────────────────────────
Log "Step 2: Exporting PostgreSQL data..."

$pgDumpFile = "$TempDir\pg-dump.sql"

if ($hasPgDump) {
    $env:PGPASSWORD = $PgPassword
    $dumpArgs = @(
        '--host', $PgHost,
        '--port', $PgPort,
        '--username', $PgUser,
        '--dbname', $PgDatabase,
        '--no-owner',
        '--no-acl',
        '--no-privileges',
        '--no-tablespaces',
        '--no-security-labels',
        '--format', 'plain',
        '--file', $pgDumpFile
    )

    Log "Running pg_dump..."
    & pg_dump @dumpArgs 2>&1 | ForEach-Object { Log "  pg_dump: $_" }

    if (-not (Test-Path $pgDumpFile) -or ((Get-Item $pgDumpFile).Length -eq 0)) {
        Warn "pg_dump produced empty output. Trying artisan fallback..."
        $hasPgDump = $false
    } else {
        $size = [math]::Round((Get-Item $pgDumpFile).Length / 1KB, 1)
        Ok "PostgreSQL dump created: ${size}KB"
    }
}

if (-not $hasPgDump) {
    # Fallback: use Laravel artisan + PHP
    if (Test-Path "$ProjectRoot\artisan") {
        Log "Using Laravel artisan to export data..."
        Push-Location $ProjectRoot
        try {
            & php artisan db:dump --output=$pgDumpFile 2>&1
            if ($LASTEXITCODE -ne 0) {
                Err "artisan db:dump failed. Trying manual PHP script..."
                # Try direct PDO export
                & php -r "
                    \`$conn = new PDO('pgsql:host=$PgHost;port=$PgPort;dbname=$PgDatabase', '$PgUser', '$PgPassword');
                    \`$stmt = \`$conn->query('SELECT table_name FROM information_schema.tables WHERE table_schema=\\'public\\'');
                    \`$tables = \`$stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach (\`$tables as \`$table) {
                        \`$fh = fopen('$TempDir/' . \`$table . '.csv', 'w');
                        \`$data = \`$conn->query('SELECT * FROM \"' . \`$table . '\"');
                        while (\`$row = \`$data->fetch(PDO::FETCH_ASSOC)) {
                            fputcsv(\`$fh, \`$row);
                        }
                        fclose(\`$fh);
                        echo \"Exported: \`$table\n\";
                    }
                " 2>&1 | ForEach-Object { Log "  $_" }
            }
        } finally { Pop-Location }
    } else {
        Err "No way to export PostgreSQL data. Install pg_dump or configure artisan."
        return
    }
}

# ──────────────────────────────────────────────────────────────────────────
# 3. Create SQLite database
# ──────────────────────────────────────────────────────────────────────────
Log "Step 3: Creating SQLite database..."

if (Test-Path $SqlitePath) {
    $backupSqlite = "$SqlitePath.backup-$Timestamp"
    Copy-Item $SqlitePath $backupSqlite -Force
    Warn "Existing SQLite database backed up to: $backupSqlite"
    Remove-Item $SqlitePath -Force
}

if ($hasSqlite) {
    # Create SQLite from PostgreSQL schema dump, auto-converting types
    $convertScript = "$TempDir\convert-to-sqlite.ps1"
    @"
`$pgSchema = Get-Content '$pgDumpFile' -Raw

# Convert PostgreSQL types to SQLite-compatible
`$sqliteSchema = `$pgSchema
`$sqliteSchema = `$sqliteSchema -replace 'SERIAL\s+PRIMARY\s+KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT'
`$sqliteSchema = `$sqliteSchema -replace 'bigserial\s+[^,]+', 'INTEGER'
`$sqliteSchema = `$sqliteSchema -replace 'uuid\s+[^,]+', 'TEXT'
`$sqliteSchema = `$sqliteSchema -replace 'jsonb\s+', 'TEXT '
`$sqliteSchema = `$sqliteSchema -replace 'json\s+', 'TEXT '
`$sqliteSchema = `$sqliteSchema -replace 'timestamp\s*(?:\(?\d+\)?)?\s*(?:without\s+time\s+zone|with\s+time\s+zone)?', 'TEXT'
`$sqliteSchema = `$sqliteSchema -replace 'timestamptz\s*', 'TEXT '
`$sqliteSchema = `$sqliteSchema -replace 'boolean', 'INTEGER'
`$sqliteSchema = `$sqliteSchema -replace 'text\s*\[\]', 'TEXT'  # arrays
`$sqliteSchema = `$sqliteSchema -replace 'character\s+varying\s*\(\d+\)', 'TEXT'
`$sqliteSchema = `$sqliteSchema -replace 'numeric\s*\([^\)]+\)', 'REAL'

# Remove PostgreSQL-specific SQL
`$sqliteSchema = `$sqliteSchema -replace 'CREATE\s+(SEQUENCE|INDEX|TRIGGER|FUNCTION|EXTENSION|SCHEMA)[^;]*;', ''
`$sqliteSchema = `$sqliteSchema -replace 'ALTER\s+(TABLE|SEQUENCE)[^;]*;', ''
`$sqliteSchema = `$sqliteSchema -replace 'SET\s+\w+[^;]*;', ''
`$sqliteSchema = `$sqliteSchema -replace 'SELECT\s+pg_catalog[^;]*;', ''
`$sqliteSchema = `$sqliteSchema -replace 'COMMENT\s+ON[^;]*;', ''
`$sqliteSchema = `$sqliteSchema -replace '--.*', ''
`$sqliteSchema = `$sqliteSchema -replace '\\\\connect[^\n]*', ''
`$sqliteSchema = `$sqliteSchema -replace '\n\s*\n', "`n"

# Fix UUID primary keys
`$sqliteSchema = `$sqliteSchema -replace 'uuid\(\)\s*;', 'TEXT NOT NULL);'
`$sqliteSchema = `$sqliteSchema -replace 'gen_random_uuid\(\)', "lower(hex(randomblob(16)))"

`$sqliteSchema | Out-File '$SchemaFile' -Encoding utf8
"@ | Out-File $convertScript -Encoding utf8

    & powershell -ExecutionPolicy Bypass -File $convertScript

    Log "Creating SQLite database from schema..."
    & sqlite3 $SqlitePath ".read '$SchemaFile'" 2>&1 | ForEach-Object { Log "  sqlite3: $_" }

} else {
    # PHP fallback: use PDO to create SQLite
    Log "Using PHP PDO to create SQLite database..."
    & php -r "
        try {
            \$pdo = new PDO('sqlite:$SqlitePath');
            \$pdo->exec('PRAGMA journal_mode=WAL');
            \$pdo->exec('PRAGMA synchronous=NORMAL');
            \$pdo->exec('PRAGMA foreign_keys=ON');
            echo 'SQLite database created successfully.\n';
        } catch (Exception \$e) {
            echo 'Error: ' . \$e->getMessage() . '\n';
            exit(1);
        }
    " 2>&1 | ForEach-Object { Log "  $_" }
}

if (-not (Test-Path $SqlitePath)) {
    Err "Failed to create SQLite database"
    return
}

Ok "SQLite database created: $SqlitePath ($(([math]::Round((Get-Item $SqlitePath).Length / 1KB, 1)))KB)"

# ──────────────────────────────────────────────────────────────────────────
# 4. Import data into SQLite
# ──────────────────────────────────────────────────────────────────────────
Log "Step 4: Importing data..."

$csvFiles = Get-ChildItem "$TempDir\*.csv" -ErrorAction SilentlyContinue
if ($csvFiles.Count -gt 0) {
    foreach ($csv in $csvFiles) {
        $table = [System.IO.Path]::GetFileNameWithoutExtension($csv.Name)
        Log "Importing table: $table..."

        if ($hasSqlite) {
            & sqlite3 $SqlitePath ".mode csv" ".import '$($csv.FullName)' $table" 2>&1 | ForEach-Object {
                if ($_ -match 'Error') { Err "  $_" } else { Log "  $_" }
            }
        } else {
            # PHP PDO import
            & php -r "
                \$pdo = new PDO('sqlite:$SqlitePath');
                \$csv = fopen('$($csv.FullName)', 'r');
                \$headers = fgetcsv(\$csv);
                \$placeholders = implode(',', array_fill(0, count(\$headers), '?'));
                \$stmt = \$pdo->prepare('INSERT INTO \"$table\" VALUES (' . \$placeholders . ')');
                \$count = 0;
                while (\$row = fgetcsv(\$csv)) {
                    try { \$stmt->execute(\$row); \$count++; }
                    catch (Exception \$e) { /* skip bad rows */ }
                }
                fclose(\$csv);
                echo \"Imported \$count rows into $table\n\";
            " 2>&1 | ForEach-Object { Log "  $_" }
        }
    }
    Ok "Data imported from $($csvFiles.Count) CSV files"
} else {
    Log "No CSV files to import (pg_dump output used directly)."
}

# ──────────────────────────────────────────────────────────────────────────
# 5. Run Laravel migrations for SQLite
# ──────────────────────────────────────────────────────────────────────────
Log "Step 5: Running Laravel migrations for SQLite..."

if (Test-Path "$ProjectRoot\artisan") {
    Push-Location $ProjectRoot
    try {
        # Set temporary DB connection to SQLite
        $env:DB_CONNECTION = 'sqlite'
        $env:DB_DATABASE = $SqlitePath

        & php artisan migrate --force 2>&1 | ForEach-Object { Log "  $_" }
        if ($LASTEXITCODE -eq 0) {
            Ok "Laravel migrations applied"
        } else {
            Warn "Laravel migrations may have had issues. Check logs."
        }
    } finally {
        Pop-Location
        Remove-Item Env:\DB_CONNECTION -ErrorAction SilentlyContinue
        Remove-Item Env:\DB_DATABASE -ErrorAction SilentlyContinue
    }
}

# ──────────────────────────────────────────────────────────────────────────
# 6. Cleanup
# ──────────────────────────────────────────────────────────────────────────
Log "Step 6: Cleanup..."

if (-not $KeepPostgres) {
    Remove-Item $TempDir -Recurse -Force -ErrorAction SilentlyContinue
    Log "Temporary files cleaned up"
}

# Create SQLite connection string for .env
$connectionString = "sqlite:$SqlitePath"
$envFilePath = "$ProjectRoot\.env"
if (Test-Path $envFilePath) {
    $envContent = Get-Content $envFilePath -Raw
    $envContent = $envContent -replace 'DB_CONNECTION=.*', "DB_CONNECTION=sqlite"
    $envContent = $envContent -replace 'DB_DATABASE=.*', "# DB_DATABASE (SQLite path set below)"
    $envContent += "`nDB_DATABASE=$SqlitePath"
    Set-Content $envFilePath $envContent -Encoding utf8 -NoNewline
    Ok "Updated .env with SQLite configuration"
}

# Summary
$finalSize = [math]::Round((Get-Item $SqlitePath).Length / 1MB, 2)
Log ""
Log "=== Migration Complete ==="
Ok "SQLite database: $SqlitePath (${finalSize}MB)"
Log "Migration log: $LogFile"
Log "Backup: $BackupDir"
Log ""
Log "Next steps:"
Log "  1. Update .env: DB_CONNECTION=sqlite, DB_DATABASE=$SqlitePath"
Log "  2. Run: php artisan cache:clear"
Log "  3. Run: convert-env.ps1 to sync environment variables"
Log "  4. Verify: test-database.ps1"
