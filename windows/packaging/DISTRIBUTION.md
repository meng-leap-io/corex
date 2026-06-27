# Corex Distribution Guide

## Directory Structure

After build, the distribution directory contains:

```
Corex/
├── app/
│   ├── backend/              # Laravel PHP application
│   │   ├── app/
│   │   ├── bootstrap/
│   │   ├── config/
│   │   ├── database/
│   │   ├── public/
│   │   ├── resources/
│   │   ├── routes/
│   │   ├── storage/
│   │   ├── vendor/
│   │   ├── artisan
│   │   └── .env
│   └── ai-gateway/           # Python FastAPI AI Gateway
│       ├── app/
│       ├── main.py
│       ├── .venv/            # Portable virtual environment
│       └── requirements.txt
├── electron/                  # Electron desktop shell
│   ├── main.js
│   ├── preload.js
│   └── package.json
├── php/                       # Portable PHP 8.3
│   ├── php.exe
│   ├── php-cgi.exe
│   ├── php8ts.dll
│   ├── ext/                  # Extensions
│   ├── composer.phar
│   └── php.ini
├── python/                    # Portable Python 3.12
│   ├── python.exe
│   ├── python312.dll
│   ├── Scripts/              # pip, uvicorn, etc.
│   └── Lib/
├── nginx/                     # Portable Nginx
│   ├── nginx.exe
│   └── conf/
├── redis/                     # Portable Redis
│   ├── redis-server.exe
│   └── redis-cli.exe
├── nodejs/                    # Portable Node.js 20
│   ├── node.exe
│   ├── npm.cmd
│   └── npx.cmd
├── nssm/                      # NSSM service manager
├── data/                      # Runtime data
│   ├── db/                   # SQLite databases
│   ├── cache/
│   └── pids/
├── logs/                      # Log output
├── scripts/                   # Management scripts
├── services/                  # Windows service wrappers
├── resources/                 # Icons and assets
├── start-corex.bat            # Launch Corex (double-click)
├── stop-corex.bat             # Stop Corex
├── start-corex.ps1            # PowerShell launcher
├── version.json               # Build metadata
├── icon.ico
└── corex.exe                  # Electron shell (electron-builder output)
```

## Build Pipeline

```powershell
# Full release build
.\build.ps1 -Config Release -Arch x64

# Quick debug build (skip downloads if cached)
.\build.ps1 -Config Debug -Arch x64 -SkipDownloads

# Build with installer
.\build.ps1 -Config Release -Arch x64 -InnoSetup

# Build with installer + signing
.\build.ps1 -Config Release -Arch x64 -InnoSetup -Sign

# Build portable zip only
.\build.ps1 -Config Release -Arch x64 -Portable
```

## Prerequisites

| Tool | Required For | Download |
|------|-------------|----------|
| PowerShell 5.1+ | All scripts | Built-in to Windows 10/11 |
| Git | Version info | https://git-scm.com |
| Composer | PHP backend | Bundled in build |
| Inno Setup 6+ | Installer creation | https://jrsoftware.org/isdl.php |
| Windows SDK | Code signing | https://developer.microsoft.com/windows-sdk |
| Code Signing Cert | Signing | DigiCert, Sectigo, etc. |

## Installer Types

### 1. Electron Builder (NSIS)
- `npm run build:win` from `electron/` directory
- Produces: `Corex-Setup-1.0.0-win-x64.exe`
- Managed via `electron/builder-config.js`
- Uses `electron/installer.nsh` for custom NSIS logic

### 2. Inno Setup (Full)
- `build.ps1 -InnoSetup`
- Produces: `Output/Corex-Setup-1.0.0-x64.exe`
- Everything bundled: no external dependencies
- Handles: registry, shortcuts, protocol, uninstall

### 3. Portable (No Install)
- `build.ps1 -Portable`
- Produces: `Corex-1.0.0-x64-portable.zip`
- Extract anywhere and run `start-corex.bat`
- No registry changes, no admin required

## Code Signing

```powershell
# Sign a single file
.\sign\sign.ps1 -Path .\dist\Corex-Setup.exe -PfxPath .\cert.pfx

# Sign all executables recursively
.\sign\sign.ps1 -Path .\dist -Recursive -PfxPath .\cert.pfx

# Sign with Azure Key Vault (configure env vars first)
.\sign\sign.ps1 -Path .\dist\Corex-Setup.exe -UseAzureKeyVault
```

## Self-Update Flow

1. Desktop app calls `GET /_native/updates/check` every 24h
2. PHP backend checks GitHub releases API
3. If newer version found, downloads installer to temp dir
4. Electron shows notification with release notes
5. On user approval, launches installer silently
6. Installer replaces app files, keeps user data in %APPDATA%

## Version Management

- `version.json` at app root contains build metadata
- `electron/package.json` `version` field is the canonical version
- Git tags should match the version (e.g. `v1.0.0`)
- Release workflow in `.github/workflows/release.yml` automates builds

## User Data

All user data is stored in `%LOCALAPPDATA%\Corex`:
- `database/` — SQLite databases
- `cache/` — Application cache
- `logs/` — Log files
- `config/` — User preferences

This is preserved across reinstalls and updates.
