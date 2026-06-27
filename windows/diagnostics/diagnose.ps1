#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Windows Diagnostic Suite

.DESCRIPTION
    Comprehensive diagnostic tool for Corex on Windows. Tests paths, permissions,
    ports, environment variables, registry, Windows Defender exclusions, UAC
    elevation, and platform-specific issues.

.PARAMETER OutputDir
    Directory to write diagnostic report. Default: ./logs/diagnostic-{timestamp}/

.PARAMETER Quick
    Run only essential checks (skip Defender scan, registry deep scan)

.EXAMPLE
    PS> .\diagnose.ps1
    PS> .\diagnose.ps1 -Quick
    PS> .\diagnose.ps1 -OutputDir C:\Users\me\Desktop\corex-diagnostic
#>

param(
    [string]$OutputDir,
    [switch]$Quick
)

$ErrorActionPreference = 'Stop'
$VerbosePreference = 'Continue'

# ── Paths ──────────────────────────────────────────────────────────────────
$ScriptDir = Split-Path -Parent $PSCommandPath
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir '..\..')
$LogDir = if ($OutputDir) { $OutputDir } else {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    Join-Path $ScriptDir '..\logs' "diagnostic-$ts"
}
$ReportFile = Join-Path $LogDir 'diagnostic-report.txt'
$JsonReport = Join-Path $LogDir 'diagnostic-report.json'

# ── State ──────────────────────────────────────────────────────────────────
$Issues = @()
$Warnings = @()
$Passed = @()
$Summary = @{
    timestamp = (Get-Date -Format o)
    hostname = $env:COMPUTERNAME
    os = @{}
    paths = @{}
    ports = @{}
    env = @{}
    registry = @{}
    defender = @{}
    uac = @{}
    php = @{}
    python = @{}
    services = @{}
    docker = @{}
    network = @{}
    permissions = @{}
    performance = @{}
}

# ═══════════════════════════════════════════════════════════════════════════
# Helper Functions
# ═══════════════════════════════════════════════════════════════════════════

function Write-Diag {
    [CmdletBinding()]
    param([string]$Message, [ValidateSet('Info','Ok','Warn','Fail','Header')][string]$Level = 'Info')

    $color = @{ Info = 'Gray'; Ok = 'Green'; Warn = 'Yellow'; Fail = 'Red'; Header = 'Cyan' }[$Level]
    $prefix = @{ Info = '  '; Ok = '✓ '; Warn = '⚠ '; Fail = '✗ '; Header = '──' }[$Level]
    Write-Host "$prefix$Message" -ForegroundColor $color
}

function Add-Issue {
    param([string]$Category, [string]$Message, [string]$Severity = 'error', [string]$Fix)
    $Issues += @{ category = $Category; message = $Message; severity = $Severity; fix = $Fix }
    Write-Diag "$Message" -Level ($Severity -eq 'error' ? 'Fail' : 'Warn')
}

function Add-Pass {
    param([string]$Category, [string]$Message)
    $Passed += @{ category = $Category; message = $Message }
    Write-Diag $Message -Level Ok
}

function Write-Header {
    param([string]$Title)
    Write-Host ""
    Write-Diag " $Title " -Level Header
}

# ═══════════════════════════════════════════════════════════════════════════
# 1. OS Information
# ═══════════════════════════════════════════════════════════════════════════

