# Corex Windows Setup Guide

Complete step-by-step guide to set up Corex on Windows for the first time.

## Phase 1: System Preparation (15-30 minutes)

### Step 1: Check System Requirements

Run this **as Administrator**:

```powershell
# Check Windows version
[Environment]::OSVersion.VersionString

# Check RAM (should be 8GB+)
[math]::Round((Get-CimInstance CIM_PhysicalMemory | Measure-Object -Property capacity -Sum).sum / 1GB)

# Check CPU cores (should be 4+)
(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).sum

# Check free disk space (should be 30GB+)
Get-Volume C | Select-Object SizeRemaining
```

**Requirements:**
- ✅ Windows 10 version 21H2+ or Windows 11
- ✅ 8 GB RAM (16 GB recommended)
- ✅ 4 CPU cores (8+ recommended)
- ✅ 30 GB free disk space

### Step 2: Enable Required Windows Features

Run **as Administrator**:

```powershell
# Enable WSL 2 and Hyper-V (for Docker)
wsl --install

# Enable Hyper-V
Enable-WindowsOptionalFeature -Online -FeatureName Microsoft-Hyper-V -All

# Restart required
```

Restart your computer when prompted.

### Step 3: Install Docker Desktop

1. Download Docker Desktop: https://www.docker.com/products/docker-desktop
2. Run the installer
3. **During installation**, select:
   - ✅ WSL 2 backend (recommended)
   - ✅ Install required WSL 2 distributions
4. Finish installation and restart

**Verify Docker installation:**

```powershell
docker --version
docker compose version
docker run hello-world
```

---

## Phase 2: Download Corex (5-10 minutes)

### Step 1: Clone Repository

Open PowerShell and run:

```powershell
cd C:\Users\YourUsername\projects

# Clone the repository
git clone https://github.com/corex-dev/corex.git
cd corex
```

Or download as ZIP from: https://github.com/corex-dev/corex/releases

### Step 2: Verify Installation

```powershell
# Check files exist
dir windows\
dir backend\
dir ai-gateway\
dir docker-compose.yml
```

---

## Phase 3: Configuration (10-15 minutes)

### Step 1: Set Up Environment File

```powershell
# Copy template
Copy-Item backend\.env.example backend\.env

# Edit with Notepad
notepad backend\.env
```

**Minimum required settings:**

```bash
APP_KEY=base64:YOUR_APP_KEY_HERE
DB_PASSWORD=your_secure_db_password
REDIS_PASSWORD=your_secure_redis_password
JWT_SECRET=your_jwt_secret_key
SENTRY_LARAVEL_DSN=
SENTRY_DSN=
```

**To generate APP_KEY:**

```powershell
# In the backend directory
docker run --rm php:8.4 php -r "echo 'base64:' . bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Step 2: Verify Environment File

```powershell
# Check file was created
Test-Path backend\.env

# View first 20 lines
Get-Content backend\.env -Head 20
```

---

## Phase 4: Run Compatibility Test (5-10 minutes)

Run as Administrator:

```powershell
cd windows
.\test-compatibility.bat
```

**Expected output:**
- ✅ All system requirements pass
- ✅ Docker installed
- ✅ PowerShell 5.1+
- ⚠ PHP optional (needed for local Laravel commands)

Fix any **Failed** items before proceeding.

---

## Phase 5: Start Services (10-20 minutes)

### Step 1: Start All Services

Run as Administrator:

```powershell
cd windows
.\start.bat
```

This will:
1. Pull required Docker images (~5-10 minutes on first run)
2. Create containers for all services
3. Start services
4. Perform health checks

Wait for completion message.

### Step 2: Wait for Services to Stabilize

Services may take 30-60 seconds to fully initialize:

```powershell
# Check status every 10 seconds
for ($i=0; $i -lt 6; $i++) {
    .\status.bat
    Start-Sleep -Seconds 10
}
```

### Step 3: Verify Services Running

```powershell
.\status.bat
```

All containers should show as "Running".

### Step 4: Perform Health Check

```powershell
.\health.bat
```

Expected: All available services show as "Healthy"

---

## Phase 6: Verify Application Access (5 minutes)

### Test Web Access

Open browser and visit:

- **Nginx**: http://localhost (should show landing page or 404 if not configured)
- **API Health**: http://localhost:8000/health
- **AI Gateway**: http://localhost:8001/health

### Test Docker Services

```powershell
# View running containers
docker ps

