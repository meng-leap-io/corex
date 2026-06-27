# Corex Windows Service Management Guide

A complete guide to running Corex on Windows — choose from two deployment approaches:

| Approach | Description | Directory |
|---|---|---|
| **Docker-based** (default) | Uses Docker Desktop + Docker Compose | `windows/` (this guide) |
| **Native services** | Runs PHP-FPM, Nginx, Redis, AI Gateway directly as Windows services | `windows/native-services/` |

For native Windows services (no Docker required), see [native-services/README.md](native-services/README.md).

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Prerequisites](#prerequisites)
3. [Quick Start](#quick-start)
4. [Service Management](#service-management)
5. [Windows Task Scheduler](#windows-task-scheduler)
6. [Troubleshooting](#troubleshooting)
7. [Advanced Configuration](#advanced-configuration)

---

## System Requirements

### Minimum Requirements

- **Windows 10** (version 21H2 or later) or **Windows Server 2022**
- **8 GB RAM** (16 GB recommended)
- **4 CPU cores** (8 cores recommended)
- **30 GB disk space** for containers and data
- **Administrator privileges** on the machine

### Recommended Setup

- Windows 11 Pro/Enterprise
- 16+ GB RAM
- 8+ CPU cores
- SSD storage with 50+ GB free space
- Virtualization enabled in BIOS (for Hyper-V)

---

## Prerequisites

### 1. Docker Desktop for Windows

Corex relies entirely on Docker containers. Install Docker Desktop:

1. Download from: https://www.docker.com/products/docker-desktop
2. Install Docker Desktop
3. Enable **WSL 2 backend** during installation (recommended)
4. Restart your computer after installation

**Verify installation:**
```powershell
docker --version
docker compose version
```

### 2. PowerShell 5.1+

PowerShell 5.1 is included with Windows 10/11. Verify:

```powershell
$PSVersionTable.PSVersion
```

### 3. Optional: PHP (for local Laravel commands)

If you want to run Laravel commands locally (outside Docker):

1. Download PHP from: https://www.php.net/downloads.php
2. Extract to a folder (e.g., `C:\PHP`)
3. Add to PATH: `C:\PHP` environment variable
4. Verify: `php --version`

### 4. Optional: NSSM (for Windows Service Management)

If you want Corex to run as a Windows Service:

1. Download NSSM from: https://nssm.cc/download
2. Extract to: `C:\Program Files\NSSM`
3. Add to PATH environment variable: `C:\Program Files\NSSM\win64`
4. Verify: `nssm status`

**Adding to PATH:**
- Right-click "This PC" → Properties
- Click "Advanced system settings"
- Click "Environment Variables"
- Edit `Path` and add `C:\Program Files\NSSM\win64`
- Click OK and restart PowerShell/Command Prompt

---

## Quick Start

### 1. Configure Environment

```bash
# Navigate to project directory
cd C:\path\to\corex

# Copy environment template
copy backend\.env.example backend\.env

# Edit backend\.env with your configuration
# (Database, Redis, API keys, etc.)
```

### 2. Run Compatibility Test

```powershell
# Run as Administrator
cd C:\path\to\corex\windows
.\test-compatibility.bat
```

Review the test results. All critical items should pass.

### 3. Start Services

```powershell
# Run as Administrator
cd C:\path\to\corex\windows
.\start.bat
```

This will:
- Pull Docker images if needed
- Create containers
- Start all services (PostgreSQL, Redis, PHP, Nginx, AI Gateway, etc.)
- Perform health checks

### 4. Verify Services

```powershell
# Check status
.\status.bat

# Health check
.\health.bat

# View logs
.\logs.bat
```

### 5. Access the Application

- **Web UI**: http://localhost (default Nginx)
- **API**: http://localhost:8000/health
- **AI Gateway**: http://localhost:8001/health

---

## Service Management

### Batch Scripts

Simple batch files (double-click to run as Administrator):

| Script | Purpose |
|--------|---------|
| `start.bat` | Start all services |
| `stop.bat` | Stop all services |
| `restart.bat` | Restart all services |
| `status.bat` | Show service status |
| `health.bat` | Perform health checks |
| `logs.bat` | View service logs |

### PowerShell Commands

Advanced management via PowerShell:

```powershell
# Run as Administrator
cd C:\path\to\corex\windows

# Start services
.\CorexServiceWrapper.ps1 -Action Start

# Stop services
.\CorexServiceWrapper.ps1 -Action Stop

# Restart services
.\CorexServiceWrapper.ps1 -Action Restart

# Check status
.\CorexServiceWrapper.ps1 -Action Status

# Health check
.\CorexServiceWrapper.ps1 -Action HealthCheck

# View logs
.\CorexServiceWrapper.ps1 -Action Logs

# View service loop (continuous monitoring)
.\CorexServiceWrapper.ps1 -Action ServiceLoop
```

---

## Windows Task Scheduler

### Automatic Scheduling (Laravel Scheduler, Queue Jobs, etc.)

Corex includes Windows Task Scheduler integration to replace Linux `cron` jobs.

### Setup Tasks

```powershell
# Run as Administrator
cd C:\path\to\corex\windows
.\task-install.bat
```

This will create Windows Task Scheduler tasks for:
- **Corex-Laravel-Scheduler**: Runs Laravel scheduler every minute
- **Corex-Queue-Worker**: Processes Laravel queue jobs
- **Corex-Cache-Clear**: Clears cache daily at 2 AM
- **Corex-Backup-Database**: Creates database backup daily at 3 AM

### View Scheduled Tasks

```powershell
# View in GUI
tasksched.msc

# Or via command line
.\task-list.bat
```

### Test Tasks

```powershell
# Test Laravel scheduler configuration
.\task-test.bat
```

### Task Log Files

Task execution logs are stored in: `logs/task-*.log`

Example:
- `logs/task-Corex-Laravel-Scheduler.log`
- `logs/task-Corex-Queue-Worker.log`

---

## Windows Service Integration (NSSM)

Install Corex as a Windows Service that starts automatically on boot.

### Prerequisites

1. NSSM installed (see [Prerequisites](#prerequisites))
2. All other prerequisites met

### Install Service

```powershell
# Run as Administrator
cd C:\path\to\corex\windows
.\install-service.bat
```

### Manage Service

```powershell
# Start the service
net start CorexPlatform

# Stop the service
net stop CorexPlatform

# Check service status
Get-Service CorexPlatform

# Or use Services.msc GUI
services.msc
```

### Uninstall Service

```powershell
# Run as Administrator
cd C:\path\to\corex\windows
.\uninstall-service.bat
```

### Service Auto-Start on Boot

The service is configured to start automatically on system boot. To change:

1. Open `services.msc`
2. Find "Corex AI Development Platform"
3. Right-click → Properties
4. Set "Startup type" to "Automatic" (default)

---

## Logs and Diagnostics

### Log Locations

| Log File | Purpose |
|----------|---------|
| `logs/corex-service.log` | Service wrapper logs |
| `logs/task-scheduler.log` | Task scheduler logs |
| `logs/windows-compatibility-test.log` | Compatibility test results |

### View Service Logs

```powershell
# View all service logs
.\logs.bat

# Or directly with Docker
docker compose --file docker-compose.yml logs -f

# View specific container
docker logs corex-php -f
docker logs corex-postgres -f
```

### Troubleshoot Issues

```powershell
# Run compatibility test
.\test-compatibility.bat

# Check individual components
powershell -File TestWindowsCompatibility.ps1 -TestCategory System
powershell -File TestWindowsCompatibility.ps1 -TestCategory Dependencies
powershell -File TestWindowsCompatibility.ps1 -TestCategory Docker
```

---

## Troubleshooting

### Services Won't Start

1. **Run compatibility test:**
   ```powershell
   .\test-compatibility.bat
   ```

2. **Check Docker daemon:**
   ```powershell
   docker info
   ```

3. **Verify environment variables:**
   ```powershell
   Get-Content backend\.env | Select-String DB_PASSWORD
   ```

4. **Check logs:**
   ```powershell
   .\logs.bat
   docker logs corex-php
   ```

### Docker Permission Denied

Ensure Docker Desktop is running and you're using the correct WSL 2 backend.

### Port Conflicts

Services use ports: 80 (HTTP), 443 (HTTPS), 5432 (PostgreSQL), 6379 (Redis), 8000+ (APIs)

Check for conflicts:
```powershell
netstat -ano | findstr :8000
netstat -ano | findstr :5432
```

### Database Connection Errors

1. Verify PostgreSQL container is running:
   ```powershell
   docker ps | findstr postgres
   ```

2. Check environment configuration:
   ```powershell
   Get-Content backend\.env | Select-String DB_
   ```

3. Test database connection from container:
   ```powershell
   docker exec corex-php php artisan tinker
   ```

### Task Scheduler Jobs Not Running

1. Verify tasks are installed:
   ```powershell
   .\task-list.bat
   ```

2. Check task logs:
   ```powershell
   Get-Content logs/task-Corex-Laravel-Scheduler.log
   ```

3. Manually trigger a task:
   ```powershell
   taskkill /TN "\Corex\Corex-Laravel-Scheduler" /V
   # Then trigger via Windows Task Scheduler UI
   ```

---

## Advanced Configuration

### Custom Environment Variables

Edit `backend\.env`:

```bash
# Database
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=corex
DB_USERNAME=corex
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=your_redis_password

# AI Gateway
AI_GATEWAY_URL=http://ai-gateway:8000
AI_GATEWAY_API_KEY=your_api_key

# Sentry
SENTRY_LARAVEL_DSN=https://your-sentry-dsn@sentry.io/12345
```

### Container Resource Limits

Edit `docker-compose.yml` to adjust resource limits:

```yaml
php:
  deploy:
    resources:
      limits:
        memory: 512M
        cpus: "1.0"
      reservations:
        memory: 256M
```

### Custom Task Scheduler Jobs

Edit `SetupTaskScheduler.ps1` to add/modify tasks:

```powershell
$Tasks = @(
    @{
        Name        = 'My-Custom-Task'
        Description = 'Description'
        Script      = 'Your PowerShell script here'
        StartTime   = '04:00:00'
        Frequency   = 'Daily'
    }
)
```

### Network Configuration

By default, Corex uses `corex-network` bridge network. To change:

1. Edit `docker-compose.yml`
2. Update network names
3. Rebuild: `docker compose down && docker compose up -d`

---

## Performance Tuning

### Docker Desktop Settings

1. Right-click Docker Desktop icon → Settings
2. Go to Resources tab
3. Adjust:
   - **CPUs**: Allocate 4+ cores
   - **Memory**: Allocate 8+ GB
   - **Swap**: Set to 2-4 GB
4. Click "Apply & Restart"

### WSL 2 Memory Optimization

Edit or create `%USERPROFILE%\.wslconfig`:

```ini
[wsl2]
memory=8GB
processors=4
swap=2GB
localhostForwarding=true
```

Restart WSL:
```powershell
wsl --shutdown
```

---

## Support and Documentation

- **Issues**: Report on GitHub
- **Logs**: Check `logs/` directory
- **Docker Compose**: `https://docs.docker.com/compose/`
- **Laravel**: `https://laravel.com/docs`
- **FastAPI**: `https://fastapi.tiangolo.com/`

---

## Quick Reference

```powershell
# Start all services
.\start.bat

# Check everything is healthy
.\health.bat

# Stop everything
.\stop.bat

# Run diagnostics
.\test-compatibility.bat

# View real-time logs
.\logs.bat

# Schedule Laravel tasks
.\task-install.bat

# Run as service (NSSM)
.\install-service.bat
net start CorexPlatform

# Check service status
Get-Service CorexPlatform
```

---

Last Updated: 2026-06-27
Version: 1.0.0
