#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Install and manage Corex native Windows services for PHP-FPM, Nginx, AI Gateway, and Redis.

.DESCRIPTION
    Installs four Windows services that run directly (no Docker):
      - CorexRedis    : redis-server.exe with persistence and memory limits
      - CorexPHP      : php-fpm.exe / php-cgi.exe with error logging
      - CorexNginx    : nginx.exe reverse proxy, starts after PHP
      - CorexAIGateway: Python FastAPI via Uvicorn, starts after Redis

    Handles service creation (sc.exe / New-Service), recovery options,
    dependencies, Windows Event Log sources, and Performance Monitor counters.

.PARAMETER Action
    Action to perform: Install, Uninstall, Start, Stop, Restart, Status, UpdateConfig

.PARAMETER CorexRoot
    Root installation directory (default: C:\Program Files\Corex).

.PARAMETER RedisPort
    Redis server port (default: 6379).

.PARAMETER RedisMaxMemory
    Redis maxmemory value (default: 256mb).

.PARAMETER PHPFastCGIPort
    PHP-FPM listen port (default: 9000).

.PARAMETER AIGatewayPort
    AI Gateway FastAPI port (default: 8000).

.PARAMETER AIGatewayWorkers
    Number of Uvicorn workers (default: 4).

.PARAMETER NginxPort
    Nginx HTTP listen port (default: 80).

.EXAMPLE
    PS> .\Install-CorexNativeServices.ps1 -Action Install
    PS> .\Install-CorexNativeServices.ps1 -Action Uninstall
    PS> .\Install-CorexNativeServices.ps1 -Action Start
    PS> .\Install-CorexNativeServices.ps1 -Action Status
#>

param(
    [Parameter(Mandatory = $false)]
    [ValidateSet('Install', 'Uninstall', 'Start', 'Stop', 'Restart', 'Status', 'UpdateConfig')]
    [string]$Action = 'Install',

    [Parameter(Mandatory = $false)]
    [string]$CorexRoot = 'C:\Program Files\Corex',

    [Parameter(Mandatory = $false)]
    [int]$RedisPort = 6379,

    [Parameter(Mandatory = $false)]
    [string]$RedisMaxMemory = '256mb',

    [Parameter(Mandatory = $false)]
    [int]$PHPFastCGIPort = 9000,

    [Parameter(Mandatory = $false)]
    [int]$AIGatewayPort = 8000,

    [Parameter(Mandatory = $false)]
    [int]$AIGatewayWorkers = 4,

    [Parameter(Mandatory = $false)]
    [int]$NginxPort = 80
)

# ============================================================================
# Constants
# ============================================================================
$ErrorActionPreference = 'Stop'

# Binary paths (adjust to match your installation)
$RedisExe          = "$env:ProgramFiles\Redis\redis-server.exe"
$RedisConf         = "$env:ProgramFiles\Redis\redis.windows-service.conf"
$PHPServiceExe     = "$CorexRoot\php\php-fpm.exe"         # or php-cgi.exe -b 127.0.0.1:9000
$PHPConfDir        = "$CorexRoot\php"
$NginxExe          = "$CorexRoot\nginx\nginx.exe"
$NginxConf         = "$CorexRoot\nginx\conf\nginx.conf"
$AIGatewayDir      = "$CorexRoot\ai-gateway"
$AIGatewayMain     = "$AIGatewayDir\main.py"
$AIGatewayVenv     = "$AIGatewayDir\.venv\Scripts\python.exe"
$AIGatewayLogDir   = "$CorexRoot\logs\ai-gateway"
$PHPLogDir         = "$CorexRoot\logs\php"
$NginxLogDir       = "$CorexRoot\logs\nginx"
$RedisLogDir       = "$CorexRoot\logs\redis"

