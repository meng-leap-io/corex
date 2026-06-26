<nav class="fixed top-0 inset-x-0 z-50 border-b border-border-light dark:border-border-dark bg-white/80 dark:bg-surface-dark/80 backdrop-blur-xl" x-data="{ scrolled: false }" @scroll.window="scrolled = window.scrollY > 20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">

            <a href="{{ route('home') }}" class="flex items-center gap-2 group">
                <div class="w-8 h-8 rounded-lg gradient-bg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">C</span>
                </div>
                <span class="text-xl font-bold text-slate-900 dark:text-white">corex</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-medium">dev</span>
            </a>

            <div class="hidden lg:flex items-center gap-8">
                <a href="{{ route('features') }}" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">Features</a>
                <a href="{{ route('pricing') }}" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">Pricing</a>
                <a href="{{ route('about') }}" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">About</a>
                <a href="{{ route('contact') }}" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">Contact</a>
            </div>

            <div class="hidden lg:flex items-center gap-3">
                <button @click="toggle()" class="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Toggle theme">
                    <svg x-show="!isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg x-show="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
                <a href="https://console.corex.dev/login" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">Log in</a>
                <a href="https://console.corex.dev/register" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 transition-opacity shadow-lg shadow-primary-500/25">Get Started</a>
            </div>

            <button @click="mobileOpen = !mobileOpen" class="lg:hidden p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" :aria-expanded="mobileOpen" aria-label="Toggle menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-4" @click.away="mobileOpen = false" class="lg:hidden border-t border-border-light dark:border-border-dark bg-white dark:bg-surface-dark">
        <div class="px-4 py-6 space-y-4">
            <a href="{{ route('features') }}" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Features</a>
            <a href="{{ route('pricing') }}" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Pricing</a>
            <a href="{{ route('about') }}" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">About</a>
            <a href="{{ route('contact') }}" class="block px-3 py-2 rounded-lg text-base font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Contact</a>
            <hr class="border-border-light dark:border-border-dark">
            <button @click="toggle()" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg text-base font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                <svg x-show="!isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                <svg x-show="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span x-text="isDark ? 'Light Mode' : 'Dark Mode'"></span>
            </button>
            <div class="pt-2 space-y-3">
                <a href="https://console.corex.dev/login" class="block w-full text-center px-4 py-2.5 rounded-lg text-base font-medium text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">Log in</a>
                <a href="https://console.corex.dev/register" class="block w-full text-center px-4 py-2.5 rounded-lg text-base font-semibold text-white gradient-bg hover:opacity-90 transition-opacity">Get Started</a>
            </div>
        </div>
    </div>
</nav>
