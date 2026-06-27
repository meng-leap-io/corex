# Windows Installer Solutions for Corex

Complete Windows installation framework with three professional-grade installers.

## Quick Start

Choose your preferred installer:

### 1. PowerShell (Recommended for Developers)
```powershell
# Run as Administrator
cd windows\installer\powershell
.\Install-Corex.ps1
```

### 2. Inno Setup (Recommended for End Users)
```cmd
# Install Inno Setup first from: https://www.jrsoftware.org/isdl.php
# Then open: windows\installer\innosetup\CorexInstaller.iss
# Click: Build → Compile
# Output: CorexSetup-1.0.0.exe
```

### 3. WiX Toolset (Professional MSI)
```batch
# Install WiX from: https://wixtoolset.org/
# Then run:
cd windows\installer\wix
build.bat
```

---

## File Structure

```
windows/
  installer/
    ├── README.md (this file)
    ├── INSTALLER_GUIDE.md (comprehensive guide)
    │
    ├── powershell/
    │   ├── Install-Corex.ps1 (main installer)
    │   ├── Install-Dependencies.ps1 (dependency checker)
    │   └── Configure-Services.ps1 (service configuration)
    │
    ├── innosetup/
    │   ├── CorexInstaller.iss (Inno Setup script)
    │   ├── assets/ (icons, bitmaps)
    │   └── Output/ (generated EXE)
    │
    ├── wix/
    │   ├── Product.wxs (main WiX definition)
    │   ├── Files.wxs (file components)
    │   ├── build.bat (build script)
    │   ├── obj/ (compiled objects)
    │   └── bin/ (generated MSI)
    │
    └── updates/
        ├── Update-Corex.ps1 (update manager)
        └── README.md (update documentation)
```

---

## Installation Options

### Option 1: PowerShell Installation (Easiest)

**Standard Installation:**
```powershell
PS> cd windows\installer\powershell
PS> .\Install-Corex.ps1
```

**Custom Installation Path:**
```powershell
PS> .\Install-Corex.ps1 -InstallPath "D:\Applications\Corex"
```

**Unattended Installation:**
```powershell
PS> .\Install-Corex.ps1 -Unattended -SkipValidation
```

**Uninstall:**
```powershell
PS> .\Install-Corex.ps1 -Uninstall
```

### Option 2: Inno Setup (EXE Installer)

**Build:**
1. Install Inno Setup
2. Open `CorexInstaller.iss`
3. Click "Build" → "Compile"
4. Output: `Output\CorexSetup-1.0.0.exe`

**Install (Interactive):**
```cmd
CorexSetup-1.0.0.exe
```

**Install (Silent):**
```cmd
CorexSetup-1.0.0.exe /SILENT /NORESTART
```

### Option 3: WiX Toolset (MSI Installer)

**Build:**
```batch
cd wix
build.bat
```

**Install (Interactive):**
```cmd
msiexec /i Corex-Setup.msi
```

**Install (Silent):**
```cmd
msiexec /i Corex-Setup.msi /quiet /norestart
```

---

## What Gets Installed

### Core Components
- ✅ Laravel Backend (`backend/`)
- ✅ Python FastAPI Gateway (`ai-gateway/`)
- ✅ Nginx Configuration (`nginx/`)
- ✅ Docker Compose setup
- ✅ Management scripts
- ✅ Documentation

### Services
- ✅ Windows services (if NSSM available)
- ✅ Windows Task Scheduler jobs
- ✅ Registry entries
- ✅ Environment variables
- ✅ Desktop shortcuts

### Directories
- `C:\Program Files\Corex` - Main installation
- `%APPDATA%\Corex` - User data, caches, logs
- `C:\ProgramData\Corex` - Shared data (if multi-user)

---

## System Requirements

**Minimum:**
- Windows 10 (build 19041+) or Windows 11
- 8 GB RAM
- 30 GB disk space
- Administrator privileges
- Docker Desktop 20.10+

**Recommended:**
- Windows 11
- 16 GB RAM
- SSD with 50+ GB free space
- 8+ CPU cores

---

## Features

### PowerShell Installer (`Install-Corex.ps1`)
- ✅ Comprehensive system validation
- ✅ Dependency detection and guidance
- ✅ Automatic file copying
- ✅ Environment configuration
- ✅ Registry setup
- ✅ Service registration (with NSSM)
- ✅ Detailed logging
- ✅ Rollback on failure

### Inno Setup Installer (`CorexInstaller.iss`)
- ✅ Professional GUI installation wizard
- ✅ Component selection (optional features)
- ✅ Multi-language support
- ✅ Silent installation support
- ✅ Automatic uninstall
- ✅ Registry management
- ✅ Start menu/desktop shortcuts
- ✅ Startup integration (optional)

### WiX Toolset Installer (`Product.wxs`)
- ✅ Enterprise-grade MSI format
- ✅ Modular component architecture
- ✅ Custom actions for dependencies
- ✅ Service registration
- ✅ Registry configuration
- ✅ Upgrade path support
- ✅ Code signing ready
- ✅ Group Policy deployment ready

---

## Installation Walkthrough

### Phase 1: Pre-Installation
1. Administrator privilege check
2. System requirements validation
3. Disk space verification
4. Docker Desktop detection
5. Optional dependency detection

