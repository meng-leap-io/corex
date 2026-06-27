# Corex Windows Installer Guide

Complete guide to building and deploying Windows installers for Corex.

## Overview

This directory contains three installer solutions:

1. **WiX Toolset** (`wix/`) - Professional MSI installer for enterprise deployment
2. **Inno Setup** (`innosetup/`) - Lightweight EXE installer for general users
3. **PowerShell** (`powershell/`) - Script-based installation framework

## Quick Start

### Option 1: PowerShell (Easiest)

```powershell
# Run as Administrator
.\powershell\Install-Corex.ps1

# Uninstall
.\powershell\Install-Corex.ps1 -Uninstall
```

### Option 2: Inno Setup (Recommended for End Users)

1. Install Inno Setup from: https://www.jrsoftware.org/isdl.php
2. Open `innosetup\CorexInstaller.iss`
3. Click "Build" → "Compile"
4. Output: `innosetup\Output\CorexSetup-1.0.0.exe`

### Option 3: WiX Toolset (Professional MSI)

1. Install WiX from: https://wixtoolset.org/
2. Run: `wix\build.bat`
3. Output: `wix\bin\Corex-Setup.msi`

---

## Installation Methods

### PowerShell Installation

**Standard Installation:**
```powershell
cd windows\installer
.\powershell\Install-Corex.ps1
```

**Custom Installation Path:**
```powershell
.\powershell\Install-Corex.ps1 -InstallPath "D:\Applications\Corex"
```

**Unattended Installation (for scripting):**
```powershell
.\powershell\Install-Corex.ps1 -Unattended -SkipValidation
```

**Uninstall:**
```powershell
.\powershell\Install-Corex.ps1 -Uninstall
```

### Inno Setup Installation

**Interactive (GUI):**
```cmd
CorexSetup-1.0.0.exe
```

**Silent Installation:**
```cmd
CorexSetup-1.0.0.exe /SILENT /NORESTART
```

**Installation with custom path:**
```cmd
CorexSetup-1.0.0.exe /D=C:\MyCorex
```

**Uninstall (silent):**
```cmd
C:\Program Files\Corex\unins000.exe /SILENT /NORESTART
```

### WiX MSI Installation

**Interactive (GUI):**
```cmd
msiexec /i Corex-Setup.msi
```

**Silent Installation:**
```cmd
msiexec /i Corex-Setup.msi /quiet /norestart
```

**With custom properties:**
```cmd
msiexec /i Corex-Setup.msi INSTALLFOLDER=C:\Corex ALLUSERS=1
```

**Uninstall:**
```cmd
msiexec /x Corex-Setup.msi /quiet
```

---

## System Requirements

All installers verify:

- ✅ Windows 10 (build 19041+) or Windows 11
- ✅ 8 GB RAM minimum (16 GB recommended)
- ✅ 30 GB disk space
- ✅ Administrator privileges
- ✅ Docker Desktop 20.10+ installed
- ⚠️ PHP 8.3+ (optional, can use Docker)
- ⚠️ Python 3.12+ (optional, can use Docker)

---

## Dependency Handling

### Docker Desktop

**Required:** All installers verify Docker Desktop is installed.

If missing, installers will:
- Show download link: https://www.docker.com/products/docker-desktop
- Pause for manual installation
- Resume after Docker is installed

### PHP & Python

**Optional:** Installers detect if PHP/Python are in PATH.

If missing:
- Installers recommend using Docker images instead
- Installation continues (Docker containers will be used)
- Can be installed later if needed

### Redis

Redis runs in Docker container as part of `docker-compose.yml`

### Ollama (Optional)

Included as optional feature for local AI model support.

---

## Building Installers

### Prerequisites

- PowerShell 5.1+
- Windows 10/11
- Administrator privileges

### For WiX (Professional MSI)

1. **Install WiX Toolset 3.14+**
   - Download: https://wixtoolset.org/
   - Install to default location

2. **Build MSI**
   ```batch
   cd windows\installer\wix
   build.bat
   ```

3. **Output**
   - File: `bin\Corex-Setup.msi`
   - Size: ~50-100 MB (depending on dependencies)

4. **Code Signing (Optional but Recommended)**
   ```batch
   signtool sign /f cert.pfx /p password /t http://timestamp.server /d "Corex" ^
     Corex-Setup.msi
   ```

### For Inno Setup (Lightweight EXE)

1. **Install Inno Setup**
   - Download: https://www.jrsoftware.org/isdl.php
   - Standard installation

2. **Build EXE**
   - Open `CorexInstaller.iss`
   - Click: Build → Compile

3. **Output**
   - File: `Output\CorexSetup-1.0.0.exe`
   - Size: ~30-50 MB

4. **Code Signing (Optional)**
   ```batch
   "C:\Program Files\OpenDNS\Authenticode\signtool" sign /f cert.pfx /p password ^
     CorexSetup-1.0.0.exe
   ```

### For PowerShell (Direct Installation)

```powershell
# No build needed - ready to use immediately
# Can be distributed as ZIP file with:
# - Install-Corex.ps1
# - All application files
```

---

## Installation Workflow