# Service definitions
$Services = @(
    @{
        Name        = 'CorexRedis'
        DisplayName = 'Corex Redis Server'
        Description = 'Redis in-memory data store for Corex AI platform'
        BinaryPath  = "`"$RedisExe`" `"$RedisConf`" --service-run"
        Dependencies = @()
        StartType   = 'Automatic'
        User        = 'NT AUTHORITY\NetworkService'
        Recovery    = @{
            FirstFailure    = 'restart'
            SecondFailure   = 'restart'
            SubsequentFailures = 'restart'
            ResetPeriod     = 86400  # 1 day
            RestartDelay    = 5000   # 5 seconds
        }
        LogSource   = 'CorexRedis'
    }
    @{
        Name        = 'CorexPHP'
        DisplayName = 'Corex PHP-FPM'
        Description = 'PHP FastCGI Process Manager for Corex Laravel backend'
        BinaryPath  = "`"$PHPServiceExe`" --fpm-config `"$PHPConfDir\php-fpm.conf`" --nodaemonize"
        Dependencies = @()
        StartType   = 'Automatic'
        User        = 'NT AUTHORITY\NetworkService'
        Recovery    = @{
            FirstFailure    = 'restart'
            SecondFailure   = 'restart'
            SubsequentFailures = 'restart'
            ResetPeriod     = 86400
            RestartDelay    = 10000  # 10 seconds (PHP may need time to release the port)
        }
        LogSource   = 'CorexPHP'
    }
    @{
        Name        = 'CorexNginx'
        DisplayName = 'Corex Nginx Web Server'
        Description = 'Nginx reverse proxy for Corex PHP backend and static assets'
        BinaryPath  = "`"$NginxExe`" -p `"$CorexRoot\nginx`""
        Dependencies = @('CorexPHP')
        StartType   = 'Automatic'
        User        = 'NT AUTHORITY\NetworkService'
        Recovery    = @{
            FirstFailure    = 'restart'
            SecondFailure   = 'restart'
            SubsequentFailures = 'restart'
            ResetPeriod     = 86400
            RestartDelay    = 10000
        }
        LogSource   = 'CorexNginx'
    }
    @{
        Name        = 'CorexAIGateway'
        DisplayName = 'Corex AI Gateway'
        Description = 'Python FastAPI AI proxy gateway with provider routing'
        BinaryPath  = "`"$AIGatewayVenv`" -m uvicorn main:app --host 0.0.0.0 --port $AIGatewayPort --workers $AIGatewayWorkers --log-level info --limit-max-requests 10000"
        Dependencies = @('CorexRedis')
        StartType   = 'Automatic'
        User        = 'NT AUTHORITY\NetworkService'
        WorkingDir  = $AIGatewayDir
        EnvVars     = @{
            PYTHONUNBUFFERED = '1'
            COREX_ROOT       = $CorexRoot
        }
        Recovery    = @{
            FirstFailure    = 'restart'
            SecondFailure   = 'restart'
            SubsequentFailures = 'restart'
            ResetPeriod     = 86400
            RestartDelay    = 5000
        }
        LogSource   = 'CorexAIGateway'
    }
)

# ============================================================================
# Helper Functions
# ============================================================================

function Write-Log {
    param([string]$Message, [ValidateSet('Info', 'Success', 'Warning', 'Error')][string]$Level = 'Info')
    $color = switch ($Level) {
        'Error'   { 'Red' }
        'Warning' { 'Yellow' }
        'Success' { 'Green' }
        default   { 'Cyan' }
    }
    Write-Host "[$Level] $Message" -ForegroundColor $color
}

function Register-EventLogSource {
    param([string]$Source)
    try {
        if (-not [System.Diagnostics.EventLog]::SourceExists($Source)) {
            [System.Diagnostics.EventLog]::CreateEventSource($Source, 'Application')
            Write-Log "Event log source '$Source' created" -Level Success
        }
    } catch {
        Write-Log "Cannot create event source '$Source': $_" -Level Warning
    }
}

function Remove-EventLogSource {
    param([string]$Source)
    try {
        if ([System.Diagnostics.EventLog]::SourceExists($Source)) {
            [System.Diagnostics.EventLog]::DeleteEventSource($Source)
            Write-Log "Event log source '$Source' removed" -Level Success
        }
    } catch {
        Write-Log "Cannot remove event source '$Source': $_" -Level Warning
    }
}

function Write-EventLogEntry {
    param(
        [string]$Source,
        [string]$Message,
        [System.Diagnostics.EventLogEntryType]$EntryType = 'Information',
        [int]$EventId = 1000
    )
    try {
        if ([System.Diagnostics.EventLog]::SourceExists($Source)) {
            Write-EventLog -LogName Application -Source $Source -Message $Message -EntryType $EntryType -EventId $EventId
        }
    } catch {
        # Silently fail — event log should never block installation
    }
}

function Invoke-ServiceCommand {
    param([string]$Name, [string]$Command)
    $result = sc.exe $Command $Name
    if ($LASTEXITCODE -ne 0 -and $LASTEXITCODE -ne 1062) {
        Write-Log "sc.exe $Command $Name returned exit code $LASTEXITCODE" -Level Warning
    }
}

# ============================================================================
# Service Installation (using sc.exe for maximum compatibility)
# ============================================================================

function Install-NativeService {
    param([hashtable]$Svc)

    $name   = $Svc.Name
    $bin    = $Svc.BinaryPath
    $desc   = $Svc.Description
    $start  = $Svc.StartType
    $user   = $Svc.User

    Write-Log "Installing service: $name" -Level Info

    # --- 1. Create the service ---
    # First try New-Service (more PowerShell-friendly), fall back to sc.exe
    try {
        $existing = Get-Service -Name $name -ErrorAction SilentlyContinue
        if ($existing) {
            Write-Log "Service '$name' already exists, updating configuration..." -Level Info
            sc.exe config $name start= $start binPath= $bin
            if ($LASTEXITCODE -ne 0) { throw "sc.exe config failed" }
        } else {
            New-Service -Name $name -BinaryPathName $bin -DisplayName $Svc.DisplayName -StartupType $start -ErrorAction Stop
            Write-Log "Service '$name' created via New-Service" -Level Success
        }
    } catch {
        Write-Log "New-Service failed, trying sc.exe create: $_" -Level Warning
        Invoke-ServiceCommand $name "create $name binPath= $bin start= $start DisplayName= `"$($Svc.DisplayName)`""
    }

    # --- 2. Description ---
    sc.exe description $name $desc

    # --- 3. Service user account ---
    if ($user) {
        sc.exe config $name obj= $user
        if ($Svc.ContainsKey('Password')) {
            sc.exe config $name password= $Svc.Password
        }
    }

    # --- 4. Working directory ---
    if ($Svc.ContainsKey('WorkingDir') -and $Svc.WorkingDir) {
        sc.exe config $name binPath= "cd /d `"$($Svc.WorkingDir)`" && $bin"
    }

    # --- 5. Environment variables (via sc.exe) ---
    if ($Svc.ContainsKey('EnvVars')) {
        $envBlock = ($Svc.EnvVars.GetEnumerator() | ForEach-Object { "$($_.Key)=$($_.Value)" }) -join '`0'
        sc.exe config $name env= $envBlock
    }

    # --- 6. Dependencies ---
    if ($Svc.Dependencies.Count -gt 0) {
        $depStr = ($Svc.Dependencies -join '/')
        sc.exe config $name depend= $depStr
        Write-Log "Dependencies set: $depStr" -Level Success
    }

    # --- 7. Recovery options ---
    $rec = $Svc.Recovery
    $actions = "$($rec.FirstFailure)/$($rec.SecondFailure)/$($rec.SubsequentFailures)"
    sc.exe failure $name reset= $rec.ResetPeriod actions= $actions
    # Set per-action delay (milliseconds)
    sc.exe failure $name reset= $rec.ResetPeriod actions= "$($actions)/60000/$($rec.RestartDelay)/60000/$($rec.RestartDelay)/60000"
    sc.exe failureflag $name flag= 1

    # --- 8. Pre-shutdown notification (give 30s to drain) ---
    sc.exe config $name pri= 30000

    # --- 9. Register Event Log source ---
    Register-EventLogSource -Source $Svc.LogSource

    Write-Log "Service '$name' installed successfully" -Level Success
}

