<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full overflow-hidden select-none"
      x-data="desktopApp()"
      :class="{ 'dark': isDark }">
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
                }
            }
        }
    </script>
    <style>
        html, body { height: 100%; overflow: hidden; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #45475a; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #585b70; }
        .titlebar { -webkit-app-region: drag; }
        .titlebar-button { -webkit-app-region: no-drag; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }
    </style>
    @stack('styles')
</head>
<body class="h-full flex flex-col bg-ide-bg text-ide-text text-[13px] font-sans antialiased"
      :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-light-bg text-light-text'">

    {{-- Custom title bar for frameless window --}}
    @if(config('nativephp.window.frame') === false)
    <header class="titlebar flex items-center h-9 shrink-0 px-3 gap-2 select-none"
            :class="theme === 'dark' ? 'bg-ide-sidebar border-b border-ide-border' : 'bg-light-sidebar border-b border-light-border'">
        <div class="flex items-center gap-2">
            <div class="flex gap-1.5">
                <button @click="window.native?.window.close()" class="titlebar-button w-3 h-3 rounded-full bg-red-500 hover:bg-red-400 transition-colors"></button>
                <button @click="window.native?.window.minimize()" class="titlebar-button w-3 h-3 rounded-full bg-yellow-500 hover:bg-yellow-400 transition-colors"></button>
                <button @click="window.native?.window.maximize()" class="titlebar-button w-3 h-3 rounded-full bg-green-500 hover:bg-green-400 transition-colors"></button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <span class="text-xs font-medium opacity-60">{{ config('app.name', 'Corex') }} Desktop</span>
        </div>
        <div class="w-16"></div>
    </header>
    @endif

    {{-- Offline Banner --}}
    <div x-show="!online" x-cloak
         class="flex items-center justify-center gap-2 py-1 text-xs font-medium text-amber-200 bg-amber-600/20 border-b border-amber-600/30">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-2.829-2.829a5 5 0 000-7.07m-4.243 4.243a1 1 0 010-1.414"/></svg>
        <span>You are offline. Some features may be unavailable.</span>
    </div>

    {{-- Main Content --}}
    <main class="flex-1 overflow-hidden">
        @yield('content')
    </main>

    {{─ Offline detection ──}}
    <script>
        function desktopApp() {
            return {
                theme: localStorage.getItem('console-theme') || 'dark',
                online: navigator.onLine,

                init() {
                    window.addEventListener('online', () => this.online = true);
                    window.addEventListener('offline', () => this.online = false);

                    // Listen for NativePHP menu actions
                    window.addEventListener('native:menu', (e) => {
                        this.handleMenuAction(e.detail.action);
                    });
                },

                handleMenuAction(action) {
                    switch (action) {
                        case 'check-updates':
                            if (window.native) window.native.app.checkForUpdates();
                            break;
                        case 'open-about':
                            this.showAbout();
                            break;
                        case 'open-docs':
                            if (window.native) window.native.shell.openExternal('https://corex.dev/docs');
                            else window.open('https://corex.dev/docs', '_blank');
                            break;
                        case 'report-issue':
                            const url = 'https://github.com/corex-dev/corex/issues/new';
                            if (window.native) window.native.shell.openExternal(url);
                            else window.open(url, '_blank');
                            break;
                    }
                },

                showAbout() {
                    alert(`Corex Desktop v${window.native?.app?.getVersion?.() || '1.0.0'}\nAI-Powered Development Platform`);
                },
            };
        }
    </script>

    @stack('scripts')
</body>
</html>