### Phase 1: Pre-Installation
1. ✅ Check Windows version
2. ✅ Verify RAM and disk space
3. ✅ Verify administrator privileges
4. ✅ Check Docker Desktop installation
5. ⚠️ Detect optional dependencies

### Phase 2: Installation
1. Copy application files to `C:\Program Files\Corex`
2. Copy management scripts
3. Create `.env` file from template
4. Generate secure `APP_KEY`
5. Create log directories

### Phase 3: Registration
1. Add Windows registry entries
2. Create desktop shortcuts
3. Create start menu shortcuts
4. Register Windows services (if NSSM available)
5. Configure Windows Task Scheduler jobs

### Phase 4: Post-Installation
1. Create Corex Manager shortcut
2. Open documentation
3. Show next steps
4. Log installation details

---

## Uninstallation

### Via Control Panel (EXE/MSI)
- Settings → Apps → Apps & features
- Search "Corex"
- Click "Uninstall"

### Via PowerShell
```powershell
.\powershell\Install-Corex.ps1 -Uninstall
```

### Via Command Line (MSI)
```cmd
msiexec /x Corex-Setup.msi
```

### Manual Uninstall (if needed)
```powershell
# 1. Stop services
net stop CorexPlatform

# 2. Uninstall service
nssm remove CorexPlatform confirm

# 3. Remove registry
reg delete "HKLM\Software\Corex" /f

# 4. Delete installation directory
rmdir /s /q "C:\Program Files\Corex"
```

---

## Updates and Auto-Updates

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
.\updates\Update-Corex.ps1 -AutoUpdate -AutoUpdateInterval 7
```

This creates a Windows Task Scheduler job that checks daily for updates.

### Rollback to Previous Version

```powershell
.\updates\Update-Corex.ps1 -RollBack
```

Restores from latest backup created before the last update.

---

## Logging and Diagnostics

### Installation Logs

Stored in: `%APPDATA%\Corex\logs\`

- `install-YYYYMMDD-HHMMSS.log` - Installation details
- `updates.log` - Update history
- `task-scheduler.log` - Task execution logs

### View Installation Log

```powershell
Get-Content "$env:APPDATA\Corex\logs\install-*.log" -Tail 50
```

### Enable Verbose Logging

```powershell
.\powershell\Install-Corex.ps1 -Verbose
```

---

## Troubleshooting

### Installation Fails

1. **Check logs:**
   ```powershell
   Get-Content "$env:APPDATA\Corex\logs\install-*.log"
   ```

2. **Run compatibility test:**
   ```powershell
   .\windows\test-compatibility.bat
   ```

3. **Verify prerequisites:**
   - Docker Desktop running: `docker ps`
   - Administrator privileges: `whoami /priv | findstr Admin`
   - Disk space: `dir C:\ | findstr "bytes free"`

### Docker Not Found

```powershell
# Download and install Docker Desktop
Start-Process https://www.docker.com/products/docker-desktop

# Wait for installation, then run installer again
```

### Port Already in Use

Corex uses ports: 80 (HTTP), 443 (HTTPS), 5432 (PostgreSQL), 6379 (Redis)

```powershell
# Find process using port 8000
netstat -ano | findstr :8000

# Kill process
taskkill /PID 12345 /F
```

---

## Distribution

### For End Users (Recommended: Inno Setup)
- Distribute: `CorexSetup-1.0.0.exe`
- Size: ~40 MB
- No installation of build tools needed

### For Enterprise (Recommended: WiX MSI)
- Distribute: `Corex-Setup.msi`
- Size: ~80 MB
- Can be deployed via Group Policy
- Supports silent installation
- Code signing recommended

### For Developers (PowerShell)
- Distribute: ZIP with PowerShell scripts
- Size: ~100-200 MB (includes all source files)
- Requires PowerShell knowledge

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Build Installers
on: [push, pull_request]

jobs:
  build:
    runs-on: windows-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Inno Setup installer
        run: |
          choco install innosetup -y
          iscc.exe windows\installer\innosetup\CorexInstaller.iss
      
      - name: Build WiX MSI
        run: |
          choco install wixtoolset -y
          cd windows\installer\wix
          build.bat
      
      - name: Upload artifacts
        uses: actions/upload-artifact@v3
        with:
          name: installers
          path: |
            windows\installer\innosetup\Output\
            windows\installer\wix\bin\
```

---

## Version Management

Version is defined in:
- `windows\installer\innosetup\CorexInstaller.iss` - Line 6: `#define MyAppVersion`
- `windows\installer\wix\Product.wxs` - Line 7: `Version="1.0.0.0"`
- `windows\installer\powershell\Install-Corex.ps1` - Line 53: `$AppVersion`
- `windows\installer\updates\Update-Corex.ps1` - Line 60: `$CurrentVersion`

Update all files when releasing new version.

---

## Support

- Documentation: [Windows Setup Guide](../SETUP.md)
- Scripts: [Windows Management Scripts](../)
- Issues: Report on GitHub
- Logs: Check installation logs in `%APPDATA%\Corex\logs\`

---

Last Updated: 2026-06-27
Version: 1.0.0 Installer Suite
