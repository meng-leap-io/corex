<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" x-data="themeManager()" :class="{ 'dark': isDark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <title>@yield('title', 'Corex.dev - AI-Powered Development Platform')</title>
    <meta name="description" content="@yield('description', 'Build, test, and deploy faster with Corex.dev. An AI-powered development platform that accelerates your workflow from idea to production.')">

    <meta property="og:title" content="@yield('og_title', 'Corex.dev - AI-Powered Development Platform')">
    <meta property="og:description" content="@yield('og_description', 'Build, test, and deploy faster with Corex.dev. AI-powered tools for modern development teams.')">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Corex.dev')">
    <meta name="twitter:description" content="@yield('og_description', 'AI-powered development platform.')">

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preload" href="{{ asset('favicon.svg') }}" as="image">

    @hasSection('console')
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.3.0/css/xterm.min.css" as="style" crossorigin="anonymous">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/editor/editor.main.min.css" as="style" crossorigin="anonymous">
    @endif

    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://browser.sentry-cdn.com" crossorigin>
    <link rel="preconnect" href="https://api.corex.dev" crossorigin>
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' },
                        surface: { light: '#ffffff', dark: '#0f172a' },
                        card: { light: '#f8fafc', dark: '#1e293b' },
                        border: { light: '#e2e8f0', dark: '#334155' },
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'] },
                    animation: { 'gradient': 'gradient 8s ease infinite', 'float': 'float 6s ease-in-out infinite', 'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite' },
                    keyframes: { gradient: { '0%, 100%': { backgroundPosition: '0% 50%' }, '50%': { backgroundPosition: '100% 50%' } }, float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-20px)' } } }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .gradient-text { background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899); background-size: 200% 200%; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: gradient 8s ease infinite; }
        .gradient-bg { background: linear-gradient(-45deg, #6366f1, #a855f7, #3b82f6, #06b6d4); background-size: 400% 400%; animation: gradient 15s ease infinite; }
        .card-hover { transition: all .3s ease; } .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -12px rgba(0,0,0,.15); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeInUp .6s ease forwards; } .animate-in-d1 { animation-delay: .1s; } .animate-in-d2 { animation-delay: .2s; } .animate-in-d3 { animation-delay: .3s; } .animate-in-d4 { animation-delay: .4s; }

        .lazy-bg { background-color: #1e293b; background-image: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
        }
    </style>
    @stack('head')
</head>
<body class="bg-surface-light dark:bg-surface-dark text-slate-900 dark:text-slate-100 antialiased transition-colors duration-300" x-data="{ mobileOpen: false, authModal: false, authTab: 'login' }">

    @include('partials.navigation')

    <main class="min-h-screen">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.auth-modal')

    <script>
        function themeManager() {
            return {
                isDark: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                toggle() { this.isDark = !this.isDark; localStorage.setItem('theme', this.isDark ? 'dark' : 'light') }
            }
        }
    </script>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).then((reg) => {
                    console.debug('[SW] registered:', reg.scope);
                }).catch((err) => {
                    console.debug('[SW] registration failed:', err);
                });
            });
        }
    </script>

    <script>
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img[loading="lazy"]');
            images.forEach(img => { img.loading = 'lazy'; });
        }
        if ('loading' in HTMLIFrameElement.prototype) {
            const iframes = document.querySelectorAll('iframe[loading="lazy"]');
            iframes.forEach(iframe => { iframe.loading = 'lazy'; });
        }
    </script>

    @stack('scripts')
</body>
</html>
