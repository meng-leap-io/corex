<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full overflow-hidden select-none"
      x-data="desktopApp()"
      x-bind:class="{
          'dark': theme === 'dark',
          'high-contrast': highContrast,
          'reduced-motion': reducedMotion,
      }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="desktop-app" content="true">
    <meta name="app-version" content="{{ config('nativephp.version', '1.0.0') }}">

    <title>@yield('title', config('app.name', 'Corex') . ' Desktop')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' },
                        ide: { bg: '#1e1e2e', sidebar: '#252536', panel: '#2a2a3c', border: '#363650', hover: '#313145', active: '#3b3b52', text: '#cdd6f4', muted: '#6c7086', accent: '#89b4fa' },
                        light: { bg: '#ffffff', sidebar: '#f8fafc', panel: '#f1f5f9', border: '#e2e8f0', hover: '#e2e8f0', active: '#cbd5e1', text: '#0f172a', muted: '#64748b', accent: '#6366f1' },
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'], mono: ['JetBrains Mono', 'Fira Code', 'monospace'] },
                    fontSize: { 'dpi-sm': 'calc(0.75rem * var(--dpi-scale))', 'dpi-base': 'calc(0.875rem * var(--dpi-scale))', 'dpi-lg': 'calc(1rem * var(--dpi-scale))', 'dpi-xl': 'calc(1.25rem * var(--dpi-scale))' },
                }
            }
        }
    </script>
    <style>
        :root {
            --dpi-scale: 1;
            --font-scale: 1;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --border: #e2e8f0;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
            --surface: #ffffff;
            --surface-hover: #f1f5f9;
            --scrollbar: #cbd5e1;
            --scrollbar-hover: #94a3b8;
        }

        .dark {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border: #334155;
            --accent: #818cf8;
            --accent-hover: #6366f1;
            --surface: #1e293b;
            --surface-hover: #334155;
            --scrollbar: #475569;
            --scrollbar-hover: #64748b;
        }

        html, body { height: 100%; overflow: hidden; font-size: calc(13px * var(--dpi-scale) * var(--font-scale)); }
        [x-cloak] { display: none !important; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--scrollbar-hover); }

        .titlebar { -webkit-app-region: drag; }
        .titlebar-button { -webkit-app-region: no-drag; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }

        @media (prefers-contrast: more) {
            .dark { --text-primary: #ffffff; --text-secondary: #e2e8f0; --border: #64748b; }
            :root { --text-primary: #000000; --text-secondary: #1e293b; --border: #64748b; }
        }

        .high-contrast {
            --text-primary: #ffffff !important;
            --text-secondary: #cbd5e1 !important;
            --border: #ffffff !important;
            --accent: #60a5fa !important;
        }
        .high-contrast:root { --text-primary: #000000 !important; --text-secondary: #1e293b !important; --border: #000000 !important; --accent: #2563eb !important; }

        .reduced-motion *, .reduced-motion *::before, .reduced-motion *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    </style>
    @stack('styles')
</head>
<body class="h-full flex flex-col font-sans antialiased"
      :style="`--dpi-scale: ${dpiScale}; --font-scale: ${fontScale}; --accent: ${accentColor}; --accent-hover: ${accentHover};`"
      :class="theme === 'dark' ? 'bg-[var(--bg-primary)] text-[var(--text-primary)]' : 'bg-[var(--bg-primary)] text-[var(--text-primary)]'">

    {{-- Custom title bar for frameless window --}}
    @if(config('nativephp.window.frame') === false)
    <header class="titlebar flex items-center shrink-0 px-3 gap-2 select-none"
            :class="theme === 'dark' ? 'bg-ide-sidebar border-b border-[var(--border)]' : 'bg-light-sidebar border-b border-[var(--border)]'"
            :style="{ height: `calc(36px * ${dpiScale})` }">
        <div class="flex items-center gap-2" :style="{ transform: `scale(${dpiScale})`, transformOrigin: 'left center' }">
            <div class="flex gap-1.5">
                <button @click="window.native?.window?.close()" class="titlebar-button w-3 h-3 rounded-full bg-red-500 hover:bg-red-400 transition-colors" :title="'Close'"></button>
                <button @click="window.native?.window?.minimize()" class="titlebar-button w-3 h-3 rounded-full bg-yellow-500 hover:bg-yellow-400 transition-colors" :title="'Minimize'"></button>
                <button @click="window.native?.window?.maximize()" class="titlebar-button w-3 h-3 rounded-full bg-green-500 hover:bg-green-400 transition-colors" :title="'Maximize'"></button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <span class="text-xs font-medium opacity-60">{{ config('app.name', 'Corex') }} Desktop</span>
        </div>
        <div class="w-16 flex items-center justify-end gap-1 pr-1">
            <template x-if="!online">
                <span class="w-2 h-2 rounded-full bg-amber-400" title="Offline"></span>
            </template>
            <template x-if="taskProgress !== null && taskProgress >= 0">
                <span class="text-[10px] font-mono opacity-60" x-text="`${Math.round(taskProgress * 100)}%`"></span>
            </template>
        </div>
    </header>
    @endif

    {{-- Offline Banner --}}
    <div x-show="!online" x-cloak
         class="flex items-center justify-center gap-2 py-1 text-xs font-medium text-amber-200 bg-amber-600/20 border-b border-amber-600/30"
         :style="{ fontSize: `calc(12px * ${dpiScale} * ${fontScale})` }">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-2.829-2.829a5 5 0 000-7.07m-4.243 4.243a1 1 0 010-1.414"/></svg>
        <span>You are offline. Some features may be unavailable.</span>
    </div>

    {{-- Main Content --}}
    <main class="flex-1 overflow-hidden">
        @yield('content')
    </main>

    <script>
        function desktopApp() {
            return {
                theme: localStorage.getItem('console-theme') || 'dark',
                online: navigator.onLine,
                dpiScale: window.devicePixelRatio || 1,
                fontScale: 1,
                highContrast: window.matchMedia('(prefers-contrast: more)').matches,
                reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                accentColor: '#6366f1',
                taskProgress: null,

                get accentHover() {
                    return this.adjustBrightness(this.accentColor, -20)
                },

                async init() {
                    this.syncSystemTheme()
                    this.watchSystemTheme()
                    this.watchContrast()
                    this.watchMotion()
                    this.loadThemeFromServer()

                    window.addEventListener('online', () => this.online = true)
                    window.addEventListener('offline', () => this.online = false)

                    window.addEventListener('native:menu', (e) => {
                        this.handleMenuAction(e.detail.action)
                    })
                },

                async loadThemeFromServer() {
                    try {
                        const res = await fetch('/_native/system/theme-css', {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                            }
                        })
                        if (res.ok) {
                            const data = await res.json()
                            if (data.is_dark !== undefined) {
                                this.theme = data.is_dark ? 'dark' : 'light'
                                localStorage.setItem('console-theme', this.theme)
                            }
                            if (data.dpi_scale) this.dpiScale = data.dpi_scale
                            if (data.font_scale) this.fontScale = data.font_scale
                            if (data.variables?.['--accent']) {
                                this.accentColor = data.variables['--accent']
                                document.documentElement.style.setProperty('--accent', this.accentColor)
                                document.documentElement.style.setProperty('--accent-hover', this.accentHover)
                            }
                        }
                    } catch (e) {
                        // Server not available, use defaults
                    }
                },

                syncSystemTheme() {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)')
                    if (!localStorage.getItem('console-theme')) {
                        this.theme = prefersDark.matches ? 'dark' : 'light'
                    }
                },

                watchSystemTheme() {
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (!localStorage.getItem('console-theme')) {
                            this.theme = e.matches ? 'dark' : 'light'
                        }
                    })
                },

                watchContrast() {
                    window.matchMedia('(prefers-contrast: more)').addEventListener('change', (e) => {
                        this.highContrast = e.matches
                    })
                },

                watchMotion() {
                    window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (e) => {
                        this.reducedMotion = e.matches
                    })
                },

                setTheme(t) {
                    this.theme = t
                    localStorage.setItem('console-theme', t)
                },

                toggleTheme() {
                    this.setTheme(this.theme === 'dark' ? 'light' : 'dark')
                },

                setTaskProgress(progress) {
                    this.taskProgress = Math.max(0, Math.min(1, progress))
                    if (window.native?.taskbar) {
                        window.native.taskbar.setProgress(progress)
                    }
                },

                clearTaskProgress() {
                    this.taskProgress = null
                    if (window.native?.taskbar) {
                        window.native.taskbar.setProgress(-1)
                    }
                },

                adjustBrightness(hex, percent) {
                    hex = hex.replace('#', '')
                    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2]
                    const num = parseInt(hex, 16)
                    let r = (num >> 16) + percent
                    let g = ((num >> 8) & 0x00FF) + percent
                    let b = (num & 0x0000FF) + percent
                    r = Math.max(0, Math.min(255, r))
                    g = Math.max(0, Math.min(255, g))
                    b = Math.max(0, Math.min(255, b))
                    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`
                },

                handleMenuAction(action) {
                    switch (action) {
                        case 'check-updates':
                            if (window.native) window.native.app.checkForUpdates()
                            break
                        case 'open-about':
                            this.showAbout()
                            break
                        case 'open-docs':
                            if (window.native) window.native.shell.openExternal('https://corex.dev/docs')
                            else window.open('https://corex.dev/docs', '_blank')
                            break
                        case 'report-issue':
                            const url = 'https://github.com/corex-dev/corex/issues/new'
                            if (window.native) window.native.shell.openExternal(url)
                            else window.open(url, '_blank')
                            break
                        case 'toggle-theme':
                            this.toggleTheme()
                            break
                    }
                },

                showAbout() {
                    const version = window.native?.app?.getVersion?.() || '1.0.0'
                    alert(`Corex Desktop v${version}\nAI-Powered Development Platform\nOS: ${navigator.platform}\nDPI: ${this.dpiScale.toFixed(2)}x`)
                },
            }
        }
    </script>

    @stack('scripts')
</body>
</html>