### Phase 2: File Installation
1. Copy application files
2. Copy management scripts
3. Create configuration directories
4. Generate `.env` file
5. Set file permissions

### Phase 3: Configuration
1. Generate secure APP_KEY
2. Configure database connection
3. Create registry entries
4. Set environment variables
5. Configure Windows Task Scheduler

### Phase 4: Service Setup
1. Register Windows service (via NSSM)
2. Configure service startup
3. Create scheduled tasks
4. Setup firewall rules
5. Generate shortcuts

### Phase 5: Verification
1. Health check
2. Service status verification
3. Log review
4. Success confirmation

---

## Post-Installation

### Start Services
```cmd
C:\Program Files\Corex\scripts\start.bat
```

### Check Status
```cmd
C:\Program Files\Corex\scripts\status.bat
```

### Access Application
- **Web UI**: http://localhost
- **API**: http://localhost:8000/health
- **AI Gateway**: http://localhost:8001/health

### View Logs
```cmd
C:\Program Files\Corex\scripts\logs.bat
```

### Stop Services
```cmd
C:\Program Files\Corex\scripts\stop.bat
```

---

## Updates

### Manual Update
```powershell
.\updates\Update-Corex.ps1
```

### Check for Updates Only
```powershell
.\updates\Update-Corex.ps1 -CheckOnly
```

### Enable Auto-Updates
```powershell
.\updates\Update-Corex.ps1 -AutoUpdate
```

### Rollback
```powershell
.\updates\Update-Corex.ps1 -RollBack
```

---

## Uninstallation

### Via PowerShell
```powershell
.\Install-Corex.ps1 -Uninstall
```

### Via Control Panel
1. Settings → Apps → Apps & features
2. Search "Corex"
3. Click "Uninstall"

### Via Command Line
```cmd
msiexec /x Corex-Setup.msi
```

---

## Troubleshooting

### Installation Fails

1. Check logs:
   ```powershell
   Get-Content "$env:APPDATA\Corex\logs\install-*.log" -Tail 50
   ```

2. Run compatibility test:
   ```cmd
   C:\Program Files\Corex\scripts\test-compatibility.bat
   ```

3. Verify prerequisites:
   - Docker Desktop running: `docker ps`
   - Administrator privileges confirmed
   - Disk space available: `dir C:`

### Docker Not Found

```powershell
# Download and install Docker Desktop
Start-Process https://www.docker.com/products/docker-desktop

# Wait for installation, then try installer again
```

### Services Won't Start

```powershell
# Check service status
Get-Service CorexPlatform

# View detailed logs
C:\Program Files\Corex\scripts\logs.bat

# Try restarting
net stop CorexPlatform
net start CorexPlatform
```

### Port Already in Use

```cmd
REM Find process using port 8000
netstat -ano | findstr :8000

REM Kill process (replace PID)
taskkill /PID 12345 /F
```

---

## Building Installers for Distribution

### For End Users (Recommended: Inno Setup EXE)
1. Build using Inno Setup
2. Distribute: `CorexSetup-1.0.0.exe` (~40 MB)
3. Users run and follow GUI wizard
4. No technical knowledge required

### For Enterprise (Recommended: WiX MSI)
1. Build using WiX Toolset
2. Distribute: `Corex-Setup.msi` (~80 MB)
3. Deploy via Group Policy, SCCM, Intune
4. Silent installation supported
5. Optional: Code sign the MSI

### For Developers (PowerShell)
1. Distribute ZIP with PowerShell scripts
2. Users run: `.\Install-Corex.ps1`
3. Requires PowerShell knowledge
4. Most flexible for customization

---

## Environment Configuration

After installation, configure:

**File:** `C:\Program Files\Corex\backend\.env`

Required settings:
```bash
APP_KEY=base64:...
DB_PASSWORD=...
REDIS_PASSWORD=...
JWT_SECRET=...
SENTRY_LARAVEL_DSN=
SENTRY_DSN=
```

---

## Support & Documentation

- **Installation Guide**: [INSTALLER_GUIDE.md](./INSTALLER_GUIDE.md)
- **Windows Setup Guide**: [../SETUP.md](../SETUP.md)
- **Service Management**: [../README.md](../README.md)
- **Logs**: `%APPDATA%\Corex\logs\`
- **Issues**: GitHub repository

---

## CI/CD Integration

### GitHub Actions Build Example
```yaml
- name: Build installers
  run: |
    choco install innosetup -y
    choco install wixtoolset -y
    
    # Build Inno Setup EXE
    iscc.exe windows\installer\innosetup\CorexInstaller.iss
    
    # Build WiX MSI
    cd windows\installer\wix
    build.bat
```

---

## Version Management

Keep versions synchronized:
- `CorexInstaller.iss` - Line 6: `#define MyAppVersion`
- `Product.wxs` - Line 7: `Version="1.0.0.0"`
- `Install-Corex.ps1` - Line 53: `$AppVersion`
- `Update-Corex.ps1` - Line 60: `$CurrentVersion`

---

## License

Corex © Corex Development. See LICENSE file for details.

---

Last Updated: 2026-06-27
Version: 1.0.0 Installer Suite
