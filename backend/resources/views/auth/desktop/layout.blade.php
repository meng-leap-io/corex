<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full"
      x-data="{ isDark: localStorage.getItem('auth-theme') === 'dark' || (!localStorage.getItem('auth-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
      :class="{ 'dark': isDark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="desktop-app" content="true">
    <title>@yield('title', config('app.name') . ' - Authentication')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' },
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .gradient-bg { background: linear-gradient(-45deg, #6366f1, #a855f7, #3b82f6, #06b6d4); background-size: 400% 400%; animation: gradient 15s ease infinite; }
        @keyframes gradient { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .auth-card { backdrop-filter: blur(20px); }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }
    </style>
    @stack('styles')
</head>
<body class="h-full bg-gradient-to-br from-slate-50 via-primary-50/30 to-slate-100 dark:from-slate-900 dark:via-primary-950/20 dark:to-slate-800">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <a href="/" class="flex justify-center">
                <div class="w-12 h-12 rounded-xl gradient-bg flex items-center justify-center shadow-lg shadow-primary-500/25">
                    <span class="text-white font-bold text-xl">C</span>
                </div>
            </a>
            <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
                @yield('heading')
            </h2>
            <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
                @yield('subheading')
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="auth-card bg-white/80 dark:bg-slate-800/80 border border-slate-200/50 dark:border-slate-700/50 rounded-2xl shadow-xl shadow-slate-900/5 px-6 py-8 sm:px-10">
                @if (session('status'))
                    <div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-sm text-green-700 dark:text-green-300">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800">
                        @foreach ($errors->all() as $error)
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @yield('content')
            </div>

            <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-500">
                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function toggleTheme() {
            return {
                isDark: localStorage.getItem('auth-theme') === 'dark' || (!localStorage.getItem('auth-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
                toggle() {
                    this.isDark = !this.isDark;
                    localStorage.setItem('auth-theme', this.isDark ? 'dark' : 'light');
                }
            }
        }
    </script>
    @stack('scripts')
</body>
</html>
