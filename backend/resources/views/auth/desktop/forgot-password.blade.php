@extends('auth.desktop.layout')

@section('title', 'Forgot Password - ' . config('app.name'))
@section('heading', 'Reset your password')
@section('subheading', 'Enter your email and we\'ll send you a reset link.')

@section('content')
<form method="POST" action="{{ route('auth.desktop.password.email') }}" x-data="{ loading: false, email: '{{ old('email') }}', sent: false }" @submit.prevent="if(!sent) { loading = true; $el.submit() }" class="space-y-5">
    @csrf

    <div x-show="!sent">
        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email address</label>
        <input
            id="email"
            name="email"
            type="email"
            autocomplete="email"
            required
            x-model="email"
            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors"
            placeholder="you@example.com"
        >
    </div>

    <div x-show="sent" x-cloak class="p-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-center">
        <svg class="w-12 h-12 mx-auto text-green-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        <p class="text-sm font-medium text-green-700 dark:text-green-300">Check your email</p>
        <p class="text-xs text-green-600 dark:text-green-400 mt-1">We've sent a password reset link to <span x-text="email"></span></p>
    </div>

    <button type="submit" :disabled="loading || sent" x-show="!sent"
        class="w-full py-2.5 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
        <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <span x-text="loading ? 'Sending...' : 'Send reset link'"></span>
    </button>

    <button type="button" x-show="sent" x-cloak @click="sent = false; loading = false; email = ''"
        class="w-full py-2.5 rounded-lg text-sm font-semibold text-primary-600 dark:text-primary-400 border border-primary-300 dark:border-primary-700 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors">
        Send to another email
    </button>
</form>

<p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
    Remember your password?
    <a href="{{ route('auth.desktop.login') }}" class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 transition-colors">Back to log in</a>
</p>
@endsection
