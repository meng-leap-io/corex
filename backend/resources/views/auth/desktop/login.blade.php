@extends('auth.desktop.layout')

@section('title', 'Log In - ' . config('app.name'))
@section('heading', 'Welcome back')
@section('subheading', 'Log in to continue building with ' . config('app.name') . '.')

@section('content')
<form method="POST" action="{{ route('auth.desktop.login.submit') }}" x-data="{ loading: false, email: '{{ old('email') }}', password: '' }" @submit.prevent="loading = true; $el.submit()" class="space-y-5">
    @csrf

    <div>
        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email address</label>
        <input
            id="email"
            name="email"
            type="email"
            autocomplete="email"
            required
            x-model="email"
            value="{{ old('email') }}"
            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors @error('email') border-red-500 @enderror"
            placeholder="you@example.com"
        >
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
        <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            required
            x-model="password"
            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors @error('password') border-red-500 @enderror"
            placeholder="Enter your password"
        >
    </div>

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
                type="checkbox"
                name="remember"
                value="1"
                {{ old('remember') ? 'checked' : '' }}
                class="rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500 bg-white dark:bg-slate-700"
            >
            <span class="text-sm text-slate-600 dark:text-slate-400">Remember me</span>
        </label>
        <a href="{{ route('auth.desktop.forgot-password') }}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 transition-colors">
            Forgot password?
        </a>
    </div>

    <button
        type="submit"
        :disabled="loading"
        class="w-full py-2.5 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2"
    >
        <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <span x-text="loading ? 'Logging in...' : 'Log in'"></span>
    </button>
</form>

<div class="mt-6">
    <div class="relative mb-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-slate-200 dark:border-slate-700"></div>
        </div>
        <div class="relative flex justify-center">
            <span class="px-4 text-xs text-slate-400 dark:text-slate-500 bg-white/80 dark:bg-slate-800/80">Or continue with</span>
        </div>
    </div>

    <div class="grid gap-3">
        <a href="{{ route('auth.desktop.oauth.google') }}"
           class="flex items-center justify-center gap-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
        </a>
    </div>
</div>

<p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
    Don't have an account?
    <a href="{{ route('auth.desktop.register') }}" class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 transition-colors">Create one</a>
</p>
@endsection
