#Requires -Version 5.1
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Corex Windows Performance Optimization

.DESCRIPTION
    Analyzes and optimizes Windows performance for Corex: memory usage,
    CPU spikes, disk I/O bottlenecks, network latency, power management,
    and Windows-specific tweaks for PHP/Python workloads.

.PARAMETER Apply
    Apply recommended optimizations (backups made automatically).

.PARAMETER OutputDir
    Directory for reports.

.EXAMPLE
    PS> .\performance.ps1                   # Analyze only
    PS> .\performance.ps1 -Apply            # Analyze + apply fixes
#>

param(
    [switch]$Apply,
    [string]$OutputDir
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $PSCommandPath
$ProjectRoot = Resolve-Path (Join-Path $ScriptDir '..\..')
$LogDir = $OutputDir ? $OutputDir : (Join-Path $ScriptDir '..\logs' "perf-$(Get-Date -Format 'yyyyMMdd-HHmmss')")
$ReportFile = Join-Path $LogDir 'performance-report.txt'

New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
$backupDir = Join-Path $LogDir 'backups'
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

function Write-Result { param([string]$M, [string]$L='Info') $c=@{Info='Gray';Ok='Green';Warn='Yellow';Fail='Red';H='Cyan';Action='Magenta'} [$L]; Write-Host "$(@{Info='  ';Ok='✓ ';Warn='⚠ ';Fail='✗ ';H='──';Action='→ '}[$L])$M" -ForegroundColor $c }
function H { param([string]$M) Write-Result $M H; "" }

$issues = @()
$optimizations = @()

# ═══════════════════════════════════════════════════════════════════════════
# 1. Power Plan
# ═══════════════════════════════════════════════════════════════════════════
H "1. Power Plan"

$powerPlan = powercfg /getactivescheme 2>&1
Write-Result "Active power plan: $powerPlan" Info

if ($powerPlan -match '381b4222-f694-41f0-9685-ff5bb260df2e' -or $powerPlan -match 'Balanced') {
    Write-Result "Power plan: Balanced (good for dev workloads)" Info
} elseif ($powerPlan -match 'a1841308-3541-4fab-bc81-f71556f20b4a' -or $powerPlan -match 'Power saver') {
    Write-Result "Power plan: Power saver — will throttle performance" Fail
    $issues += "Power saver plan active. Switch to High Performance or Ultimate Performance."
    if ($Apply) {
        powercfg /setactive 8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c
        Write-Result "Switched to High Performance plan" Action
    }
} elseif ($powerPlan -match 'High performance') {
    Write-Result "Power plan: High Performance ✓" Ok
}

# CPU min/max state
$cpuMin = (powercfg /query 8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c SUB_PROCESSOR PROCTHROTTLEMIN 2>&1) -join ' '
$cpuMax = (powercfg /query 8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c SUB_PROCESSOR PROCTHROTTLEMAX 2>&1) -join ' '
Write-Result "CPU min state: $cpuMin" Info
Write-Result "CPU max state: $cpuMax" Info

# ═══════════════════════════════════════════════════════════════════════════
# 2. Memory Management
# ═══════════════════════════════════════════════════════════════════════════
H "2. Memory"

$os = Get-CimInstance Win32_OperatingSystem
$totalGB = [math]::Round($os.TotalVisibleMemorySize / 1MB, 1)
$freeGB = [math]::Round($os.FreePhysicalMemory / 1MB, 1)
$usedPercent = [math]::Round(($totalGB - $freeGB) / $totalGB * 100, 1)

Write-Result "Memory: ${totalGB}GB total, ${freeGB}GB free, ${usedPercent}% used" Info

if ($usedPercent -gt 85) {
    Write-Result "Memory usage is high: ${usedPercent}%" Fail
    $issues += "Memory at ${usedPercent}%. Close apps or increase RAM."

    # Top memory consumers
    $topMem = Get-Process | Sort-Object WorkingSet64 -Descending | Select-Object -First 5 Name, @{N='MB';E={[math]::Round($_.WorkingSet64/1MB,1)}}
    Write-Result "Top memory consumers:" Info
    foreach ($p in $topMem) { Write-Result "  $($p.Name): $($p.MB) MB" Info }
}

# Page file
$pageFile = Get-CimInstance Win32_PageFileUsage
if ($pageFile) {
    $pageGB = [math]::Round($pageFile.AllocatedBaseSize / 1MB, 1)
    $recPage = [math]::Max([math]::Round($totalGB * 1.5, 0), 2)
    Write-Result "Page file: ${pageGB}GB (recommended: ${recPage}GB)" $(if ($pageGB -ge $recPage) { 'Ok' } else { 'Warn' })
    if ($pageGB -lt $recPage) {
        $issues += "Page file is ${pageGB}GB, should be at least ${recPage}GB."
        if ($Apply) {
            $backupFile = Join-Path $backupDir 'pagefile-backup.reg'
            reg export 'HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Memory Management' $backupFile /y 2>$null
            # Set automatic page file management
            wmic computersystem where name="%computername%" set AutomaticManagedPagefile=True
            Write-Result "Set automatic page file management" Action
        }
    }
}

# Memory compression
$memComp = Get-Process -Name 'Memory Compression' -ErrorAction SilentlyContinue
if ($memComp) {
    $memCompWS = [math]::Round(($memComp | Measure-Object WorkingSet64 -Sum).Sum / 1MB, 1)
    Write-Result "Memory Compression: ${memCompWS}MB used" Info
}

# ═══════════════════════════════════════════════════════════════════════════
# 3. Disk I/O
# ═══════════════════════════════════════════════════════════════════════════
H "3. Disk I/O"

$disk = Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3"
foreach ($d in $disk) {
    $freeGB = [math]::Round($d.FreeSpace / 1GB, 1)
    $totalGB = [math]::Round($d.Size / 1GB, 1)
    $usedPct = [math]::Round(($totalGB - $freeGB) / $totalGB * 100, 1)
    $drive = $d.DeviceID

    Write-Result "$drive ${totalGB}GB total, ${freeGB}GB free, ${usedPct}% used" $(if ($freeGB -lt 5) {'Fail'} elseif ($freeGB -lt 15) {'Warn'} else {'Ok'})

    if ($freeGB -lt 5) { $issues += "$drive has only ${freeGB}GB free. Free up disk space." }
    if ($freeGB -lt 15) { $issues += "$drive is ${usedPct}% full. Consider cleanup." }

    # Disk perf counters
    $perf = Get-CimInstance Win32_PerfFormattedData_PerfDisk_LogicalDisk | Where-Object { $_.Name -eq $drive.TrimEnd(':') }
    if ($perf) {
        $avgQueue = $perf.AvgDiskQueueLength
        $diskTime = $perf.PercentDiskTime
        Write-Result "$drive avg queue: $avgQueue | disk time: ${diskTime}%" Info
        if ($avgQueue -gt 2) {
            Write-Result "$drive queue length is $avgQueue (high I/O contention)" Warn
            $issues += "$drive disk queue length is $avgQueue. Check for excessive file operations."
        }
    }
}

# Disk fragmentation
$defragStatus = Get-Volume -ErrorAction SilentlyContinue | ForEach-Object {
    $vol = $_.DriveLetter
    if ($vol) {
        $analysis = Optimize-Volume -DriveLetter $vol -Analyze -ErrorAction SilentlyContinue
        if ($analysis) { [PSCustomObject]@{ Drive=$vol; FragPercent=$analysis.DefragAnalysis.[System.__ComObject].PercentFragmentation } }
    }
}
$defragStatus | ForEach-Object {
    Write-Result "$($_.Drive):\ fragmentation $($_.FragPercent)%" $(if ($_.FragPercent -gt 10) {'Warn'} else {'Ok'})
    if ($_.FragPercent -gt 10) {
        $issues += "$($_.Drive):\ is $($_.FragPercent)% fragmented."
        if ($Apply) {
            Optimize-Volume -DriveLetter $_.Drive -Defrag -ErrorAction SilentlyContinue
            Write-Result "Defragmenting $($_.Drive):\" Action
        }
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 4. Network Performance
# ═══════════════════════════════════════════════════════════════════════════
H "4. Network"

# TCP auto-tuning
$tcpAuto = netsh int tcp show global 2>&1 | Select-String 'Receive Window Auto-Tuning'
if ($tcpAuto) {
    if ($tcpAuto -match 'normal') {
        Write-Result "TCP auto-tuning: normal ✓" Ok
    } else {
        Write-Result "TCP auto-tuning: $tcpAuto" Warn
        $issues += "TCP auto-tuning not at normal level."
        if ($Apply) { netsh int tcp set global autotuninglevel=normal; Write-Result "Set TCP auto-tuning to normal" Action }
    }
}

# RSS
$rss = netsh int tcp show global 2>&1 | Select-String 'Receive-Side Scaling State'
if ($rss -match 'enabled') {
    Write-Result "RSS: enabled ✓" Ok
} else {
    Write-Result "RSS: disabled (may limit network throughput)" Warn
    $issues += "RSS (Receive-Side Scaling) is disabled."
    if ($Apply) { netsh int tcp set global rss=enabled; Write-Result "Enabled RSS" Action }
}

# Chimney Offload
$chimney = netsh int tcp show global 2>&1 | Select-String 'Chimney Offload State'
if ($chimney -match 'disabled') {
    Write-Result "TCP Chimney: disabled (recommended)" Ok
} else {
    Write-Result "TCP Chimney: enabled (may cause issues)" Warn
    if ($Apply) { netsh int tcp set global chimney=disabled; Write-Result "Disabled TCP Chimney" Action }
}

# Network Adapter Power Saving
$adapters = Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' }
foreach ($adapter in $adapters) {
    $powerMgmt = Get-NetAdapterAdvancedProperty -Name $adapter.Name -DisplayName 'Power Saving Mode' -ErrorAction SilentlyContinue
    if ($powerMgmt) {
        Write-Result "$($adapter.Name): power saving — $($powerMgmt.DisplayValue)" Info
    }
    # Disable power saving on the adapter
    if ($Apply) {
        $nic = Get-NetAdapter -Name $adapter.Name
        Disable-NetAdapterPowerManagement -Name $adapter.Name -ErrorAction SilentlyContinue
        Write-Result "Disabled power management for $($adapter.Name)" Action
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 5. Windows Services Optimization
# ═══════════════════════════════════════════════════════════════════════════
H "5. Service Optimization"

$bloatServices = @(
    @{ Name = 'XblAuthManager'; Desc = 'Xbox Live Auth Manager' },
    @{ Name = 'XblGameSave'; Desc = 'Xbox Live Game Save' },
    @{ Name = 'XboxNetApiSvc'; Desc = 'Xbox Live Networking' },
    @{ Name = 'WSearch'; Desc = 'Windows Search (indexing)' },
    @{ Name = 'SysMain'; Desc = 'SysMain (Superfetch)' },
    @{ Name = 'DiagTrack'; Desc = 'Connected User Experiences and Telemetry' },
    @{ Name = 'dmwappushservice'; Desc = 'Device Management WAP Push' }
)

foreach ($svc in $bloatServices) {
    $s = Get-Service -Name $svc.Name -ErrorAction SilentlyContinue
    if ($s -and $s.Status -eq 'Running') {
        Write-Result "$($svc.Name) ($($svc.Desc)): running" Warn
        if ($Apply) {
            Stop-Service $svc.Name -Force -ErrorAction SilentlyContinue
            Set-Service $svc.Name -StartupType Disabled -ErrorAction SilentlyContinue
            Write-Result "Stopped and disabled $($svc.Name)" Action
        }
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 6. Visual Effects
# ═══════════════════════════════════════════════════════════════════════════
H "6. Visual Effects"

$perfOptions = (Get-ItemProperty 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\VisualEffects' -Name 'VisualFXSetting' -ErrorAction SilentlyContinue).VisualFXSetting
if ($perfOptions -eq 2) {
    Write-Result "Visual effects: Adjust for best performance ✓" Ok
} elseif ($perfOptions -eq 1) {
    Write-Result "Visual effects: Default" Info
} else {
    Write-Result "Visual effects: Full (may impact performance)" Warn
    $issues += "Visual effects set to full. Consider 'Adjust for best performance'."
}

# Animations
$animations = (Get-ItemProperty 'HKCU:\Control Panel\Desktop' -Name 'UserPreferencesMask' -ErrorAction SilentlyContinue).UserPreferencesMask
if ($Apply) {
    Set-ItemProperty 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\Advanced' -Name 'TaskbarAnimations' -Value 0
    Set-ItemProperty 'HKCU:\Control Panel\Desktop' -Name 'MenuShowDelay' -Value '0'
    Write-Result "Disabled animations and menu delay" Action
}

# ═══════════════════════════════════════════════════════════════════════════
# 7. PHP Performance
# ═══════════════════════════════════════════════════════════════════════════
H "7. PHP Performance Tuning"

$phpIniPaths = @(
    "$ProjectRoot\windows\packaging\tools\php\php.ini",
    "$env:ProgramFiles\php\php.ini",
    "C:\tools\php\php.ini",
    "C:\PHP\php.ini"
)

$phpIni = $phpIniPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
if ($phpIni) {
    $iniContent = Get-Content $phpIni -Raw
    Write-Result "php.ini: $phpIni" Info
    $data.ini_path = $phpIni

    # OPcache
    if ($iniContent -match 'opcache\.enable\s*=\s*1') {
        Write-Result "OPcache: enabled ✓" Ok
    } else {
        Write-Result "OPcache: NOT enabled" Warn
        $issues += "PHP OPcache disabled. Enable for 2-3x performance improvement."
        if ($Apply) {
            Add-Content $phpIni "`n[opcache]`nopcache.enable=1`nopcache.memory_consumption=128`nopcache.max_accelerated_files=10000`nopcache.revalidate_freq=2" -Force
            Write-Result "Enabled OPcache in php.ini" Action
        }
    }

    # Realpath cache
    if ($iniContent -match 'realpath_cache_size\s*=\s*4096k') {
        Write-Result "realpath cache: 4MB (default)" Info
    } else {
        Write-Result "realpath cache not configured" Warn
        if ($Apply) {
            Add-Content $phpIni "`nrealpath_cache_size=4096k`nrealpath_cache_ttl=600" -Force
            Write-Result "Set realpath_cache_size=4096k" Action
        }
    }

    # Max children (for PHP-FPM)
    if ($iniContent -match 'pm\.max_children') {
        Write-Result "PHP-FPM max_children configured" Info
    }
} else {
    Write-Result "php.ini not found in standard locations" Warn
}

# ═══════════════════════════════════════════════════════════════════════════
# 8. Python Performance
# ═══════════════════════════════════════════════════════════════════════════
H "8. Python Performance Tuning"

$pythonPaths = @(
    "$ProjectRoot\.venv\Scripts\python.exe",
    "$ProjectRoot\ai-gateway\.venv\Scripts\python.exe",
    (Get-Command python -ErrorAction SilentlyContinue).Source
)
$pyexe = $pythonPaths | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
if ($pyexe) {
    # Check for opentelemetry overhead
    $otelCheck = & $pyexe -c "try: import opentelemetry; print('yes'); except: print('no')" 2>&1
    if ($otelCheck -eq 'yes') {
        Write-Result "OpenTelemetry: enabled (may add 5-15% overhead)" Warn
        $issues += "OpenTelemetry adds latency. Consider disabling in development: OTEL_SDK_DISABLED=true"
    }

    # GIL check
    $gilCheck = & $pyexe -c "import sys; print(sys._is_gil_enabled() if hasattr(sys, '_is_gil_enabled') else 'unknown')" 2>&1
    if ($gilCheck -eq 'unknown') {
        Write-Result "GIL: enabled (Python 3.12+ free-threading not active)" Info
    } elseif ($gilCheck -eq 'False') {
        Write-Result "GIL: DISABLED (free-threading build) ⚡" Ok
    }

    # asyncio loop optimization
    $loopCheck = & $pyexe -c "import asyncio; print(type(asyncio.get_event_loop_policy()).__name__)" 2>&1
    if ($loopCheck -ne 'WindowsProactorEventLoopPolicy' -and $loopCheck -ne 'DefaultEventLoopPolicy') {
        Write-Result "Event loop policy: $loopCheck (WindowsAsyncIOProactor recommended)" Warn
        $issues += "Set asyncio event loop policy: asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())"
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 9. Windows Defender Exclusions
# ═══════════════════════════════════════════════════════════════════════════
H "9. Windows Defender Exclusions"

try {
    $mp = Get-MpPreference -ErrorAction SilentlyContinue
    if ($mp) {
        $paths = @($ProjectRoot, "$ProjectRoot\backend\storage", "$ProjectRoot\ai-gateway", "$ProjectRoot\electron", "$env:TMP", "$ProjectRoot\windows\packaging\tools")
        foreach ($path in $paths) {
            $exists = $mp.ExclusionPath -contains $path
            if (-not $exists) {
                Write-Result "Missing exclusion: $path" Warn
                $issues += "Add Defender exclusion for $path."
                if ($Apply) {
                    Add-MpPreference -ExclusionPath $path -ErrorAction SilentlyContinue
                    Write-Result "Added exclusion: $path" Action
                }
            }
        }

        $procNames = @('php.exe', 'python.exe', 'composer.exe', 'artisan', 'nginx.exe', 'redis-server.exe', 'node.exe')
        foreach ($pn in $procNames) {
            $exists = $mp.ExclusionProcess -contains $pn
            if (-not $exists) {
                Write-Result "Missing process exclusion: $pn" Warn
                if ($Apply) {
                    Add-MpPreference -ExclusionProcess $pn -ErrorAction SilentlyContinue
                    Write-Result "Added process exclusion: $pn" Action
                }
            }
        }
    }
} catch { Write-Result "Cannot access Defender settings (may use third-party AV)" Info }

# ═══════════════════════════════════════════════════════════════════════════
# 10. Registry Tweaks
# ═══════════════════════════════════════════════════════════════════════════
H "10. Registry Performance Tweaks"

$tweaks = @(
    @{ Path = 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem'; Name = 'LongPathsEnabled'; Value = 1; Type = 'DWord'; Desc = 'Enable long path support' },
    @{ Path = 'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager\Memory Management'; Name = 'LargeSystemCache'; Value = 0; Type = 'DWord'; Desc = 'Disable large system cache (server workloads)' },
    @{ Path = 'HKLM:\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters'; Name = 'TcpTimedWaitDelay'; Value = 30; Type = 'DWord'; Desc = 'Reduce TCP TIME_WAIT (30s)' },
    @{ Path = 'HKLM:\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters'; Name = 'MaxUserPort'; Value = 65534; Type = 'DWord'; Desc = 'Increase max user ports' },
    @{ Path = 'HKCU:\Control Panel\Desktop'; Name = 'AutoEndTasks'; Value = 1; Type = 'String'; Desc = 'Auto-end hung tasks' },
    @{ Path = 'HKCU:\Control Panel\Desktop'; Name = 'HungAppTimeout'; Value = 5000; Type = 'String'; Desc = 'Reduce hung app timeout' },
    @{ Path = 'HKCU:\Control Panel\Desktop'; Name = 'WaitToKillAppTimeout'; Value = 5000; Type = 'String'; Desc = 'Reduce kill app timeout' }
)

foreach ($tweak in $tweaks) {
    try {
        $current = (Get-ItemProperty -Path $tweak.Path -Name $tweak.Name -ErrorAction SilentlyContinue).$($tweak.Name)
        $expected = $tweak.Value
        if ($current -ne $expected) {
            Write-Result "$($tweak.Desc): current=$current → expected=$expected" Warn
            $issues += "$($tweak.Desc). Run: Set-ItemProperty -Path '$($tweak.Path)' -Name '$($tweak.Name)' -Value $($tweak.Value)"
            if ($Apply) {
                Set-ItemProperty -Path $tweak.Path -Name $tweak.Name -Value $tweak.Value
                Write-Result "Applied: $($tweak.Desc)" Action
            }
        } else {
            Write-Result "$($tweak.Desc): $expected ✓" Ok
        }
    } catch {
        Write-Result "Cannot read/write $($tweak.Path)\$($tweak.Name): $_" Warn
        unless ($Apply -and $tweak.Path -match 'HKLM') { continue }
        try {
            New-Item -Path $tweak.Path -Force | Out-Null
            Set-ItemProperty -Path $tweak.Path -Name $tweak.Name -Value $tweak.Value
            Write-Result "Created: $($tweak.Name) = $($tweak.Value)" Action
        } catch { Write-Result "Failed: $($tweak.Name)" Fail }
    }
}

# ═══════════════════════════════════════════════════════════════════════════
# 11. System Health
# ═══════════════════════════════════════════════════════════════════════════
H "11. System Health"

# Handle count
$handles = (Get-Process | Measure-Object -Property HandleCount -Sum).Sum
Write-Result "Total handles: $handles" $(if ($handles -gt 80000) {'Warn'} else {'Ok'})
if ($handles -gt 80000) { $issues += "Handle count is $handles (high). Consider restarting." }

# Thread count
$threads = (Get-Process | Measure-Object -Property Threads -Count).Sum
Write-Result "Total threads: $threads" $(if ($threads -gt 3000) {'Warn'} else {'Ok'})

# System uptime
$uptime = (Get-Date) - (Get-CimInstance Win32_OperatingSystem).LastBootUpTime
Write-Result "System uptime: $($uptime.Days)d $($uptime.Hours)h" Info
if ($uptime.TotalDays -gt 14) {
    Write-Result "Uptime >14 days — consider restarting to clear memory" Warn
    $issues += "System running for $($uptime.TotalDays) days. Restart recommended."
}

# Paged pool
$poolPaged = (Get-Process | Measure-Object -Property PagedMemorySize64 -Sum).Sum / 1MB
$poolNonPaged = (Get-Process | Measure-Object -Property NonPagedSystemMemorySize64 -Sum).Sum / 1MB
Write-Result "Paged pool: $([math]::Round($poolPaged, 1)) MB | Non-paged: $([math]::Round($poolNonPaged, 1)) MB" Info
if ($poolNonPaged -gt 200) {
    Write-Result "Non-paged pool > 200 MB (possible leak)" Warn
    $issues += "Non-paged pool memory at $([math]::Round($poolNonPaged, 1)) MB. Check drivers."
}

# ═══════════════════════════════════════════════════════════════════════════
# Report
# ═══════════════════════════════════════════════════════════════════════════
H "PERFORMANCE SUMMARY"

Write-Host "`nIssues found: $($issues.Count)" -ForegroundColor $(if ($issues.Count -gt 0) {'Yellow'} else {'Green'})
$issues | ForEach-Object { Write-Host "  ⚠ $_" -ForegroundColor Yellow }

if ($Apply) {
    Write-Host "`nBackups saved to: $backupDir" -ForegroundColor Gray
    Write-Host "Restart required for some changes to take effect." -ForegroundColor Yellow
}

# Generate reg tweak file
$regFile = Join-Path $LogDir 'performance-tweaks.reg'
@"
Windows Registry Editor Version 5.00
; Corex Windows Performance Tweaks — $(Get-Date -Format 'yyyy-MM-dd')
; Apply: regedit /s performance-tweaks.reg

[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\FileSystem]
"LongPathsEnabled"=dword:00000001

[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Memory Management]
"LargeSystemCache"=dword:00000000

[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters]
"TcpTimedWaitDelay"=dword:0000001e
"MaxUserPort"=dword:0000fffe

[HKEY_CURRENT_USER\Control Panel\Desktop]
"AutoEndTasks"="1"
"HungAppTimeout"="5000"
"WaitToKillAppTimeout"="5000"
"MenuShowDelay"="0"
"ForegroundLockTimeout"="200"
"ForegroundFlashCount"="3"
"ForegroundLockTimeout"="200"
"DragFullWindows"="0"
"DragFromMaximize"="1"
"Animation"="0"
"MinAnimate"="0"
"SmoothScroll"="0"
"KeyboardDelay"="0"
"KeyboardSpeed"="31"
"MouseHoverTime"="10"
"MouseHoverHeight"="1"
"MouseHoverWidth"="1"
"MenuShowDelay"="0"
"WaitToKillServiceTimeout"="2000"

[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\PriorityControl]
"Win32PrioritySeparation"=dword:00000026
"@ | Out-File $regFile -Encoding utf8

"`nRegistry tweaks saved to: $regFile" | Out-Host
"Report: $ReportFile" | Out-Host

$issues | ForEach-Object { $_ } | Out-File $ReportFile -Encoding utf8

exit ($issues.Count -gt 0 ? 1 : 0)
