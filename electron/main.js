const { app, BrowserWindow, Menu, Tray, nativeImage, dialog, Notification, ipcMain, shell, screen } = require('electron');
const path = require('path');
const { autoUpdater } = require('electron-updater');
const { ChildProcess, spawn } = require('child_process');

// ── Configuration ───────────────────────────────────────────────────────
const DEV = process.env.NODE_ENV === 'development';
const APP_NAME = 'Corex';
const APP_VERSION = '1.0.0';
const PHP_SERVER_PORT = process.env.NATIVEPHP_PORT || 8100;
const APP_URL = DEV ? 'http://localhost:8000' : `http://127.0.0.1:${PHP_SERVER_PORT}`;

let mainWindow = null;
let tray = null;
let phpProcess = null;

// ── Window Creation ─────────────────────────────────────────────────────
function createWindow() {
  const { width: screenWidth, height: screenHeight } = screen.getPrimaryDisplay().workAreaSize;

  mainWindow = new BrowserWindow({
    title: APP_NAME,
    width: Math.min(1400, screenWidth),
    height: Math.min(900, screenHeight),
    minWidth: 900,
    minHeight: 600,
    center: true,
    resizable: true,
    show: false,
    frame: true,
    backgroundColor: '#0f172a',
    icon: path.join(__dirname, 'resources', 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: true,
      spellcheck: true,
    },
  });

  // Build menu from config
  const menuTemplate = buildMenu();
  Menu.setApplicationMenu(Menu.buildFromTemplate(menuTemplate));

  mainWindow.loadURL(APP_URL);

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
    if (DEV) mainWindow.webContents.openDevTools({ mode: 'bottom' });
  });

  mainWindow.on('close', (e) => {
    // Minimize to tray instead of closing
    if (tray && !global.shouldQuit) {
      e.preventDefault();
      mainWindow.hide();
    }
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  // Handle external links
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith(APP_URL) || url.startsWith('http://127.0.0.1')) {
      return { action: 'allow' };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });
}

