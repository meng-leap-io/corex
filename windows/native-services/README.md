# Corex Native Windows Services

Run Corex services natively on Windows (no Docker required). Four individual Windows services plus an optional unified C# wrapper.

## Services

| Service Name | Display Name | Description | Startup Order |
|---|---|---|---|
| `CorexRedis` | Corex Redis Server | In-memory cache & queue | 1 |
| `CorexPHP` | Corex PHP-FPM | PHP FastCGI Process Manager | 2 |
| `CorexAIGateway` | Corex AI Gateway | Python FastAPI AI proxy | 3 (after Redis) |
| `CorexNginx` | Corex Nginx Web Server | Reverse proxy for Laravel | 4 (after PHP) |
| `CorexServiceHost` | Corex Service Host | Unified C# wrapper (optional) | Manages all above |

## Requirements

- **Windows 10/11** or **Windows Server 2019+**
- **.NET 8 Runtime** (only for `CorexServiceHost`)
- **Redis** — Install to `C:\Program Files\Redis\`
- **PHP 8.4+** — Extract to `C:\Program Files\Corex\php\`
- **Nginx** — Extract to `C:\Program Files\Corex\nginx\`
- **Python 3.12+** — Install to `C:\Program Files\Python312\`

## Quick Start

### 1. Install Dependencies

```powershell
# Run as Administrator
.\Install-Dependencies.ps1
```

### 2. Install All Services

```powershell
.\Install-CorexNativeServices.ps1 -Action Install
```

### 3. Start Services

```powershell
.\Install-CorexNativeServices.ps1 -Action Start
```

### 4. Verify

```powershell
.\Install-CorexNativeServices.ps1 -Action Status
```

Expected: all 4 services show `Running`.

## Service Scripts

| Script | Purpose |
|---|---|
| `Install-CorexNativeServices.ps1` | Create/remove/manage all 4 services |
| `build-and-deploy.ps1` | Build & deploy the C# unified wrapper |
| `manage-nginx.ps1` | Reload, test, stop Nginx config |
| `manage-redis.ps1` | Redis info, memory, flush, ping |

## C# Unified Wrapper (CorexServiceHost)

An alternative to running 4 separate services — single Windows service that manages all child processes:

```
CorexServiceHost.exe
├── redis-server.exe
├── php-fpm.exe
├── uvicorn (main:app)
└── nginx.exe
```

**Features:**
- Manages process lifecycle (start, stop, restart)
- Auto-restart on crash (up to 5 times per 5-minute window)
- Graceful shutdown (Ctrl+Break for PHP/Redis, SIGTERM for Python)
- Windows Event Log integration (source: `CorexServiceHost`)
- Performance counter reporting every 30 seconds (CPU, memory per process)
- HTTP health checks against AI Gateway
- File logging to `{CorexRoot}\logs\CorexServiceHost\`

### Build & Install

```powershell
.\build-and-deploy.ps1
net start CorexServiceHost
```

## Recovery Configuration

Each service has Windows Service Recovery configured:

- **First failure**: Restart after 5 seconds
- **Second failure**: Restart after 10 seconds
- **Subsequent failures**: Restart after 15 seconds
- **Reset failure count**: After 24 hours
- **Failure actions enabled**: Yes (`failureflag=1`)

## Event Logging

All services log to **Windows Event Log > Application** under these sources:

| Source | Events |
|---|---|
| `CorexRedis` | Start/stop, errors, OOM warnings |
| `CorexPHP` | FPM pool status, worker errors |
| `CorexNginx` | Config reload, listen errors |
| `CorexAIGateway` | Uvicorn start/stop, provider errors |
| `CorexServiceHost` | Child process lifecycle, health checks |
| `CorexPerfMon` | Performance counter snapshots |

View with:
```powershell
Get-WinEvent -LogName Application | Where-Object Provider -Match 'Corex'
```

## Performance Monitoring

Performance counters are collected every 30 seconds via `logman`:

**Process counters per service:**
- `% Processor Time`
- `Private Bytes`
- `Working Set`
- `Thread Count`
- `Handle Count`

**System counters:**
- `Memory\Available MBytes`
- `Processor(_Total)\% Processor Time`

Data stored in: `{CorexRoot}\logs\perfmon\CorexPerfmon*.blg`

View with Performance Monitor GUI (`perfmon.msc`) or:
```powershell
relog "C:\Program Files\Corex\logs\perfmon\CorexPerfmon_000001.blg" -f csv -o report.csv
```

## Uninstall

```powershell
.\Install-CorexNativeServices.ps1 -Action Uninstall
```

To also remove the C# wrapper:
```powershell
sc.exe delete CorexServiceHost
```
