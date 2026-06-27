const { contextBridge, ipcRenderer } = require('electron');

// Expose a safe API to the renderer process
contextBridge.exposeInMainWorld('native', {
  // ── File System ───────────────────────────────────────────────────
  fs: {
    readFile: (filePath) => ipcRenderer.invoke('fs-read-file', filePath),
    writeFile: (filePath, content) => ipcRenderer.invoke('fs-write-file', filePath, content),
    readDir: (dirPath) => ipcRenderer.invoke('fs-readdir', dirPath),
    stat: (filePath) => ipcRenderer.invoke('fs-stat', filePath),
    exists: (filePath) => ipcRenderer.invoke('fs-exists', filePath),
    mkdir: (dirPath) => ipcRenderer.invoke('fs-mkdir', dirPath),
  },

  // ── Window ────────────────────────────────────────────────────────
  window: {
    minimize: () => ipcRenderer.send('window-minimize'),
    maximize: () => ipcRenderer.send('window-maximize'),
    close: () => ipcRenderer.send('window-close'),
    setTitle: (title) => ipcRenderer.send('window-set-title', title),
  },

  // ── Dialogs ───────────────────────────────────────────────────────
  dialog: {
    openFile: (options) => ipcRenderer.invoke('dialog-open-file', options),
    saveFile: (options) => ipcRenderer.invoke('dialog-save-file', options),
    message: (options) => ipcRenderer.invoke('dialog-message', options),
  },

  // ── Notifications ─────────────────────────────────────────────────
  notify: (title, body, silent = false) => {
    ipcRenderer.send('show-notification', { title, body, silent });
  },

  // ── App Info ──────────────────────────────────────────────────────
  app: {
    getVersion: () => ipcRenderer.invoke('get-app-version'),
    getPlatform: () => ipcRenderer.invoke('get-platform'),
    getUserDataPath: () => ipcRenderer.invoke('get-user-data-path'),
    checkForUpdates: () => ipcRenderer.invoke('check-for-updates'),
  },

  // ── Shell ─────────────────────────────────────────────────────────
  shell: {
    showItemInFolder: (itemPath) => ipcRenderer.send('show-item-in-folder', itemPath),
    openExternal: (url) => ipcRenderer.send('open-external', url),
  },

  // ── Events from main process ──────────────────────────────────────
  on: (channel, callback) => {
    const validChannels = [
      'menu-action', 'files-opened', 'folder-opened',
      'update-available', 'update-downloaded', 'deep-link',
    ];
    if (validChannels.includes(channel)) {
      const subscription = (_event, ...args) => callback(...args);
      ipcRenderer.on(channel, subscription);
      return () => ipcRenderer.removeListener(channel, subscription);
    }
  },

  // ── Drag & Drop ───────────────────────────────────────────────────
  getFilePath: (filePath) => ipcRenderer.invoke('get-file-path', filePath),
});