// ── Menu ─────────────────────────────────────────────────────────────────
function buildMenu() {
  return [
    {
      label: APP_NAME,
      submenu: [
        { label: `About ${APP_NAME}`, role: 'about' },
        { type: 'separator' },
        {
          label: 'Preferences...',
          accelerator: 'CmdOrCtrl+,',
          click: () => mainWindow?.webContents.send('menu-action', 'open-settings'),
        },
        { type: 'separator' },
        { role: 'services' },
        { type: 'separator' },
        { role: 'hide' },
        { role: 'hideOthers' },
        { role: 'unhide' },
        { type: 'separator' },
        { role: 'quit' },
      ],
    },
    {
      label: 'File',
      submenu: [
        {
          label: 'New File',
          accelerator: 'CmdOrCtrl+N',
          click: () => mainWindow?.webContents.send('menu-action', 'new-file'),
        },
        {
          label: 'Open File...',
          accelerator: 'CmdOrCtrl+O',
          click: async () => {
            const result = await dialog.showOpenDialog(mainWindow, {
              properties: ['openFile', 'multiSelections'],
              filters: [
                { name: 'All Supported Files', extensions: ['php', 'js', 'ts', 'vue', 'blade.php', 'json', 'md', 'css', 'html', 'py'] },
                { name: 'All Files', extensions: ['*'] },
              ],
            });
            if (!result.canceled) {
              mainWindow?.webContents.send('files-opened', result.filePaths);
            }
          },
        },
        {
          label: 'Open Folder...',
          accelerator: 'CmdOrCtrl+Shift+O',
          click: async () => {
            const result = await dialog.showOpenDialog(mainWindow, {
              properties: ['openDirectory'],
            });
            if (!result.canceled) {
              mainWindow?.webContents.send('folder-opened', result.filePaths[0]);
            }
          },
        },
        { type: 'separator' },
        { label: 'Save', accelerator: 'CmdOrCtrl+S', click: () => mainWindow?.webContents.send('menu-action', 'save-file') },
        { label: 'Save As...', accelerator: 'CmdOrCtrl+Shift+S', click: () => mainWindow?.webContents.send('menu-action', 'save-file-as') },
        { type: 'separator' },
        { label: 'Close File', accelerator: 'CmdOrCtrl+W', click: () => mainWindow?.webContents.send('menu-action', 'close-file') },
      ],
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' },
        { role: 'redo' },
        { type: 'separator' },
        { role: 'cut' },
        { role: 'copy' },
        { role: 'paste' },
        { role: 'selectAll' },
        { type: 'separator' },
        { label: 'Find', accelerator: 'CmdOrCtrl+F', click: () => mainWindow?.webContents.send('menu-action', 'open-find') },
        { label: 'Find in Files', accelerator: 'CmdOrCtrl+Shift+F', click: () => mainWindow?.webContents.send('menu-action', 'open-find-in-files') },
        { label: 'Replace', accelerator: 'CmdOrCtrl+H', click: () => mainWindow?.webContents.send('menu-action', 'open-replace') },
      ],
    },
    {
      label: 'View',
      submenu: [
        { label: 'Command Palette...', accelerator: 'CmdOrCtrl+Shift+P', click: () => mainWindow?.webContents.send('menu-action', 'open-command-palette') },
        { type: 'separator' },
        { label: 'Toggle Sidebar', accelerator: 'CmdOrCtrl+B', click: () => mainWindow?.webContents.send('menu-action', 'toggle-sidebar') },
        { label: 'Toggle Terminal', accelerator: 'CmdOrCtrl+`', click: () => mainWindow?.webContents.send('menu-action', 'toggle-terminal') },
        { label: 'Toggle Chat', accelerator: 'CmdOrCtrl+Shift+I', click: () => mainWindow?.webContents.send('menu-action', 'toggle-chat') },
        { type: 'separator' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { role: 'resetZoom' },
        { type: 'separator' },
        { role: 'togglefullscreen' },
        { type: 'separator' },
        { label: 'Toggle DevTools', accelerator: 'F12', role: 'toggleDevTools' },
      ],
    },
    {
      label: 'AI',
      submenu: [
        { label: 'New Chat', accelerator: 'CmdOrCtrl+Shift+L', click: () => mainWindow?.webContents.send('menu-action', 'new-chat') },
        { label: 'Ask AI...', accelerator: 'CmdOrCtrl+I', click: () => mainWindow?.webContents.send('menu-action', 'ai-inline') },
        { type: 'separator' },
        { label: 'Explain Code', click: () => mainWindow?.webContents.send('menu-action', 'ai-explain') },
        { label: 'Refactor Code', click: () => mainWindow?.webContents.send('menu-action', 'ai-refactor') },
        { label: 'Generate Tests', click: () => mainWindow?.webContents.send('menu-action', 'ai-generate-tests') },
        { label: 'Review Code', click: () => mainWindow?.webContents.send('menu-action', 'ai-review') },
        { type: 'separator' },
        { label: 'Manage Models...', click: () => mainWindow?.webContents.send('menu-action', 'manage-ai-models') },
      ],
    },
    {
      label: 'Window',
      submenu: [
        { role: 'minimize' },
        { role: 'close' },
        { type: 'separator' },
        { role: 'front' },
      ],
    },
    {
      label: 'Help',
      submenu: [
        { label: 'Documentation', accelerator: 'F1', click: () => shell.openExternal('https://corex.dev/docs') },
        { label: 'Report Issue', click: () => shell.openExternal('https://github.com/corex-dev/corex/issues') },
        { type: 'separator' },
        { label: 'Check for Updates...', click: () => mainWindow?.webContents.send('menu-action', 'check-updates') },
        { label: `About ${APP_NAME}`, click: () => mainWindow?.webContents.send('menu-action', 'open-about') },
      ],
    },
  ];
}

// ── System Tray ──────────────────────────────────────────────────────────
function createTray() {
  const iconPath = path.join(__dirname, 'resources', 'tray-icon.png');
  let trayIcon;

  try {
    trayIcon = nativeImage.createFromPath(iconPath);
    trayIcon = trayIcon.resize({ width: 16, height: 16 });
  } catch {
    // Fallback: create a 16x16 empty image
    trayIcon = nativeImage.createEmpty();
  }

  tray = new Tray(trayIcon);
  tray.setToolTip(APP_NAME);

  const contextMenu = Menu.buildFromTemplate([
    {
      label: `Open ${APP_NAME}`,
      click: () => mainWindow?.show() || createWindow(),
    },
    { type: 'separator' },
    {
      label: 'Check for Updates...',
      click: () => autoUpdater.checkForUpdates(),
    },
    { type: 'separator' },
    {
      label: 'Quit',
      click: () => {
        global.shouldQuit = true;
        app.quit();
      },
    },
  ]);

  tray.setContextMenu(contextMenu);
  tray.on('double-click', () => mainWindow?.show() || createWindow());
}

