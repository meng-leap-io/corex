<div x-show="authModal" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.away="authModal = false" @keydown.escape.window="authModal = false">
    <div x-show="authModal" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4" class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden" @click.away="authModal = false">

        <div class="relative p-6 sm:p-8">
            <button @click="authModal = false" class="absolute top-4 right-4 p-2 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-xl gradient-bg flex items-center justify-center mx-auto mb-4">
                    <span class="text-white font-bold text-lg">C</span>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 dark:text-white" x-text="authTab === 'login' ? 'Welcome back' : 'Create your account'"></h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400" x-text="authTab === 'login' ? 'Log in to continue building with Corex.' : 'Start building smarter with AI-powered tools.'"></p>
            </div>

            <div class="flex mb-6 bg-slate-100 dark:bg-slate-700/50 rounded-lg p-1">
                <button @click="authTab = 'login'" class="flex-1 py-2.5 text-sm font-medium rounded-md transition-all" :class="authTab === 'login' ? 'bg-white dark:bg-slate-600 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">Log In</button>
                <button @click="authTab = 'register'" class="flex-1 py-2.5 text-sm font-medium rounded-md transition-all" :class="authTab === 'register' ? 'bg-white dark:bg-slate-600 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'">Register</button>
            </div>

            <form x-show="authTab === 'login'" x-data="{ email: '', password: '', loading: false, errors: {} }" @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
                    <input type="email" x-model="email" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="you@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
                    <input type="password" x-model="password" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="Enter your password">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" class="rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Remember me</span>
                    </label>
                    <a href="https://console.corex.dev/forgot-password" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500">Forgot password?</a>
                </div>
                <button type="submit" :disabled="loading" class="w-full py-2.5 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
                    <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="loading ? 'Logging in...' : 'Log in'"></span>
                </button>
            </form>

            <form x-show="authTab === 'register'" x-data="{ name: '', email: '', password: '', password_confirmation: '', loading: false, errors: {} }" @submit.prevent="handleRegister" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Full Name</label>
                    <input type="text" x-model="name" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="Jane Doe">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
                    <input type="email" x-model="email" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="you@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
                    <input type="password" x-model="password" required minlength="8" class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="Min. 8 characters">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Confirm Password</label>
                    <input type="password" x-model="password_confirmation" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="Repeat your password">
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">By creating an account, you agree to our <a href="https://docs.corex.dev/terms" class="text-primary-600 dark:text-primary-400 hover:underline">Terms of Service</a> and <a href="https://docs.corex.dev/privacy" class="text-primary-600 dark:text-primary-400 hover:underline">Privacy Policy</a>.</p>
                <button type="submit" :disabled="loading" class="w-full py-2.5 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
                    <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="loading ? 'Creating account...' : 'Create account'"></span>
                </button>
            </form>

            <div class="mt-6">
                <div class="relative mb-6">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-border-light dark:border-border-dark"></div></div>
                    <div class="relative flex justify-center"><span class="px-4 text-xs text-slate-400 dark:text-slate-500 bg-white dark:bg-slate-800">Or continue with</span></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button class="flex items-center justify-center gap-2 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        Google
                    </button>
                    <button class="flex items-center justify-center gap-2 py-2.5 rounded-lg border border-border-light dark:border-border-dark text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
                        GitHub
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function handleLogin() {
        this.loading = true;
        this.errors = {};
        setTimeout(() => {
            window.location.href = 'https://console.corex.dev/login';
        }, 1000);
    }
    function handleRegister() {
        this.loading = true;
        this.errors = {};
        if (this.password !== this.password_confirmation) {
            this.errors.password_confirmation = 'Passwords do not match.';
            this.loading = false;
            return;
        }
        setTimeout(() => {
            window.location.href = 'https://console.corex.dev/register';
        }, 1000);
    }
</script>