# View logs
docker compose logs -f

# Test specific service
docker exec corex-php php --version
```

---

## Phase 7: Schedule Tasks (Optional, 10 minutes)

Enable automatic Laravel scheduler and queue jobs:

```powershell
cd windows
.\task-install.bat
```

View installed tasks:

```powershell
.\task-list.bat
```

---

## Phase 8: Install Windows Service (Optional, 5 minutes)

Make Corex start automatically on system boot:

### Prerequisites

Download NSSM from https://nssm.cc/download and add to PATH:

1. Extract NSSM to: `C:\Program Files\NSSM`
2. Add to PATH environment variable
3. Verify: `nssm status`

### Install Service

```powershell
cd windows
.\install-service.bat
```

Verify:

```powershell
Get-Service CorexPlatform
```

Start service:

```powershell
net start CorexPlatform
```

---

## Daily Operations

### Starting Services

```powershell
cd windows
.\start.bat
```

### Checking Status

```powershell
.\status.bat
```

### Viewing Logs

```powershell
.\logs.bat
```

### Stopping Services

```powershell
.\stop.bat
```

### Health Check

```powershell
.\health.bat
```

---

## Troubleshooting

### Docker Won't Start

```powershell
# Restart Docker Desktop
taskkill /IM Docker.exe /F
Start-Process 'C:\Program Files\Docker\Docker\Docker.exe'

# Or manually restart from system tray
```

### Ports Already in Use

```powershell
# Find process using port 8000
netstat -ano | findstr :8000

# Kill process (replace PID)
taskkill /PID 12345 /F
```

### Services Won't Start

```powershell
# Detailed error check
.\test-compatibility.bat

# View Docker logs
docker logs corex-php
docker logs corex-postgres

# Check environment file
Get-Content backend\.env
```

### Database Connection Failed

```powershell
# Check PostgreSQL container
docker ps | findstr postgres

# View PostgreSQL logs
docker logs corex-postgres

# Test from container
docker exec corex-php php -r "var_dump(\$_ENV);"
```

---

## Next Steps

1. **Configure environment** - Set API keys, database credentials, etc.
2. **Run Laravel migrations** - Set up database schema
3. **Build frontend assets** - Compile CSS/JS for web UI
4. **Configure AI providers** - Add OpenAI, Anthropic API keys
5. **Set up backups** - Configure automated database backups
6. **Enable monitoring** - Set up Sentry for error tracking

---

## Common Commands

| Task | Command |
|------|---------|
| Start all services | `.\start.bat` |
| Stop all services | `.\stop.bat` |
| Restart services | `.\restart.bat` |
| Check status | `.\status.bat` |
| View logs | `.\logs.bat` |
| Health check | `.\health.bat` |
| Run migrations | `docker exec corex-php php artisan migrate` |
| Create user | `docker exec corex-php php artisan tinker` |
| View database | `docker exec -it corex-postgres psql -U corex -d corex` |
| Clear cache | `docker exec corex-php php artisan cache:clear` |

---

## Support

- **Compatibility Test**: `.\test-compatibility.bat`
- **Logs**: `logs/` directory
- **Documentation**: `README.md` in windows folder
- **Issues**: Check Docker logs with `.\logs.bat`

---

## Success Checklist

- ✅ Docker Desktop installed and running
- ✅ All system requirements met
- ✅ Corex repository cloned
- ✅ Environment file configured
- ✅ Compatibility test passed
- ✅ All services running
- ✅ Health check passed
- ✅ Web access verified
- ✅ (Optional) Scheduled tasks installed
- ✅ (Optional) Windows service installed

**You're ready to start using Corex!**

---

First-time setup typically takes **1-2 hours** including Docker image downloads.

For subsequent startups, simply run `.\start.bat` (takes 30-60 seconds).

---

Last Updated: 2026-06-27
Version: 1.0.0