function Uninstall-NativeService {
    param([string]$Name, [string]$LogSource)

    Write-Log "Uninstalling service: $Name" -Level Info
    try {
        $svc = Get-Service -Name $Name -ErrorAction SilentlyContinue
        if ($svc -and $svc.Status -eq 'Running') {
            Stop-Service -Name $Name -Force -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
        }
        sc.exe delete $Name
        Write-Log "Service '$Name' deleted" -Level Success
    } catch {
        Write-Log "Failed to remove service '$Name': $_" -Level Warning
    }
    Remove-EventLogSource -Source $LogSource
}

function Get-NativeServiceStatus {
    Write-Log "Checking native service status..." -Level Info
    foreach ($svc in $Services) {
        try {
            $s = Get-Service -Name $svc.Name -ErrorAction SilentlyContinue
            if ($s) {
                Write-Log "$($svc.Name): $($s.Status)" -Level $(if ($s.Status -eq 'Running') { 'Success' } else { 'Warning' })
            } else {
                Write-Log "$($svc.Name): NOT INSTALLED" -Level Error
            }
        } catch {
            Write-Log "$($svc.Name): ERROR - $_" -Level Error
        }
    }
}

function Start-NativeServices {
    # Start in dependency order: Redis -> PHP -> AI Gateway, Nginx
    $order = @('CorexRedis', 'CorexPHP', 'CorexAIGateway', 'CorexNginx')
    foreach ($name in $order) {
        try {
            $svc = Get-Service -Name $name -ErrorAction SilentlyContinue
            if ($svc -and $svc.Status -ne 'Running') {
                Write-Log "Starting $name..." -Level Info
                Start-Service -Name $name -ErrorAction Stop
                Write-Log "$name started" -Level Success
                Start-Sleep -Seconds 3
            } elseif ($svc) {
                Write-Log "$name already running" -Level Info
            } else {
                Write-Log "$name not installed" -Level Warning
            }
        } catch {
            Write-Log "Failed to start $name: $_" -Level Error
        }
    }
}

