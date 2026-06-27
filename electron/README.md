# Corex Desktop App (Electron)

Native desktop application for the Corex AI-powered development platform.

## Architecture

```
electron/
├── main.js              # Electron main process (window, tray, menu, IPC, auto-updater)
├── preload.js           # Context bridge exposing safe native APIs
├── package.json         # Dependencies (electron, electron-builder, electron-updater)
├── build-config.js      # electron-builder configuration
├── installer.nsh        # NSIS installer customization
└── resources/           # Icons, entitlements, etc.
```

The Laravel backend runs as an embedded PHP server (`artisan serve`) inside the Electron app bundle.

## Development

```bash
cd electron

# Install dependencies
npm install

# Run in development mode (connects to existing local Laravel at localhost:8000)
npm run dev

# Run in production mode (starts embedded PHP server)
npm start
```

## Building for Windows

```bash
# Build Windows installer (NSIS)
npm run build:win

# Build Windows portable executable
# (both are produced by build:win)
```

Output in `electron/dist/`:
- `Corex-Setup-1.0.0-win-x64.exe` — NSIS installer
- `Corex-Portable-1.0.0-win-x64.exe` — Portable executable

## Build Options

```bash
# Set environment variables for code signing
$env:CODESIGN_CERT_PATH = "C:\certs\corex.pfx"
$env:CODESIGN_CERT_PASSWORD = "password"

# Set GitHub repo for auto-updates
$env:GITHUB_REPO_OWNER = "corex-dev"
$env:GITHUB_REPO = "corex"

# Build
npm run build:win
```

## Key Features

### Window
- 1400x900 default size (900x600 minimum)
- Center on screen
- Minimize to system tray
- Single-instance lock
- Custom title bar (frameless mode)

### Menu
- File: New, Open, Save operations
- Edit: Undo/Redo, Cut/Copy/Paste, Find/Replace
- View: Toggle sidebar/terminal/chat, zoom, fullscreen, DevTools
- AI: New chat, inline AI, code actions, model management
- Window: Minimize, Close
- Help: Docs, issues, updates, about

### System Tray
- Double-click to show/hide window
- Context menu: Open, New Chat, Check Updates, Quit
- Tooltip: "Corex"

### Auto-Update
- GitHub releases-based
- Checks on startup and every 24 hours
- Downloads automatically, installs on quit
- Uses electron-updater

### Drag & Drop
- Drop files from Explorer onto the editor area
- Opens text files in the Monaco editor
- Shows upload progress for larger files

### Native Dialogs
- Open File / Save File with file type filters
- Open Folder (directory picker)
- Message boxes (info, warning, error, confirm)

### Protocol Handler
- Registers `corex://` URL scheme
- Supports deep linking: `corex://open-project?path=...`

### File Associations
- PHP, JS, TS, Vue, JSON, YAML, Markdown, CSS, Blade, etc.

## Resources Directory

Place the following in `electron/resources/`:
- `icon.png` — Application icon (512x512)
- `icon.ico` — Windows icon (256x256, multi-res)
- `tray-icon.png` — Tray icon (16x16 or 32x32)
- `entitlements.mac.plist` — macOS sandbox entitlements

## Testing

```bash
# Test the installer
.\dist\Corex-Setup-1.0.0-win-x64.exe /SILENT

# Run the portable version
.\dist\Corex-Portable-1.0.0-win-x64.exe

# Check auto-update
curl -H "Accept: application/vnd.github.v3+json" \
  https://api.github.com/repos/corex-dev/corex/releases/latest
```
