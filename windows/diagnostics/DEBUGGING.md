# Corex Windows Debugging Guide

Comprehensive guide for diagnosing and fixing Windows-specific issues in the
Corex Laravel/Python desktop application.

**Quick links:**
- [Run full diagnostic](#running-diagnostics)
- [Path length issues](#1-path-length-issues-max_path)
- [PHP problems](#3-php-specific-issues)
- [Python problems](#4-python-specific-issues)
- [Performance optimization](#5-performance-optimization)

---

## Quick Diagnostic Commands

```powershell
# Run ALL diagnostics (as Administrator):
cd windows\diagnostics
.\diagnose.ps1                          # Full system check
.\php-debug.ps1                         # PHP-specific
.\python-debug.ps1                      # Python-specific
.\performance.ps1                       # Performance analysis
.\analyze-logs.ps1 -Hours 24            # Error log analysis

# Apply optimizations:
.\performance.ps1 -Apply                # Analyze + apply fixes

# Quick checks:
netstat -ano | findstr ":8000 :8001 :6379 :5432"  # Port conflicts
php -i | findstr "memory_limit"                    # PHP memory
python -c "import sys; print(sys.version)"         # Python version
```

---

## Running Diagnostics

```powershell
# As Administrator, run:
cd windows\diagnostics

# 1. System diagnostic (paths, ports, env, registry, defender, UAC)
.\diagnose.ps1

# 2. PHP-specific check
.\php-debug.ps1

# 3. Python-specific check
.\python-debug.ps1

# 4. Performance analysis
.\performance.ps1

# 5. Error log analysis (last 24 hours)
.\analyze-logs.ps1 -Hours 24

# 6. Performance + apply fixes
.\performance.ps1 -Apply
```

All reports written to `windows/logs/diagnostic-{timestamp}/`.

---

## 1. Path Length Issues (MAX_PATH)

### Problem
Windows has a 260-character `MAX_PATH` limit. PHP, Python, and npm can fail
when paths exceed this. Example error: `The filename or extension is too long`.

### Symptoms
- `npm install` fails with long paths
- Composer cannot create cache files
- Git cannot checkout branches with deep paths
- Python cannot import modules from deep trees

### Fixes

```powershell
# 1. Enable LongPathsSupport (Windows 10 1607+ / Server 2016+)
reg add "HKLM\SYSTEM\CurrentControlSet\Control\FileSystem" /v LongPathsEnabled /t REG_DWORD /d 1 /f

# 2. Or via PowerShell
Set-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem' -Name 'LongPathsEnabled' -Value 1

# 3. Move project closer to root (shortens all paths)
# Before: C:\Users\JohnDoe\Documents\Projects\corex-dev\corex\backend\vendor\...
# After:  C:\corex\backend\vendor\...
```

### PHP-specific path fix
Add to `php.ini`:
```ini
; Allow longer paths for Windows
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

### Composer workaround
```powershell
composer config --global cache-dir "C:\composer-cache"
composer config --global vendor-dir "vendor"
```

---

## 2. Port Conflicts

### Problem
Common Windows services occupy ports needed by Corex:

| Port | Service | Conflict With |
|------|---------|---------------|
| 80   | Nginx/HTTP | IIS, Skype, VMWare, Docker |
| 443  | Nginx/HTTPS | IIS, VPNs |
| 5432 | PostgreSQL | Another PG instance |
| 6379 | Redis | Another Redis instance |
| 8000 | Laravel | Other dev servers |
| 8001 | AI Gateway | Other Python apps |
| 11434| Ollama | Another Ollama instance |

### Detection
```powershell
# Find what's on a port
netstat -ano | findstr :8000

# Get process name by PID
tasklist /FI "PID eq 12345"

# Kill the offender
taskkill /PID 12345 /F
```

### Fixes

```powershell
# Stop IIS (common port 80 conflict)
iisreset /stop
# Or disable: dism /online /disable-feature /featurename:IIS-WebServerRole

# Stop World Wide Web Publishing Service
Stop-Service W3SVC -Force
Set-Service W3SVC -StartupType Disabled

# Stop Skype from using port 80/443
# Skype → Tools → Options → Advanced → Connections → uncheck "Use port 80 and 443"

# Stop SQL Server Reporting Services (port 80)
Stop-Service ReportServer -Force
```

### Configurable ports
In `.env`:
```env
APP_PORT=8080           # Instead of 80
FORWARD_NGINX_PORT=8080
FORWARD_NGINX_SSL_PORT=8443
AI_GATEWAY_PORT=8001    # Can change in ai-gateway/.env
```

---

## 3. PHP-Specific Issues

### 3a. Extensions Not Loading

**Symptoms:**
- `Call to undefined function` errors
- Laravel fails to connect to PostgreSQL
- `Class "COM" not found` errors
- `ext-redis` not available

**Check:**
```powershell
# List loaded extensions
php -m

# Check for specific extension
php -m | findstr pdo_pgsql
php -m | findstr redis
php -m | findstr com_dotnet
```

**Fix:**
```ini
; In php.ini — ensure these are uncommented:
extension_dir = "ext"
extension=pdo_pgsql
extension=mbstring
extension=openssl
extension=curl
extension=gd
extension=zip
extension=fileinfo
extension=redis
extension=com_dotnet

; Windows-specific:
extension=php_pdo_pgsql.dll
extension=php_mbstring.dll
extension=php_openssl.dll
extension=php_curl.dll
extension=php_gd.dll
extension=php_zip.dll
extension=php_fileinfo.dll
extension=php_redis.dll
extension=php_com_dotnet.dll
```

**Verify DLLs exist:**
```powershell
dir "C:\php\ext\php_pdo_pgsql.dll"
dir "C:\php\ext\php_redis.dll"
```

**Install missing DLL:** Download correct version from https://windows.php.net/downloads/pecl/releases/

### 3b. Thread Safety

**Problem:** PHP on Windows comes in two flavors:
- **TS** (Thread-Safe) — for single-process Apache module
- **NTS** (Non-Thread-Safe) — for PHP-FPM, IIS FastCGI

**Fix:** Use NTS for Corex (better performance, fewer locking issues).

```powershell
# Check which version you have
php -i | findstr "Thread Safety"
# Should say: Thread Safety => disabled
```

### 3c. File Locking

**Symptoms:**
- `file_put_contents` fails
- Session lockups
- Cache write errors
- Laravel storage errors

**Causes:**
- Windows Defender scanning files (hold exclusive locks)
- Antivirus real-time protection
- Network drives with slow locking
- NFS/SMB shares without proper locking

**Fixes:**
```powershell
# Add Defender exclusions (run as Admin)
Add-MpPreference -ExclusionPath "C:\corex"
Add-MpPreference -ExclusionPath "$env:TMP"
Add-MpPreference -ExclusionProcess "php.exe"
Add-MpPreference -ExclusionProcess "composer.exe"

# Use Windows-native file locks instead of flock()
# In config/filesystems.php:
'local' => [
    'driver' => 'local',
    'lock' => env('FILE_LOCK_DRIVER', 'flock'),  # Windows may need 'sem' or disabled
],
```

### 3d. Memory Limit

**Symptoms:**
- `Allowed memory size of X bytes exhausted`
- OOM errors during `composer install`
- Laravel dies on large requests

**Fix:**
```ini
; In php.ini
memory_limit = 512M       # Or -1 for unlimited
max_execution_time = 300   # AI requests need more time
max_input_time = 300
```

**For Composer:**
```powershell
# Set per-command
php -d memory_limit=-1 composer.phar install
```

### 3e. Timezone

**Symptoms:**
- Laravel shows wrong timestamps
- `date()` returns unexpected values
- `Carbon` parsing errors

**Fix:**
```ini
; In php.ini — MUST be set
date.timezone = "UTC"
; Or match Windows timezone:
; date.timezone = "America/New_York"
```

```powershell
# Check Windows timezone vs PHP
Get-Timezone
php -i | findstr timezone
```

### 3f. Visual C++ Redistributable

**Symptoms:**
- PHP fails to start
- `VCRUNTIME140.dll` missing
- `MSVCR110.dll` not found

**Fix:**
```powershell
# Download and install:
# https://aka.ms/vs/17/release/vc_redist.x64.exe

# Check if installed:
Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\*' |
    Select-Object DisplayName
```

---

## 4. Python-Specific Issues

### 4a. DLL Loading Errors

**Symptoms:**
- `ImportError: DLL load failed`
- `OSError: [WinError 126] The specified module could not be found`
- `ImportError: cannot import name 'utf8' from 'ctypes'`
- Cryptography package errors

**Causes:**
- Missing Visual C++ Redistributable
- Missing `.dll` in PATH
- 32-bit Python with 64-bit DLLs (or vice versa)

**Fixes:**

```powershell
# 1. Install VC++ Redistributable
# https://aka.ms/vs/17/release/vc_redist.x64.exe

# 2. Add Python DLL directory to PATH
$pythonDir = Split-Path (Get-Command python).Source
$env:Path = "$pythonDir;$pythonDir\Library\bin;$pythonDir\DLLs;$env:Path"

# 3. Reinstall problematic packages
pip uninstall cryptography -y
pip install cryptography --no-cache-dir

# 4. Check Python architecture (must match system)
python -c "import sys; print('64bit' if sys.maxsize > 2**32 else '32bit')"
```

### 4b. asyncio Event Loop

**Symptoms:**
- FastAPI won't start
- `RuntimeError: Event loop is closed`
- `NotImplementedError` in asyncio
- WebSocket connections fail

**Causes:**
- `uvloop` (Unix-only) breaks on Windows
- Wrong event loop policy for Windows I/O
- Mixing `asyncio` and `ProactorEventLoop`

**Fixes:**

```python
# In ai-gateway/main.py or app startup:
import asyncio
import sys

if sys.platform == 'win32':
    # Windows requires ProactorEventLoop for subprocess pipes
    asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

# Do NOT import uvloop on Windows
```

In `ai-gateway/app/core/config.py` (already handles this):
```python
host: str = Field(default="0.0.0.0" if not IS_WINDOWS else "127.0.0.1")
workers: int = Field(default=4 if not IS_WINDOWS else 2)
```

### 4c. Process Management

**Symptoms:**
- Subprocess hangs or never completes
- `subprocess.Popen` returns before process finishes
- Zombie processes on exit

**Fixes:**
```python
# Always use creationflags on Windows
import subprocess
import sys

if sys.platform == 'win32':
    proc = subprocess.Popen(
        ['python', 'script.py'],
        creationflags=subprocess.CREATE_NO_WINDOW,  # No console window
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
else:
    proc = subprocess.Popen(['python', 'script.py'])

# For clean process tree termination:
if sys.platform == 'win32':
    subprocess.run(['taskkill', '/F', '/T', '/PID', str(proc.pid)])
else:
    proc.terminate()
```

### 4d. Signal Handling

**Symptoms:**
- Ctrl+C doesn't stop the server
- Service ignores shutdown requests
- `SIGTERM` doesn't work

**Explanation:**
Windows signals are fundamentally different from Unix:
- `SIGTERM` → Python emulates it, unreliable
- `SIGINT` → Ctrl+C, works but limited
- `SIGBREAK` → Ctrl+Break, CTRL_CLOSE_EVENT
- `SIGKILL` → Does not exist; use `TerminateProcess`

**Fixes:**
```python
import signal
import sys

if sys.platform == 'win32':
    # Use SetConsoleCtrlHandler via pywin32
    import win32api

    def handler(type):
        if type in (win32api.CTRL_C_EVENT, win32api.CTRL_BREAK_EVENT):
            # Clean shutdown
            sys.exit(0)
        return True

    win32api.SetConsoleCtrlHandler(handler, True)
else:
    signal.signal(signal.SIGTERM, lambda s, f: sys.exit(0))
```

### 4e. Unicode/Encoding

**Symptoms:**
- `UnicodeEncodeError: 'charmap' codec can't encode character`
- `UnicodeDecodeError` when reading files
- Garbled text in logs
- JSON parsing errors with non-ASCII data

**Fixes:**
```powershell
# Set environment variables
setx PYTHONIOENCODING "utf-8"

# In PowerShell, set output to UTF-8
$OutputEncoding = [Console]::OutputEncoding = [Text.UTF8Encoding]::UTF8
```

```python
# In Python code:
import sys
import io

# Force UTF-8 for all I/O
if sys.platform == 'win32':
    sys.stdin = io.TextIOWrapper(sys.stdin.buffer, encoding='utf-8')
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')
```

Or use Python 3.12's `-X utf8` mode:
```powershell
set PYTHONUTF8=1
```

---

## 5. Performance Optimization

### 5a. Memory

```powershell
# Check current memory
(Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory / 1MB

# Top memory consumers
Get-Process | Sort-Object WorkingSet64 -Descending | Select-Object -First 10 Name, @{N='MB';E={[math]::Round($_.WorkingSet64/1MB,1)}}

# Increase page file to recommended size (1.5x RAM)
wmic computersystem where name="%computername%" set AutomaticManagedPagefile=True

# Set PHP memory limit
php -i | findstr memory_limit
# In php.ini: memory_limit = 512M

# Limit Python memory in uvicorn
# Run with: uvicorn ... --limit-max-requests 10000
```

### 5b. CPU Spikes

```powershell
# Find high-CPU processes
Get-Process | Sort-Object CPU -Descending | Select-Object -First 10 Name, CPU, Id

# Check power plan (should NOT be Power Saver)
powercfg /getactivescheme

# Switch to High Performance
powercfg /setactive 8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c

# Enable PHP OPcache (2-3x faster)
# In php.ini:
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### 5c. Disk I/O

```powershell
# Check disk fragmentation
Optimize-Volume -DriveLetter C -Analyze

# Defrag if >10% fragmented
Optimize-Volume -DriveLetter C -Defrag

# Add Windows Defender exclusions (reduces file scan overhead)
Add-MpPreference -ExclusionPath "C:\corex"
Add-MpPreference -ExclusionProcess "php.exe"
Add-MpPreference -ExclusionProcess "python.exe"
Add-MpPreference -ExclusionProcess "composer.exe"
Add-MpPreference -ExclusionProcess "nginx.exe"
Add-MpPreference -ExclusionProcess "redis-server.exe"

# Move Corex to SSD if on HDD
```

### 5d. Network

```powershell
# Enable TCP receive window auto-tuning
netsh int tcp set global autotuninglevel=normal

# Enable RSS (Receive-Side Scaling) for multi-core
netsh int tcp set global rss=enabled

# Disable TCP Chimney Offload (causes issues)
netsh int tcp set global chimney=disabled

# Disable network adapter power saving
Get-NetAdapter | Disable-NetAdapterPowerManagement

# Test latency to API
Test-NetConnection -ComputerName api.corex.dev -Port 443
```

### 5e. Registry Tweaks

Save as `corex-perf.reg` and double-click:

```reg
Windows Registry Editor Version 5.00

; Enable long paths
[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\FileSystem]
"LongPathsEnabled"=dword:00000001

; Performance tweaks
[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Session Manager\Memory Management]
"LargeSystemCache"=dword:00000000

; Network optimization
[HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters]
"TcpTimedWaitDelay"=dword:0000001e
"MaxUserPort"=dword:0000fffe

; Disable animations
[HKEY_CURRENT_USER\Control Panel\Desktop]
"AutoEndTasks"="1"
"HungAppTimeout"="5000"
"WaitToKillAppTimeout"="5000"
"MenuShowDelay"="0"
```

---

## 6. Windows Defender & Antivirus

### Symptoms
- Slow Laravel responses (Defender scans every PHP file on access)
- Composer installs taking 10x longer
- Python imports delayed by 100ms+
- `Access Denied` errors on temp files

### Exclusions

```powershell
# Critical exclusions for Corex (run as Admin)
Add-MpPreference -ExclusionPath "C:\corex"
Add-MpPreference -ExclusionPath "$env:TMP"
Add-MpPreference -ExclusionPath "$env:LOCALAPPDATA\Corex"
Add-MpPreference -ExclusionPath "$env:USERPROFILE\.composer"
Add-MpPreference -ExclusionProcess "php.exe"
Add-MpPreference -ExclusionProcess "python.exe"
Add-MpPreference -ExclusionProcess "composer.exe"
Add-MpPreference -ExclusionProcess "nginx.exe"
Add-MpPreference -ExclusionProcess "redis-server.exe"
Add-MpPreference -ExclusionProcess "node.exe"
Add-MpPreference -ExclusionProcess "artisan"

# Verify exclusions
Get-MpPreference | Select-Object -ExpandProperty ExclusionPath
Get-MpPreference | Select-Object -ExpandProperty ExclusionProcess
```

### Temporary disable for builds
```powershell
# Only during installs; re-enable after
Set-MpPreference -DisableRealtimeMonitoring $true
# ... run composer/pip install ...
Set-MpPreference -DisableRealtimeMonitoring $false
```

---

## 7. UAC & Permissions

### Symptoms
- "Access Denied" errors from PHP
- Service won't install
- Cannot write to Program Files
- Registry access fails

### Fixes

```powershell
# Always run the following as Administrator:
# - Service installs
# - Port binding (ports < 1024)
# - Registry writes
# - Windows Defender changes

# Run PHP as administrator for development:
# Right-click php.exe → Properties → Compatibility → Run as Administrator

# Check if running elevated:
[Security.Principal.WindowsPrincipal]::new(
    [Security.Principal.WindowsIdentity]::GetCurrent()
).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
```

### UAC Levels
```powershell
# Check current UAC level
$key = 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System'
Get-ItemProperty $key -Name ConsentPromptBehaviorAdmin

# Values:
# 0 = Admin approval mode disabled (NOT recommended)
# 2 = Prompt for consent (default, recommended)
# 5 = Prompt for credentials
```

---

## 8. Environment Variable Problems

### Symptoms
- `php` not recognized
- `python` not found
- `composer` command fails
- PATH too long errors

### Fixes

```powershell
# Check PATH length
$env:Path.Length
# Max is 4096 characters. If near limit, remove unused entries.

# Edit PATH safely
$oldPath = [Environment]::GetEnvironmentVariable('Path', 'User')
$newPath = ($oldPath -split ';' | Where-Object {
    $_ -notmatch 'C:\\Program Files\\Docker\\Docker\\resources\\bin'  # Remove old Docker paths
} | Where-Object { $_ } ) -join ';'
[Environment]::SetEnvironmentVariable('Path', $newPath, 'User')

# Add Corex tools to PATH
$toolsDir = "C:\corex\windows\packaging\tools"
$currentPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if ($currentPath -notlike "*$toolsDir*") {
    [Environment]::SetEnvironmentVariable('Path', "$currentPath;$toolsDir\php;$toolsDir\python", 'User')
}

# Required environment variables
setx COREX_DATA_DIR "%LOCALAPPDATA%\Corex\data"
setx COREX_LOG_DIR "%LOCALAPPDATA%\Corex\logs"
setx PYTHONIOENCODING "utf-8"
```

---

## 9. Service Startup Failures

### Symptoms
- `Service did not respond to start request`
- `Error 1053: The service did not respond in a timely fashion`
- Service starts then immediately stops

### Debugging

```powershell
# 1. Check Service Control Manager errors
Get-WinEvent -LogName System -MaxEvents 10 | Where-Object {
    $_.ProviderName -eq 'Service Control Manager'
} | Format-Table TimeCreated, Id, Message -Wrap

# 2. Check if service account has permissions
Get-Service CorexServiceHost | Select-Object Name, Status, StartType

# 3. Try running the service executable manually (outside service context)
& "C:\corex\windows\native-services\CorexServiceHost\bin\Release\CorexServiceHost.exe" --debug

# 4. Check Windows Event Logs for service crashes
Get-WinEvent -LogName Application -MaxEvents 20 | Where-Object {
    $_.ProviderName -match 'Application Error|\.NET Runtime|Windows Error Reporting'
} | Format-Table TimeCreated, Message -Wrap

# 5. Fix common service issues
# - Ensure service runs as LocalSystem or dedicated service account
# - Add "Interactive Services Detection" dependency if needed
# - Set service recovery: first failure → restart, second → restart, subsequent → restart
sc failure CorexServiceHost reset=86400 actions=restart/60000/restart/120000/restart/300000

# 6. Check service dependencies
sc queryex CorexServiceHost
sc qc CorexServiceHost

# 7. Remove and recreate service if corrupted
sc delete CorexServiceHost
& "C:\corex\windows\native-services\build-and-deploy.ps1"
```

### Service Timeout
```powershell
# Increase service timeout (default 30s, not enough for PHP/Python startup)
reg add "HKLM\SYSTEM\CurrentControlSet\Control" /v ServicesPipeTimeout /t REG_DWORD /d 60000 /f
# Restart required
```

---

## 10. Registry Issues

### Symptoms
- Theme not detected (always dark/light)
- Proxy settings not read
- `reg_read` returns null
- Permission denied on `HKLM\SOFTWARE\Corex`

### Fixes

```powershell
# Check Corex registry keys
reg query "HKLM\SOFTWARE\Corex\AIGateway"
reg query "HKCU\Software\Corex"

# Create if missing
New-Item -Path "HKLM:\SOFTWARE\Corex\AIGateway" -Force
New-ItemProperty -Path "HKLM:\SOFTWARE\Corex\AIGateway" -Name "ollama_default_model" -Value "llama3.2" -PropertyType String -Force

# Grant read access to all users (for non-admin PHP)
$regPath = "HKLM:\SOFTWARE\Corex"
$acl = Get-Acl $regPath
$rule = New-Object System.Security.AccessControl.RegistryAccessRule(
    "Users","ReadKey","ContainerInherit,ObjectInherit","None","Allow"
)
$acl.SetAccessRule($rule)
Set-Acl $regPath $acl

# Check theme/proxy registry values
Get-ItemProperty "HKCU:\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize" -Name AppsUseLightTheme
Get-ItemProperty "HKCU:\Software\Microsoft\Windows\CurrentVersion\Internet Settings" -Name ProxyEnable
```

---

## 11. Docker on Windows

### Symptoms
- Docker Desktop won't start
- WSL2 integration broken
- Volume mounts fail
- Network issues

### Fixes

```powershell
# Reset Docker
wsl --shutdown
taskkill /IM "Docker Desktop.exe" /F
Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"

# WSL2 memory limit (edit %USERPROFILE%\.wslconfig):
[wsl2]
memory=8GB
processors=4
swap=2GB
localhostForwarding=true

# Then restart WSL
wsl --shutdown
# Docker → Restart

# If volume mounts fail, share drive in Docker settings:
# Docker Desktop → Settings → Resources → File Sharing → Add C:\
```

---

## 12. Ollama (Local AI)

### Symptoms
- Ollama won't start
- Model pull fails
- Connection refused on :11434
- GPU not detected

### Fixes

```powershell
# Check if Ollama is running
netstat -ano | findstr :11434

# Install Ollama
winget install Ollama.Ollama
# Or download from https://ollama.com/download

# Start Ollama
& "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe" serve

# Set Ollama models directory (if short on C: space)
setx OLLAMA_MODELS "D:\ollama\models"

# Check GPU support
ollama ps
# Should show running models

# Test API
curl http://127.0.0.1:11434/api/tags
```

---

## Diagnostics File Reference

| File | Purpose | Run When |
|------|---------|----------|
| `windows/diagnostics/diagnose.ps1` | Full system check (paths, ports, env, UAC, Defender) | First run, after Windows updates |
| `windows/diagnostics/php-debug.ps1` | PHP extensions, memory, timezone, COM, OpCache | PHP errors, slow Laravel |
| `windows/diagnostics/python-debug.ps1` | Python DLLs, asyncio, encoding, dependencies | Python import errors, asyncio issues |
| `windows/diagnostics/performance.ps1` | CPU, RAM, disk, network, Defender exclusions | Slow performance, high resource usage |
| `windows/diagnostics/analyze-logs.ps1` | Event Viewer + PHP + Python + Docker log analysis | Crashes, errors, debugging |
| `windows/TestWindowsCompatibility.ps1` | Legacy compatibility test | First-time setup, deployment check |

---

## Registry Reference

```registry
; Corex Application Settings
HKLM\SOFTWARE\Corex\AIGateway
  ollama_default_model (REG_SZ) = "llama3.2"
  ollama_base_url (REG_SZ) = "http://127.0.0.1:11434"
  redis_host (REG_SZ) = "127.0.0.1"
  install_path (REG_SZ) = "C:\corex"

; Windows Theme (read by ThemeService)
HKCU\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize
  AppsUseLightTheme (REG_DWORD) = 0 (dark) / 1 (light)
  SystemUsesLightTheme (REG_DWORD) = 0 (dark) / 1 (light)
  ColorPrevalence (REG_DWORD) = 0 / 1 (accent in title bars)

HKCU\Software\Microsoft\Windows\DWM
  AccentColor (REG_DWORD) = 0xRRGGBB hex
  ColorizationColor (REG_DWORD) = 0xAARRGGBB hex
  EnableTransparency (REG_DWORD) = 0 / 1

; Performance
HKLM\SYSTEM\CurrentControlSet\Control\FileSystem
  LongPathsEnabled (REG_DWORD) = 1

HKLM\SYSTEM\CurrentControlSet\Control
  ServicesPipeTimeout (REG_DWORD) = 60000

HKLM\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters
  TcpTimedWaitDelay (REG_DWORD) = 30
  MaxUserPort (REG_DWORD) = 65534

; Proxy (read by ProxySettingsService)
HKCU\Software\Microsoft\Windows\CurrentVersion\Internet Settings
  ProxyEnable (REG_DWORD) = 0 / 1
  ProxyServer (REG_SZ) = "http://proxy:8080"
  ProxyOverride (REG_SZ) = "*.local;10.*"
```

---

## Common Error Messages & Fixes

| Error | Likely Cause | Fix |
|-------|-------------|-----|
| `Class "COM" not found` | `com_dotnet` extension missing | `extension=com_dotnet` in php.ini |
| `PHP Fatal error: Out of memory` | `memory_limit` too low | Set `memory_limit = 512M` or `-1` |
| `Unable to load dynamic library 'php_pdo_pgsql.dll'` | PostgreSQL extension missing | Copy `php_pdo_pgsql.dll` to `ext/` |
| `VCRUNTIME140.dll not found` | VC++ Redistributable missing | Install VC++ 2015-2022 x64 redist |
| `DLL load failed while importing _ssl` | OpenSSL DLL missing | Reinstall Python; install VC++ redist |
| `NotImplementedError` in asyncio | uvloop used on Windows | Remove `import uvloop` |
| `UnicodeEncodeError` | `PYTHONIOENCODING` not set | `setx PYTHONIOENCODING utf-8` |
| `Address already in use :::8000` | Port conflict | `netstat -ano \| findstr :8000`, `taskkill /PID <PID> /F` |
| `Connection refused :11434` | Ollama not running | Start Ollama: `ollama serve` |
| `The filename or extension is too long` | MAX_PATH hit | Enable `LongPathsEnabled` in registry |
| `Access Denied` writing to registry | Not admin | Run PHP as Administrator |
| `Cannot write to storage/logs` | File permissions | Check ACLs; add Defender exclusion |
| `Maximum function nesting level of '256'` reached | Xdebug nesting limit | `xdebug.max_nesting_level = 512` |
| `Service did not respond in time` | Startup timeout | Increase `ServicesPipeTimeout` to 60000 |
| `No application key has been specified` | `.env` missing `APP_KEY` | `php artisan key:generate` |
| `The system cannot find the path specified` | Long path | Enable LongPathsSupport; move project to C:\ |
| `Windows cannot find 'php.exe'` | PHP not in PATH | Add PHP directory to PATH |
| `Error: Cannot find module 'electron'` | npm not installed | `npm install` in `electron/` directory |

---

## Quick Win Checklist

Run these in order for immediate relief:

```powershell
# 1. Enable long paths (Windows 10/11)
reg add "HKLM\SYSTEM\CurrentControlSet\Control\FileSystem" /v LongPathsEnabled /t REG_DWORD /d 1 /f

# 2. Add project to Defender exclusions
Add-MpPreference -ExclusionPath "C:\corex" -ErrorAction SilentlyContinue

# 3. Switch to High Performance power plan
powercfg /setactive 8c5e7fda-e8bf-4a96-9a85-a6e23a8c635c

# 4. Increase PHP memory
# Edit php.ini: memory_limit = 512M

# 5. Set Python UTF-8 mode
setx PYTHONIOENCODING "utf-8"

# 6. Set timezone in php.ini
# date.timezone = "UTC"

# 7. Increase service timeout
reg add "HKLM\SYSTEM\CurrentControlSet\Control" /v ServicesPipeTimeout /t REG_DWORD /d 60000 /f

# 8. Enable PHP OPcache
# [opcache]
# opcache.enable=1
# opcache.memory_consumption=128

# 9. Restart
Restart-Computer
```