function Stop-NativeServices {
    $order = @('CorexNginx', 'CorexAIGateway', 'CorexPHP', 'CorexRedis')
    foreach ($name in $order) {
        try {
            $svc = Get-Service -Name $name -ErrorAction SilentlyContinue
            if ($svc -and $svc.Status -eq 'Running') {
                Write-Log "Stopping $name..." -Level Info
                Stop-Service -Name $name -Force -ErrorAction Stop
                Write-Log "$name stopped" -Level Success
            }
        } catch {
            Write-Log "Failed to stop $name: $_" -Level Warning
        }
    }
}

# ============================================================================
# Performance Monitoring Setup
# ============================================================================

function Install-PerformanceMonitors {
    Write-Log "Setting up Performance Monitor data collector sets..." -Level Info

    $logDir = "$CorexRoot\logs\perfmon"
    if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }

    # Create a data collector set using logman
    $collectorSet = 'Corex Services'

    # Remove existing set
    logman stop $collectorSet -s 2>$null
    logman delete $collectorSet -s 2>$null

    # Create new collector set with counters for all services
    $counters = @(
        # Redis
        '\Process(redis-server)\% Processor Time'
        '\Process(redis-server)\Private Bytes'
        '\Process(redis-server)\Working Set'
        # PHP
        '\Process(php-fpm*)\% Processor Time'
        '\Process(php-fpm*)\Private Bytes'
        # Nginx
        '\Process(nginx*)\% Processor Time'
        '\Process(nginx*)\Private Bytes'
        # AI Gateway (Python)
        '\Process(python*)\% Processor Time'
        '\Process(python*)\Private Bytes'
        # System-level
        '\Memory\Available MBytes'
        '\Memory\Committed Bytes'
        '\Processor(_Total)\% Processor Time'
        '\System\Processes'
        '\System\Threads'
    )

    $counterArgs = ($counters -join ' ')
    logman create counter $collectorSet `
        -o "$logDir\CorexPerfmon" `
        -cf "`"$(New-TemporaryFile)`"" `
        --% -v binext -si 30 -f bincirc -max 500

    # Workaround: use cfg file approach
    $cfgFile = "$env:TEMP\corex-perfmon-counters.txt"
    $counters | Set-Content -Path $cfgFile

    logman create counter $collectorSet `
        -o "$logDir\CorexPerfmon" `
        -cf "$cfgFile" `
        -v binext -si 30 -f bincirc -max 500

    # Schedule automatic start
    logman start $collectorSet -s

    Write-Log "Performance counters configured for all Corex services" -Level Success

    # Create scheduled task to start perfmon on boot
    $taskName = '\Corex\Corex-PerfMon'
    try {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    } catch {}

    $action = New-ScheduledTaskAction -Execute 'logman.exe' -Argument "start `"$collectorSet`" -s"
    $trigger = New-ScheduledTaskTrigger -AtStartup
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Description 'Corex Performance Monitoring' -Force
    Write-Log "PerfMon scheduled task '$taskName' created" -Level Success
}

function Uninstall-PerformanceMonitors {
    $collectorSet = 'Corex Services'
    logman stop $collectorSet -s 2>$null
    logman delete $collectorSet -s 2>$null
    Write-Log "Performance monitors removed" -Level Info

    try {
        Unregister-ScheduledTask -TaskName '\Corex\Corex-PerfMon' -Confirm:$false -ErrorAction SilentlyContinue
        Write-Log "PerfMon scheduled task removed" -Level Info
    } catch {}
}

# ============================================================================
# Directory Structure Setup
# ============================================================================