// ── PHP Server ───────────────────────────────────────────────────────────
function startPhpServer() {
  if (DEV) return;

  const phpBinary = path.join(process.resourcesPath, 'php', 'php.exe');
  const artisanPath = path.join(process.resourcesPath, 'app', 'artisan');

  phpProcess = spawn(phpBinary, [
    'artisan',
    'serve',
    `--host=127.0.0.1`,
    `--port=${PHP_SERVER_PORT}`,
  ], {
    cwd: path.join(process.resourcesPath, 'app'),
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  phpProcess.stdout.on('data', (data) => {
    console.log(`[PHP] ${data}`);
  });

  phpProcess.stderr.on('data', (data) => {
    console.error(`[PHP] ${data}`);
  });

  phpProcess.on('exit', (code) => {
    console.log(`PHP server exited with code ${code}`);
  });
}

function stopPhpServer() {
  if (phpProcess) {
    phpProcess.kill();
    phpProcess = null;
  }
}

// ── IPC Handlers ─────────────────────────────────────────────────────────
function setupIPC() {
  // Drag & drop files
  ipcMain.handle('get-file-path', (event, filePath) => {
    return filePath;
  });

  // Window management
  ipcMain.on('window-minimize', () => mainWindow?.minimize());
  ipcMain.on('window-maximize', () => {
    if (mainWindow?.isMaximized()) {
      mainWindow.unmaximize();
    } else {
      mainWindow?.maximize();
    }
  });
  ipcMain.on('window-close', () => mainWindow?.close());
  ipcMain.on('window-set-title', (_, title) => mainWindow?.setTitle(title));

  // System dialogs
  ipcMain.handle('dialog-open-file', async (_, options) => {
    return await dialog.showOpenDialog(mainWindow, options);
  });

  ipcMain.handle('dialog-save-file', async (_, options) => {
    return await dialog.showSaveDialog(mainWindow, options);
  });

  ipcMain.handle('dialog-message', async (_, options) => {
    return await dialog.showMessageBox(mainWindow, options);
  });

  // Notifications
  ipcMain.on('show-notification', (_, { title, body, silent, actions, onClick }) => {
    const notif = new Notification({ title, body, silent });
    if (actions && Notification.isSupported()) {
      notif.actions = actions;
    }
    notif.on('click', () => {
      mainWindow?.show();
      mainWindow?.focus();
      mainWindow?.webContents.send('notification-clicked', { title, body });
    });
    notif.on('action', (event, index) => {
      mainWindow?.webContents.send('notification-action', { title, body, index });
    });
    notif.show();
  });

  // File system operations (read/write from preload)
  const fs = require('fs');
  ipcMain.handle('fs-read-file', async (_, filePath) => {
    return await fs.promises.readFile(filePath, 'utf-8');
  });

  ipcMain.handle('fs-write-file', async (_, filePath, content) => {
    await fs.promises.writeFile(filePath, content, 'utf-8');
  });

  ipcMain.handle('fs-readdir', async (_, dirPath) => {
    return await fs.promises.readdir(dirPath, { withFileTypes: true });
  });

  ipcMain.handle('fs-stat', async (_, filePath) => {
    const stat = await fs.promises.stat(filePath);
    return {
      size: stat.size,
      isDirectory: stat.isDirectory(),
      isFile: stat.isFile(),
      modified: stat.mtime.toISOString(),
      created: stat.birthtime.toISOString(),
    };
  });

  ipcMain.handle('fs-exists', async (_, filePath) => {
    try {
      await fs.promises.access(filePath);
      return true;
    } catch {
      return false;
    }
  });

  ipcMain.handle('fs-mkdir', async (_, dirPath) => {
    await fs.promises.mkdir(dirPath, { recursive: true });
  });

  // Application info
  ipcMain.handle('get-app-version', () => app.getVersion());
  ipcMain.handle('get-platform', () => process.platform);
  ipcMain.handle('get-user-data-path', () => app.getPath('userData'));

  // Open folder in system explorer
  ipcMain.on('show-item-in-folder', (_, itemPath) => {
    shell.showItemInFolder(itemPath);
  });

  // Open external URL
  ipcMain.on('open-external', (_, url) => {
    shell.openExternal(url);
  });

  // Taskbar progress
  ipcMain.on('set-progress-bar', (_, progress) => {
    if (mainWindow) {
      if (progress < 0) {
        mainWindow.setProgressBar(-1); // Indeterminate
      } else {
        mainWindow.setProgressBar(Math.min(1, Math.max(0, progress)));
      }
    }
  });

  ipcMain.on('set-progress-bar-mode', (_, mode) => {
    if (mainWindow) {
      mainWindow.setProgressBar(-1, { mode: mode || 'normal' });
    }
  });

  // Taskbar overlay icon
  ipcMain.on('set-overlay-icon', (_, { icon, description }) => {
    if (mainWindow && icon) {
      const img = nativeImage.createFromPath(icon);
      mainWindow.setOverlayIcon(img, description || '');
    } else if (mainWindow) {
      mainWindow.setOverlayIcon(null, '');
    }
  });

  // Flash taskbar
  ipcMain.on('flash-frame', (_, flash = true) => {
    if (mainWindow) {
      if (flash) {
        mainWindow.flashFrame(true);
        setTimeout(() => mainWindow?.flashFrame(false), 3000);
      } else {
        mainWindow.flashFrame(false);
      }
    }
  });

  // Jump List
  ipcMain.handle('get-jump-list', () => {
    try {
      return app.getJumpListSettings();
    } catch {
      return null;
    }
  });

  ipcMain.handle('set-jump-list', (_, categories) => {
    try {
      const jumpList = [];
      for (const cat of categories) {
        const items = (cat.items || []).map(item => ({
          type: 'task',
          title: item.title,
          program: process.execPath,
          args: item.args || '',
          iconPath: item.icon || process.execPath,
          iconIndex: 0,
          description: item.description || '',
        }));
        jumpList.push({
          type: 'custom',
          name: cat.name || 'Tasks',
          items,
        });
      }
      app.setJumpList(jumpList);
      return true;
    } catch {
      return false;
    }
  });

  ipcMain.handle('clear-jump-list', () => {
    try {
      app.setJumpList([]);
      return true;
    } catch {
      return false;
    }
  });

  // Recent documents
  ipcMain.on('add-recent-document', (_, filePath) => {
    app.addRecentDocument(filePath);
  });

  ipcMain.on('clear-recent-documents', () => {
    app.clearRecentDocuments();
  });

  // Badge counter (macOS) / overlay
  ipcMain.on('set-badge', (_, count) => {
    if (count > 0) {
      app.setBadgeCount(count);
    } else {
      app.setBadgeCount(0);
    }
  });

  // Auto-updater
  ipcMain.handle('check-for-updates', async () => {
    const result = await autoUpdater.checkForUpdates();
    return {
      currentVersion: app.getVersion(),
      updateAvailable: result?.updateInfo ? true : false,
      latestVersion: result?.updateInfo?.version,
      releaseNotes: result?.updateInfo?.releaseNotes,
    };
  });
}

// ── Auto Updater ─────────────────────────────────────────────────────────
function setupAutoUpdater() {
  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  autoUpdater.on('update-available', (info) => {
    mainWindow?.webContents.send('update-available', info);
    if (Notification.isSupported()) {
      new Notification({
        title: 'Update Available',
        body: `Version ${info.version} is downloading...`,
        silent: true,
      }).show();
    }
  });

  autoUpdater.on('update-downloaded', (info) => {
    mainWindow?.webContents.send('update-downloaded', info);
  });

  autoUpdater.on('error', (err) => {
    console.error(`[AutoUpdater] ${err.message}`);
  });
}

// ── App Lifecycle ────────────────────────────────────────────────────────
app.whenReady().then(() => {
  setupIPC();
  setupAutoUpdater();
  startPhpServer();
  createWindow();
  createTray();

  // Check for updates on startup (non-blocking)
  setTimeout(() => autoUpdater.checkForUpdates().catch(() => {}), 5000);

  app.on('activate', () => {
    if (mainWindow === null) createWindow();
    else mainWindow.show();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('will-quit', () => {
  stopPhpServer();
});

app.on('before-quit', () => {
  global.shouldQuit = true;
});

// ── Handle second instance (single instance lock) ────────────────────────
const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
  app.quit();
} else {
  app.on('second-instance', (event, commandLine) => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.show();
      mainWindow.focus();
    }
  });
}
