/**
 * Corex Native Desktop Bridge
 *
 * Alpine.js plugin that bridges Laravel Blade/Alpine frontend with
 * NativePHP / Electron native APIs.
 *
 * Provides:
 *   - $native  : Alpine magic method for accessing native APIs
 *   - x-native : directive for declarative native bindings
 *   - Desktop IPC via HTTP to _native/* routes + Electron preload bridge
 */

import Alpine from 'alpinejs'

const DESKTOP_ROUTES = '/_native'

// ── Detect if running inside Electron ─────────────────────────────────
const isElectron = typeof window !== 'undefined' &&
  window.navigator?.userAgent?.toLowerCase().includes('electron')

const hasNativeBridge = typeof window.native !== 'undefined'

// ── HTTP client for PHP-bridged native calls ──────────────────────────
async function nativeFetch(endpoint, options = {}) {
  const token = localStorage.getItem('token')
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  }
  if (token) headers['Authorization'] = `Bearer ${token}`
  headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || ''

  const res = await fetch(`${DESKTOP_ROUTES}${endpoint}`, {
    method: options.method || 'POST',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  })

  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: res.statusText }))
    throw new Error(err.error || err.message || `HTTP ${res.status}`)
  }

  return res.json()
}

// ── Helper: trigger menu actions ──────────────────────────────────────
const menuActionHandlers = {}

function onMenuAction(action, handler) {
  menuActionHandlers[action] = handler
}