function Initialize-Directories {
    $dirs = @(
        $CorexRoot, $AIGatewayLogDir, $PHPLogDir, $NginxLogDir, $RedisLogDir,
        "$CorexRoot\logs\perfmon",
        "$CorexRoot\php",
        "$CorexRoot\nginx",
        $AIGatewayDir
    )
    foreach ($d in $dirs) {
        if (-not (Test-Path $d)) {
            New-Item -ItemType Directory -Path $d -Force | Out-Null
            Write-Log "Created directory: $d" -Level Success
        }
    }
}

# ============================================================================
# AI Gateway – Python venv bootstrap
# ============================================================================

function Install-AIGatewayDependencies {
    if (-not (Test-Path $AIGatewayVenv)) {
        Write-Log "Creating Python virtual environment at $AIGatewayVenv..." -Level Info
        & "$env:ProgramFiles\Python312\python.exe" -m venv "$AIGatewayDir\.venv"
        if ($LASTEXITCODE -ne 0) {
            Write-Log "Trying 'python' from PATH..." -Level Warning
            & python -m venv "$AIGatewayDir\.venv"
        }
    }

    $reqFile = "$AIGatewayDir\requirements.txt"
    if (Test-Path $reqFile) {
        Write-Log "Installing AI Gateway Python dependencies..." -Level Info
        & "$AIGatewayVenv" -m pip install -r "$reqFile" --quiet
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Python dependencies installed" -Level Success
        } else {
            Write-Log "pip install had warnings, check manually" -Level Warning
        }
    }
}

# ============================================================================
# Redis Configuration
# ============================================================================

function Install-RedisConfiguration {
    if (Test-Path $RedisConf) { return }

    Write-Log "Creating Redis configuration at $RedisConf..." -Level Info
    $redisConfig = @"
# Corex Redis Configuration
port $RedisPort
bind 127.0.0.1
protected-mode yes
daemonize no
loglevel notice
logfile `"$RedisLogDir\redis-server.log`"
dir `"$CorexRoot\data\redis`"
dbfilename dump.rdb
appendonly yes
appendfsync everysec
maxmemory $RedisMaxMemory
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
timeout 0
tcp-keepalive 300
lfu-log-factor 10
lfu-decay-time 1
"@
    Set-Content -Path $RedisConf -Value $redisConfig -Encoding ASCII

    $dataDir = "$CorexRoot\data\redis"
    if (-not (Test-Path $dataDir)) { New-Item -ItemType Directory -Path $dataDir -Force | Out-Null }
    Write-Log "Redis configuration created" -Level Success
}

# ============================================================================
# Main Dispatch
# ============================================================================

try {
    switch ($Action) {
        'Install' {
            Write-Log "=== Corex Native Services Installation ===" -Level Info
            Initialize-Directories
            Install-RedisConfiguration
            Install-AIGatewayDependencies
            Install-PerformanceMonitors
            foreach ($svc in $Services) {
                Install-NativeService -Svc $svc
            }
            Write-Log "=== Installation Complete ===" -Level Success
            Write-Log "Start services with:  .\Install-CorexNativeServices.ps1 -Action Start" -Level Info
        }
        'Uninstall' {
            Write-Log "=== Corex Native Services Uninstall ===" -Level Info
            Stop-NativeServices
            foreach ($svc in $Services) {
                Uninstall-NativeService -Name $svc.Name -LogSource $svc.LogSource
            }
            Uninstall-PerformanceMonitors
            Write-Log "=== Uninstall Complete ===" -Level Success
        }
        'Start' {
            Write-Log "=== Corex Native Services Start ===" -Level Info
            Start-NativeServices
            Write-Log "=== All Services Started ===" -Level Success
        }
        'Stop' {
            Write-Log "=== Corex Native Services Stop ===" -Level Info
            Stop-NativeServices
        }
        'Restart' {
            Write-Log "=== Corex Native Services Restart ===" -Level Info
            Stop-NativeServices
            Start-Sleep -Seconds 3
            Start-NativeServices
        }
        'Status' {
            Get-NativeServiceStatus
        }
        'UpdateConfig' {
            Write-Log "Updating Redis configuration..." -Level Info
            Install-RedisConfiguration
            Write-Log "Restart Redis to apply: Restart-Service CorexRedis" -Level Info
        }
    }
} catch {
    Write-Log "Fatal error: $_" -Level Error
    Write-EventLogEntry -Source 'CorexInstaller' -Message "Installation failed: $_" -EntryType Error -EventId 9999
    exit 1
}