function Check-OS {
    Write-Header "1. Operating System"

    $os = Get-CimInstance Win32_OperatingSystem
    $cs = Get-CimInstance Win32_ComputerSystem
    $Summary.os = @{
        caption = $os.Caption
        version = $os.Version
        build   = $os.BuildNumber
        arch    = $os.OSArchitecture
        install_date = $os.InstallDate
        last_boot    = $os.LastBootUpTime
        manufacturer = $cs.Manufacturer
        model = $cs.Model
        total_ram_gb = [math]::Round($cs.TotalPhysicalMemory / 1GB, 1)
        logical_cores = $cs.NumberOfLogicalProcessors
        os_drive_free_gb = [math]::Round((Get-PSDrive C).Free / 1GB, 1)
    }

    Add-Pass os "OS: $($os.Caption) build $($os.BuildNumber)"
    Add-Pass os "Architecture: $($os.OSArchitecture)"
    Add-Pass os "RAM: $($Summary.os.total_ram_gb) GB | Cores: $($Summary.os.logical_cores)"
    Add-Pass os "Free disk (C:\): $($Summary.os.os_drive_free_gb) GB"

    if ($Summary.os.total_ram_gb -lt 8) {
        Add-Issue os "RAM below 8 GB ($($Summary.os.total_ram_gb) GB)" warning "Upgrade RAM to 8 GB minimum, 16 GB recommended"
    }
    if ($Summary.os.logical_cores -lt 4) {
        Add-Issue os "CPU cores below 4 ($($Summary.os.logical_cores))" warning "At least 4 CPU cores required"
    }
    if ($Summary.os.os_drive_free_gb -lt 10) {
        Add-Issue os "Less than 10 GB free on C:\" error "Free up disk space. Corex needs at least 10 GB"
    }

    $uptime = (Get-Date) - $os.LastBootUpTime
    Add-Pass os "Uptime: $($uptime.Days)d $($uptime.Hours)h $($uptime.Minutes)m"

    if ($uptime.TotalDays -gt 30) {
        Add-Issue os "System has been running for $([math]::Round($uptime.TotalDays)) days" warning "Consider restarting to clear memory leaks"
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 2. UAC & Privileges
# ═══════════════════════════════════════════════════════════════════════════

function Check-UAC {
    Write-Header "2. UAC & Elevation"

    $isElevated = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    $Summary.uac.elevated = $isElevated

    if (-not $isElevated) {
        Add-Issue uac "Script not running as Administrator" error "Re-run as Administrator (right-click → Run as Administrator)"
    } else {
        Add-Pass uac "Running with Administrator privileges"
    }

    # Check UAC level
    $uacKey = 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System'
    $consentLevel = (Get-ItemProperty -Path $uacKey -Name 'ConsentPromptBehaviorAdmin' -ErrorAction SilentlyContinue).ConsentPromptBehaviorAdmin
    $enableLUA = (Get-ItemProperty -Path $uacKey -Name 'EnableLUA' -ErrorAction SilentlyContinue).EnableLUA
    $Summary.uac.consent_prompt = $consentLevel
    $Summary.uac.enable_lua = $enableLUA

    if ($enableLUA -eq 0) {
        Add-Issue uac "UAC is disabled (EnableLUA=0)" warning "UAC is recommended for security"
    }

    if ($consentLevel -eq 0) {
        Add-Issue uac "UAC: Admin approval mode disabled (ConsentPromptBehaviorAdmin=0)" warning "Service installs may require elevation"
    }

    # Token filter (UAC virtualization)
    $filterKey = 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System'
    $enableVirtualization = (Get-ItemProperty -Path $filterKey -Name 'EnableVirtualization' -ErrorAction SilentlyContinue).EnableVirtualization
    $Summary.uac.virtualization = $enableVirtualization
}

# ═══════════════════════════════════════════════════════════════════════════
# 3. Path Length & Permissions
# ═══════════════════════════════════════════════════════════════════════════

function Check-Paths {
    Write-Header "3. Paths & Permissions"

    $checks = @(
        @{ Name = 'Project root'; Path = $ProjectRoot },
        @{ Name = 'Backend'; Path = Join-Path $ProjectRoot 'backend' },
        @{ Name = 'AI Gateway'; Path = Join-Path $ProjectRoot 'ai-gateway' },
        @{ Name = 'Electron'; Path = Join-Path $ProjectRoot 'electron' },
        @{ Name = 'Windows scripts'; Path = Join-Path $ProjectRoot 'windows' }
    )

    $longPaths = @()
    $numErrors = 0

    foreach ($check in $checks) {
        $path = $check.Path
        if (Test-Path $path) {
            $len = $path.Length
            $canWrite = (New-Object Security.AccessControl.FileSecurity $path, 'Access').AreAccessRulesProtected
            $Summary.paths[$check.Name] = @{ path = $path; length = $len; exists = $true }
            Add-Pass paths "$($check.Name): $path (${len} chars)"

            if ($len -gt 200) {
                $longPaths += @{ name = $check.Name; path = $path; length = $len }
                Add-Issue paths "Long path ($len chars): $path" warning "MAX_PATH is 260 chars. Move project closer to root (e.g., C:\corex)"
            }

            # Check permissions
            try {
                $testFile = Join-Path $path ".corex-write-test"
                Set-Content -Path $testFile -Value "test" -Force
                Remove-Item -Path $testFile -Force
                Add-Pass permissions "Write access: $($check.Name)"
            } catch {
                $numErrors++
                Add-Issue permissions "Cannot write to $($check.Name): $_" error "Check folder permissions or run as Administrator"
            }

            # Check for long file paths recursively (depth 2)
            if (-not $Quick) {
                $longFiles = Get-ChildItem $path -Recurse -Depth 2 -ErrorAction SilentlyContinue |
                    Where-Object { $_.FullName.Length -gt 250 } |
                    Select-Object FullName, @{N='Length';E={$_.FullName.Length}}
                foreach ($f in $longFiles) {
                    $longPaths += @{ name = $f.FullName; path = $f.FullName; length = $f.Length }
                }
            }
        } else {
            Add-Issue paths "Missing: $($check.Name) at $path" error "Ensure repository is complete. Re-clone if needed."
        }
    }

    if ($longPaths.Count -gt 0) {
        Add-Issue paths "Found $($longPaths.Count) paths exceeding 200 characters" warning "Enable LongPathSupport in registry: New-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem' -Name 'LongPathsEnabled' -Value 1 -PropertyType DWORD -Force"
        $Summary.paths.long_paths = $longPaths
    }

    $Summary.paths.long_paths_count = $longPaths.Count
    $Summary.paths.long_paths_enabled = (Get-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem' -Name 'LongPathsEnabled' -ErrorAction SilentlyContinue).LongPathsEnabled -eq 1
}

# ═══════════════════════════════════════════════════════════════════════════
# 4. Port Conflicts
# ═══════════════════════════════════════════════════════════════════════════

function Check-Ports {
    Write-Header "4. Port Conflicts"

    $criticalPorts = @(80, 443, 5432, 6379, 8000, 8001, 8100, 11434)
    $otherPorts = @(3000, 3306, 27017, 9200, 5601, 8080, 8443)

    $occupied = @()

    foreach ($port in $criticalPorts) {
        $conn = netstat -ano | Select-String ":$port\s" | Select-String "LISTEN" | Select-Object -First 1
        if ($conn) {
            $parts = $conn -split '\s+'
            $pid = $parts[-1]
            try {
                $proc = Get-Process -Id $pid -ErrorAction SilentlyContinue
                $name = $proc?.ProcessName ?? "Unknown"
                $occupied += @{ port = $port; pid = $pid; process = $name }
                Add-Issue ports "Port $port in use by $name (PID $pid)" error "Stop the service or configure Corex to use a different port. Fix: net stop $name / taskkill /PID $pid /F"
            } catch {
                Add-Issue ports "Port $port in use (PID $pid)" error "Stop the process using port $port"
            }
        } else {
            Add-Pass ports "Port $port available"
        }
    }

    if (-not $Quick) {
        foreach ($port in $otherPorts) {
            $conn = netstat -ano | Select-String ":$port\s" | Select-String "LISTEN" | Select-Object -First 1
            if ($conn) {
                $parts = $conn -split '\s+'
                $pid = $parts[-1]
                try {
                    $proc = Get-Process -Id $pid -ErrorAction SilentlyContinue
                    $occupied += @{ port = $port; pid = $pid; process = $proc?.ProcessName ?? "Unknown" }
                    $Warnings += "Port $port in use by $($proc?.ProcessName) (PID $pid)"
                    Write-Diag "Port $port in use by $($proc?.ProcessName) (PID $pid)" -Level Warn
                } catch {}
            }
        }
    }

    $Summary.ports.occupied = $occupied
    $Summary.ports.critical_conflicts = ($occupied | Where-Object { $_.port -in $criticalPorts }).Count
}

# ═══════════════════════════════════════════════════════════════════════════
# 5. Environment Variables
# ═══════════════════════════════════════════════════════════════════════════

function Check-Environment {
    Write-Header "5. Environment Variables"

    $requiredVars = @(
        @{ Name = 'COMPUTERNAME'; Purpose = 'Hostname resolution' },
        @{ Name = 'USERPROFILE'; Purpose = 'User home directory' },
        @{ Name = 'LOCALAPPDATA'; Purpose = 'Local app data (logs, cache)' },
        @{ Name = 'APPDATA'; Purpose = 'Roaming app data' },
        @{ Name = 'TMP'; Purpose = 'Temp directory' },
        @{ Name = 'TEMP'; Purpose = 'Temp directory' },
        @{ Name = 'PATH'; Purpose = 'Executable lookup' }
    )

    foreach ($var in $requiredVars) {
        $val = [Environment]::GetEnvironmentVariable($var.Name, 'User')
        $machineVal = [Environment]::GetEnvironmentVariable($var.Name, 'Machine')
        $actual = $val ?? $machineVal
        if ($actual) {
            $length = $var.Name -eq 'PATH' ? $actual.Length : 0
            $Summary.env[$var.Name] = @{ value = if ($var.Name -eq 'PATH') { $actual.Substring(0, [Math]::Min(100, $actual.Length)) + '...' } else { $actual }; source = if ($val) { 'User' } else { 'Machine' } }
            Add-Pass env "$($var.Name) = $(if ($var.Name -eq 'PATH') { 'set (' + $actual.Length + ' chars)' } else { $actual })"
            if ($var.Name -eq 'PATH' -and $actual.Length -gt 4096) {
                Add-Issue env "PATH is $($actual.Length) characters (max 4096)" warning "PATH exceeds recommended length. Remove unused entries."
            }
        } else {
            Add-Issue env "$($var.Name) is not set" error "Required environment variable missing"
        }
    }

    # Check Corex-specific env vars
    $corexVars = @('COREX_DATA_DIR', 'COREX_LOG_DIR', 'AI_GATEWAY_URL', 'JWT_SECRET', 'OLLAMA_BASE_URL')
    $missing = @()
    foreach ($var in $corexVars) {
        $val = [Environment]::GetEnvironmentVariable($var, 'User') ?? [Environment]::GetEnvironmentVariable($var, 'Machine')
        $Summary.env[$var] = $val
        if (-not $val) { $missing += $var }
    }

    if ($missing.Count -gt 0) {
        Add-Issue env "Missing Corex env vars: $($missing -join ', ')" warning "Set these in .env file or system environment variables"
    } else {
        Add-Pass env "All Corex-specific environment variables present"
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 6. Windows Registry
# ═══════════════════════════════════════════════════════════════════════════

function Check-Registry {
    Write-Header "6. Registry Access"

    $regPaths = @(
        'HKLM:\SOFTWARE\Corex',
        'HKLM:\SOFTWARE\Corex\AIGateway',
        'HKCU:\Software\Corex',
        'HKCU:\Software\Corex\AIGateway'
    )

    foreach ($path in $regPaths) {
        try {
            if (Test-Path $path) {
                $props = Get-ItemProperty -Path $path -ErrorAction SilentlyContinue
                Add-Pass registry "Readable: $path"
                $Summary.registry[$path] = 'readable'
            } else {
                if ($path -match '^HKLM') {
                    $Summary.registry[$path] = 'not_found'
                    Write-Diag "Registry path not found: $path" -Level Info
                }
            }
        } catch {
            Add-Issue registry "Cannot read $path : $_" error "Check registry permissions or run as Administrator. Fix: regedit, right-click key → Permissions"
            $Summary.registry[$path] = "error: $_"
        }
    }

    # Check proxy registry keys
    $proxyPath = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Internet Settings'
    try {
        $proxySettings = Get-ItemProperty -Path $proxyPath -Name 'ProxyEnable','ProxyServer','ProxyOverride' -ErrorAction SilentlyContinue
        $Summary.registry.proxy = @{
            enabled = $proxySettings.ProxyEnable -eq 1
            server = $proxySettings.ProxyServer
            bypass = $proxySettings.ProxyOverride
        }
        if ($proxySettings.ProxyEnable -eq 1) {
            Add-Pass registry "System proxy detected: $($proxySettings.ProxyServer)"
        }
    } catch {
        Write-Diag "Could not read proxy settings" -Level Info
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 7. Windows Defender
# ═══════════════════════════════════════════════════════════════════════════

function Check-Defender {
    Write-Header "7. Windows Defender & Antivirus"

    if ($Quick) {
        Write-Diag "Skipped (--Quick mode)" -Level Info
        return
    }

    try {
        $mp = Get-MpPreference -ErrorAction SilentlyContinue
        if (-not $mp) {
            Add-Pass defender "Windows Defender not active (may use third-party AV)"
            return
        }

        $Summary.defender = @{
            realtime_enabled = $mp.RealTimeScanEnabled
            cloud_enabled = $mp.CloudBlockLevel -ne 0
            exclusions_paths = $mp.ExclusionPath
            exclusions_processes = $mp.ExclusionProcess
            paused = $mp.DisableRealtimeMonitoring
        }

        if ($mp.DisableRealtimeMonitoring) {
            Add-Issue defender "Real-time monitoring is disabled" warning "Enable for security: Set-MpPreference -DisableRealtimeMonitoring $false"
        } else {
            Add-Pass defender "Real-time monitoring enabled"
        }

        $exclusions = $mp.ExclusionPath
        $projectAdded = $false
        foreach ($exc in $exclusions) {
            if ($ProjectRoot -like "$exc*") { $projectAdded = $true; break }
        }

        if (-not $projectAdded) {
            Add-Issue defender "Project root not in Defender exclusions" warning "Add exclusion to improve performance: Add-MpPreference -ExclusionPath '$ProjectRoot'"
        } else {
            Add-Pass defender "Project root excluded from real-time scanning"
        }

        if ($mp.ExclusionProcess.Count -gt 0) {
            Add-Pass defender "Process exclusions: $($mp.ExclusionProcess -join ', ')"
        }
    } catch {
        Write-Diag "Could not access Defender settings. May use third-party AV." -Level Info
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 8. Docker
# ═══════════════════════════════════════════════════════════════════════════

function Check-Docker {
    Write-Header "8. Docker"

    try {
        $dockerVersion = & docker --version 2>&1
        $Summary.docker.version = "$dockerVersion"
        Add-Pass docker "Docker installed: $dockerVersion"
    } catch {
        Add-Issue docker "Docker not installed or not in PATH" error "Install Docker Desktop from https://www.docker.com/products/docker-desktop"
        return
    }

    try {
        $composeVersion = & docker compose version 2>&1
        $Summary.docker.compose = "$composeVersion"
        Add-Pass docker "Docker Compose: $composeVersion"
    } catch {
        Add-Issue docker "Docker Compose not installed" error "Docker Compose v2 is bundled with Docker Desktop"
    }

    try {
        $info = & docker info 2>&1
        $Summary.docker.info = if ($info -match 'Server Version:\s+(\S+)') { $matches[1] } else { 'running' }
        Add-Pass docker "Docker daemon running"
    } catch {
        Add-Issue docker "Docker daemon not running" error "Start Docker Desktop from Start Menu or system tray"
    }

    # Docker resource usage
    $wslConfig = Get-Content "$env:USERPROFILE\.wslconfig" -ErrorAction SilentlyContinue
    if ($wslConfig) {
        $Summary.docker.wsl_config = $wslConfig
        if ($wslConfig -match 'memory=(\d+)GB') {
            $mem = [int]$matches[1]
            if ($mem -lt 8) {
                Add-Issue docker "WSL2 memory limited to ${mem}GB" warning "Increase in %USERPROFILE%\.wslconfig: memory=8GB (or more)"
            }
        }
        Add-Pass docker "WSL2 config found: .wslconfig"
    } else {
        Write-Diag "No .wslconfig found (using Docker defaults)" -Level Info
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 9. Network
# ═══════════════════════════════════════════════════════════════════════════

function Check-Network {
    Write-Header "9. Network"

    $hostname = $env:COMPUTERNAME
    try {
        $resolved = [System.Net.Dns]::GetHostEntry($hostname)
        $Summary.network.hostname = $hostname
        $Summary.network.resolved = $resolved.HostName
        $Summary.network.addresses = $resolved.AddressList.IPAddressToString
        Add-Pass network "Hostname $hostname resolves to $($resolved.AddressList.IPAddressToString -join ', ')"
    } catch {
        Add-Issue network "Hostname $hostname does not resolve" warning "Check hosts file: C:\Windows\System32\drivers\etc\hosts"
    }

    # Check hosts file for local entries
    $hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
    if (Test-Path $hostsFile) {
        $hostsContent = Get-Content $hostsFile -ErrorAction SilentlyContinue
        $Summary.network.hosts_entries = @($hostsContent | Where-Object { $_ -match '127\.0\.0\.1' -or $_ -match '::1' })
        $customHosts = @($hostsContent | Where-Object { $_ -notmatch '^\s*#' -and $_ -notmatch '^\s*$' -and $_ -notmatch 'localhost' -and $_ -match '127\.0\.0\.1' })
        foreach ($entry in $customHosts) {
            Add-Pass network "Custom hosts entry: $entry"
        }
    }

    # Test internet connectivity
    try {
        $http = [System.Net.Http.HttpClient]::new()
        $http.Timeout = [TimeSpan]::FromSeconds(5)
        $result = $http.GetAsync('https://api.corex.dev/health').GetAwaiter().GetResult()
        $Summary.network.internet = 'reachable'
        Add-Pass network "Internet reachable (api.corex.dev: $([int]$result.StatusCode))"
    } catch {
        $Summary.network.internet = "unreachable: $_"
        Add-Issue network "Internet not reachable" warning "Check proxy settings or firewall. Corex may work offline with local Ollama."
    }

    # Check DNS
    try {
        $dns = [System.Net.Dns]::GetHostEntry('google.com')
        $Summary.network.dns = 'resolving'
        Add-Pass network "DNS resolving (google.com → $($dns.AddressList.IPAddressToString -join ', '))"
    } catch {
        $Summary.network.dns = "failed: $_"
        Add-Issue network "DNS not resolving" error "Check DNS settings: ipconfig /flushdns; netsh int ip reset"
    }

    # Check proxy
    $proxy = [System.Net.WebRequest]::GetSystemWebProxy()
    $proxyAddr = $proxy.GetProxy('http://api.corex.dev')
    if ($proxyAddr -ne 'http://api.corex.dev') {
        $Summary.network.proxy = $proxyAddr
        Add-Pass network "System proxy: $proxyAddr"
    }

    # Firewall status
    try {
        $fw = Get-NetFirewallProfile -ErrorAction SilentlyContinue
        foreach ($profile in $fw) {
            $Summary.network.firewall[$profile.Name] = $profile.Enabled
            $status = if ($profile.Enabled) { 'enabled' } else { 'disabled' }
            Add-Pass network "Firewall ($($profile.Name)): $status"
        }
    } catch {
        Write-Diag "Could not check firewall status" -Level Info
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 10. Services
# ═══════════════════════════════════════════════════════════════════════════

function Check-Services {
    Write-Header "10. Windows Services"

    $criticalServices = @(
        @{ Name = 'Docker Desktop Service' },
        @{ Name = 'com.docker.service' },
        @{ Name = 'Redis' },
        @{ Name = 'CorexRedis' },
        @{ Name = 'CorexPHP' },
        @{ Name = 'CorexNginx' },
        @{ Name = 'CorexAIGateway' },
        @{ Name = 'CorexServiceHost' }
    )

    foreach ($svc in $criticalServices) {
        try {
            $service = Get-Service -Name $svc.Name -ErrorAction SilentlyContinue
            if ($service) {
                $status = $service.Status.ToString()
                $startType = $service.StartType.ToString()
                $Summary.services[$svc.Name] = @{ status = $status; start_type = $startType }
                if ($status -eq 'Running') {
                    Add-Pass services "$($svc.Name): $status ($startType)"
                } else {
                    Add-Issue services "$($svc.Name): $status ($startType)" warning "Start service: Start-Service -Name '$($svc.Name)'"
                }
            }
        } catch { }
    }

    # Check for services with bad states
    $errors = Get-EventLog -LogName System -Source 'Service Control Manager' -EntryType Error -Newest 10 -ErrorAction SilentlyContinue
    if ($errors) {
        $Summary.services.recent_errors = @($errors | Select-Object TimeGenerated, Message)
        $svcErrors = $errors | Where-Object { $_.Message -match 'Corex' -or $_.Message -match 'service' }
        if ($svcErrors) {
            Add-Issue services "$($svcErrors.Count) recent Service Control Manager errors" error "Check Event Viewer → Windows Logs → System for details"
        }
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 11. Event Log Health
# ═══════════════════════════════════════════════════════════════════════════

function Check-EventLog {
    Write-Header "11. Event Log Health"

    $logNames = @('Application', 'System', 'Corex' )
    foreach ($name in $logNames) {
        try {
            $log = Get-WmiObject Win32_NTEventlogFile | Where-Object { $_.LogFileName -eq $name }
            if ($log) {
                $recordCount = $log.NumberOfRecords
                $sizeMB = [math]::Round($log.FileSize / 1MB, 1)
                $maxSizeMB = [math]::Round($log.MaxFileSize / 1MB, 1)
                Add-Pass eventlog "$name: $recordCount records, ${sizeMB}MB / ${maxSizeMB}MB"
                if ($sizeMB / $maxSizeMB -gt 0.9) {
                    Add-Issue eventlog "$name log is $([math]::Round($sizeMB/$maxSizeMB*100))% full" warning "Clear log: Clear-EventLog -LogName $name"
                }
            }
        } catch { }
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 12. Performance
# ═══════════════════════════════════════════════════════════════════════════

function Check-Performance {
    Write-Header "12. Performance"

    # CPU
    $cpuLoad = (Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average
    $Summary.performance.cpu_percent = $cpuLoad
    if ($cpuLoad -gt 80) {
        Add-Issue performance "CPU usage at $cpuLoad%" warning "Check Task Manager for resource-heavy processes"
    } else {
        Add-Pass performance "CPU: ${cpuLoad}%"
    }

    # Memory
    $os = Get-CimInstance Win32_OperatingSystem
    $availGB = [math]::Round($os.FreePhysicalMemory / 1MB, 1)
    $totalGB = [math]::Round($os.TotalVisibleMemorySize / 1MB, 1)
    $usedPercent = [math]::Round(($totalGB - $availGB) / $totalGB * 100, 1)
    $Summary.performance.memory = @{ available_gb = $availGB; total_gb = $totalGB; used_percent = $usedPercent }
    if ($usedPercent -gt 90) {
        Add-Issue performance "Memory usage at ${usedPercent}% (${availGB}GB free / ${totalGB}GB total)" error "Close unused applications or increase RAM. Check for memory leaks."
    } elseif ($usedPercent -gt 80) {
        Add-Issue performance "Memory usage at ${usedPercent}% (${availGB}GB free / ${totalGB}GB total)" warning "Consider closing unused applications"
    } else {
        Add-Pass performance "Memory: ${usedPercent}% used (${availGB}GB free / ${totalGB}GB total)"
    }

    # Disk I/O
    $disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"
    $diskFreeGB = [math]::Round($disk.FreeSpace / 1GB, 1)
    $diskTotalGB = [math]::Round($disk.Size / 1GB, 1)
    $diskUsedPercent = [math]::Round(($diskTotalGB - $diskFreeGB) / $diskTotalGB * 100, 1)
    $Summary.performance.disk = @{ free_gb = $diskFreeGB; total_gb = $diskTotalGB; used_percent = $diskUsedPercent }
    if ($diskFreeGB -lt 5) {
        Add-Issue performance "Less than 5 GB free on C:\ (${diskUsedPercent}% used)" error "Free up disk space immediately"
    }

    # Page file
    $pageFile = Get-CimInstance Win32_PageFileUsage
    if ($pageFile) {
        $pageGB = [math]::Round($pageFile.AllocatedBaseSize / 1MB, 1)
        $Summary.performance.page_file_gb = $pageGB
        $recommended = [math]::Max(2, $totalGB * 1.5)
        if ($pageGB -lt $recommended) {
            Add-Issue performance "Page file is ${pageGB}GB, recommended ${recommended}GB" warning "Increase virtual memory: System Properties → Advanced → Performance → Virtual memory"
        }
        Add-Pass performance "Page file: ${pageGB}GB (recommended: ${recommended}GB)"
    }

    # Handle count
    $handles = (Get-Process | Measure-Object -Property HandleCount -Sum).Sum
    $Summary.performance.total_handles = $handles
    if ($handles -gt 100000) {
        Add-Issue performance "System handle count: $handles (high)" warning "May indicate handle leak. Restart explorer.exe or system"
    } else {
        Add-Pass performance "Handles: $handles"
    }

    # Thread count
    $threads = (Get-Process | Measure-Object -Property Threads -Sum).Sum
    $Summary.performance.total_threads = $threads
    if ($threads -gt 5000) {
        Add-Issue performance "System thread count: $threads (high)" warning "High thread count may degrade performance"
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 13. Report
# ═══════════════════════════════════════════════════════════════════════════

function Write-Report {
    Write-Header "SUMMARY"

    $errorCount = ($Issues | Where-Object { $_.severity -eq 'error' }).Count
    $warningCount = ($Issues | Where-Object { $_.severity -eq 'warning' }).Count
    $passCount = $Passed.Count

    Write-Host ""
    Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║        Corex Windows Diagnostic Report     ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""

    $status = if ($errorCount -eq 0) { "PASS" } elseif ($errorCount -le 3) { "WARNINGS" } else { "ISSUES FOUND" }
    $color = if ($errorCount -eq 0) { 'Green' } elseif ($errorCount -le 3) { 'Yellow' } else { 'Red' }
    Write-Host " Overall Status: $status" -ForegroundColor $color
    Write-Host ""

    Write-Host " ✓ Passed : $passCount" -ForegroundColor Green
    Write-Host " ⚠ Warnings: $warningCount" -ForegroundColor Yellow
    Write-Host " ✗ Errors : $errorCount" -ForegroundColor Red
    Write-Host ""

    if ($Issues.Count -gt 0) {
        Write-Host " Issues:" -ForegroundColor Yellow
        $Issues | ForEach-Object {
            $icon = if ($_.severity -eq 'error') { '✗' } else { '⚠' }
            $c = if ($_.severity -eq 'error') { 'Red' } else { 'Yellow' }
            Write-Host "  $icon [$($_.category)] $($_.message)" -ForegroundColor $c
            if ($_.fix) {
                Write-Host "     Fix: $($_.fix)" -ForegroundColor Gray
            }
        }
        Write-Host ""

        # Write fixes to a separate file
        $fixFile = Join-Path $LogDir 'fixes.ps1'
@"
# Corex Windows Diagnostic Fixes
# Generated: $(Get-Date -Format o)
# Run this script with: powershell -ExecutionPolicy Bypass -File fixes.ps1

`$ErrorActionPreference = 'Stop'

Write-Host 'Applying Corex diagnostic fixes...' -ForegroundColor Cyan

"@ | Out-File -FilePath $fixFile -Encoding utf8

        $Issues | Where-Object { $_.fix } | ForEach-Object {
            "# Fix for: $($_.message)`n# $($_.fix)`n" | Out-File -FilePath $fixFile -Encoding utf8 -Append
        }
        "#`nWrite-Host 'Done.' -ForegroundColor Green" | Out-File -FilePath $fixFile -Encoding utf8 -Append

        Write-Host " Generated fix script: $fixFile" -ForegroundColor Gray
    }

    Write-Host " Full report: $ReportFile" -ForegroundColor Gray
    Write-Host " JSON data : $JsonReport" -ForegroundColor Gray
}

# ═══════════════════════════════════════════════════════════════════════════
# Main
# ═══════════════════════════════════════════════════════════════════════════

try {
    # Create output dir
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null

    Write-Host "Corex Windows Diagnostic Suite" -ForegroundColor Cyan
    Write-Host "Project root: $ProjectRoot" -ForegroundColor Gray
    Write-Host "Output: $LogDir" -ForegroundColor Gray
    Write-Host "Quick mode: $([bool]$Quick)" -ForegroundColor Gray
    Write-Host ""

    Check-OS
    Check-UAC
    Check-Paths
    Check-Ports
    Check-Environment
    Check-Registry
    Check-Defender
    Check-Docker
    Check-Network
    Check-Services
    Check-EventLog
    Check-Performance

    # ── Write reports ───────────────────────────────────────────────
    $FullReport = @{
        summary = $Summary
        issues = $Issues
        warnings = $Warnings
        passed = $Passed
        counts = @{
            errors = ($Issues | Where-Object { $_.severity -eq 'error' }).Count
            warnings = ($Issues | Where-Object { $_.severity -eq 'warning' }).Count
            passed = $Passed.Count
        }
    }

    $FullReport | ConvertTo-Json -Depth 10 | Out-File -FilePath $JsonReport -Encoding utf8
    Write-Report

    # ── Flat text report ────────────────────────────────────────────
    $ReportLines = @(
        "Corex Windows Diagnostic Report",
        "=" * 60,
        "Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
        "Host: $env:COMPUTERNAME",
        "Project: $ProjectRoot",
        "",
        "Results: $($FullReport.counts.errors) errors, $($FullReport.counts.warnings) warnings, $($FullReport.counts.passed) passed",
        "",
        "=" * 60,
        "Issues:",
        "-" * 40
    )
    foreach ($issue in $Issues) {
        $ReportLines += "[$($issue.severity)] [$($issue.category)] $($issue.message)"
        if ($issue.fix) { $ReportLines += "  Fix: $($issue.fix)" }
    }
    $ReportLines | Out-File -FilePath $ReportFile -Encoding utf8

    exit ($FullReport.counts.errors -gt 0 ? 1 : 0)

} catch {
    Write-Host "FATAL: $_" -ForegroundColor Red
    "FATAL: $_" | Out-File -FilePath (Join-Path $LogDir 'fatal-error.txt') -Encoding utf8
    exit 2
}