// ── Alpine Plugin ─────────────────────────────────────────────────────
export default function (Alpine) {
  // ── Magic: $native ──────────────────────────────────────────────
  Alpine.magic('native', () => ({
    // Check if running as desktop app
    get isDesktop() {
      return isElectron
    },

    // Check if native bridge is available
    get isAvailable() {
      return hasNativeBridge
    },

    // Platform info
    get platform() {
      if (isElectron && hasNativeBridge) {
        return window.native.app.getPlatform()
      }
      return navigator.platform
    },

    // ── File System ──────────────────────────────────────────────
    fs: {
      async list(path, showHidden = false) {
        const params = new URLSearchParams({ path, showHidden })
        return nativeFetch(`/files/list?${params}`, { method: 'GET' })
      },
      async tree(path, depth = 3) {
        const params = new URLSearchParams({ path, depth })
        return nativeFetch(`/files/tree?${params}`, { method: 'GET' })
      },
      async read(path) {
        const params = new URLSearchParams({ path })
        return nativeFetch(`/files/read?${params}`, { method: 'GET' })
      },
      async write(path, content) {
        return nativeFetch('/files/write', { body: { path, content } })
      },
      async create(parent, name, type = 'file') {
        return nativeFetch('/files/create', { body: { parent, name, type } })
      },
      async rename(path, name) {
        return nativeFetch('/files/rename', { body: { path, name } })
      },
      async delete(path) {
        return nativeFetch('/files/delete', { body: { path } })
      },
      async duplicate(path) {
        return nativeFetch('/files/duplicate', { body: { path } })
      },
      async move(from, to) {
        return nativeFetch('/files/move', { body: { from, to } })
      },
      async search(root, query) {
        const params = new URLSearchParams({ root, query })
        return nativeFetch(`/files/search?${params}`, { method: 'GET' })
      },
      async info(path) {
        const params = new URLSearchParams({ path })
        return nativeFetch(`/files/info?${params}`, { method: 'GET' })
      },

      // Direct Electron FS access (bypasses PHP server)
      get native() {
        if (!hasNativeBridge) return null
        return window.native.fs
      },
    },

    // ── Projects ─────────────────────────────────────────────────
    projects: {
      async recent() {
        return nativeFetch('/projects/recent', { method: 'GET' })
      },
      async open(path) {
        return nativeFetch('/projects/open', { body: { path } })
      },
      async create(parent, name) {
        return nativeFetch('/projects/create', { body: { parent, name } })
      },
      async close() {
        return nativeFetch('/projects/close')
      },
      async current() {
        return nativeFetch('/projects/current', { method: 'GET' })
      },
      async settings(path) {
        const params = path ? new URLSearchParams({ path }) : ''
        return nativeFetch(`/projects/settings?${params}`, { method: 'GET' })
      },
      async updateSettings(path, settings) {
        return nativeFetch('/projects/settings', { body: { path, settings } })
      },
    },

    // ── Native Dialogs ───────────────────────────────────────────
    dialog: {
      async openFile(options = {}) {
        if (hasNativeBridge) {
          return window.native.dialog.openFile({
            title: options.title || 'Open File',
            filters: options.filters,
            properties: ['openFile', ...(options.multi ? ['multiSelections'] : [])],
          })
        }
        return nativeFetch('/dialog/open-file', { body: options })
      },
      async saveFile(options = {}) {
        if (hasNativeBridge) {
          return window.native.dialog.saveFile({
            title: options.title || 'Save File',
            filters: options.filters,
            defaultPath: options.defaultPath,
          })
        }
        return nativeFetch('/dialog/save-file', { body: options })
      },
      async openFolder(options = {}) {
        if (hasNativeBridge) {
          return window.native.dialog.openFile({
            title: options.title || 'Open Folder',
            properties: ['openDirectory'],
          })
        }
        return nativeFetch('/dialog/open-folder', { body: options })
      },
      async message(options = {}) {
        if (hasNativeBridge) {
          return window.native.dialog.message({
            type: options.type || 'info',
            title: options.title || 'Corex',
            message: options.message,
            buttons: options.buttons || ['OK'],
          })
        }
        return nativeFetch('/dialog/message', { body: options })
      },
    },

    // ── Notifications ────────────────────────────────────────────
    notify(title, message, options = {}) {
      if (hasNativeBridge) {
        window.native.notify(title, message, options.silent)
      }
      // Also try browser Notification API
      if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, { body: message, silent: options.silent })
      }
    },

    // ── Window ───────────────────────────────────────────────────
    window: {
      minimize() {
        if (hasNativeBridge) window.native.window.minimize()
        else nativeFetch('/window/minimize')
      },
      maximize() {
        if (hasNativeBridge) window.native.window.maximize()
        else nativeFetch('/window/maximize')
      },
      close() {
        if (hasNativeBridge) window.native.window.close()
        else nativeFetch('/window/close')
      },
      setTitle(title) {
        if (hasNativeBridge) window.native.window.setTitle(title)
        else nativeFetch('/window/set-title', { body: { title } })
      },
    },

    // ── App Info ─────────────────────────────────────────────────
    app: {
      get version() {
        if (hasNativeBridge) return window.native.app.getVersion()
        return null
      },
      get platform() {
        if (hasNativeBridge) return window.native.app.getPlatform()
        return navigator.platform
      },
      get userDataPath() {
        if (hasNativeBridge) return window.native.app.getUserDataPath()
        return null
      },
      async checkUpdates() {
        if (hasNativeBridge) return window.native.app.checkForUpdates()
        return nativeFetch('/updates/check', { method: 'GET' })
      },
    },

    // ── Shell ────────────────────────────────────────────────────
    shell: {
      showInFolder(itemPath) {
        if (hasNativeBridge) window.native.shell.showItemInFolder(itemPath)
      },
      openExternal(url) {
        if (hasNativeBridge) window.native.shell.openExternal(url)
        else window.open(url, '_blank')
      },
    },

    // ── Events from Main Process ─────────────────────────────────
    on(channel, handler) {
      if (hasNativeBridge) return window.native.on(channel, handler)
    },
  }))

  // ── Directive: x-native ─────────────────────────────────────────
  Alpine.directive('native', (el, { expression }, { evaluateLater, cleanup }) => {
    if (!hasNativeBridge) return

    const handler = (event, ...args) => {
      const fn = evaluateLater(expression)
      fn(() => {}, { event, ...args })
    }

    const channels = ['menu-action', 'files-opened', 'folder-opened', 'update-available', 'update-downloaded', 'deep-link']

    const cleanups = channels.map(channel => {
      return window.native.on(channel, (...args) => {
        handler({ channel, args })
      })
    })

    cleanup(() => cleanups.forEach(fn => fn?.()))
  })

  // ── Listen for menu actions ──────────────────────────────────────
  if (hasNativeBridge) {
    window.native.on('menu-action', (action) => {
      const handler = menuActionHandlers[action]
      if (handler) handler()

      // Dispatch a DOM event that Alpine can listen to
      window.dispatchEvent(new CustomEvent('native:menu', { detail: { action } }))
    })

    window.native.on('files-opened', (filePaths) => {
      window.dispatchEvent(new CustomEvent('native:files-opened', { detail: { filePaths } }))
    })

    window.native.on('folder-opened', (folderPath) => {
      window.dispatchEvent(new CustomEvent('native:folder-opened', { detail: { folderPath } }))
    })
  }
}

// ── Exports ───────────────────────────────────────────────────────────
export { onMenuAction, isElectron, hasNativeBridge, nativeFetch }
