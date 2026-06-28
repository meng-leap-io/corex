@extends('auth.desktop.layout')

@section('title', 'Reset Password - ' . config('app.name'))
@section('heading', 'Set new password')
@section('subheading', 'Your reset link is verified. Choose a new password.')

@section('content')
<form method="POST" action="{{ route('auth.desktop.password.update') }}" x-data="{ loading: false }" @submit.prevent="loading = true; $el.submit()" class="space-y-5">
    @csrf

    <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

    <div>
        <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">New password</label>
        <input
            id="password"
            name="password"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors"
            placeholder="Min. 8 characters"
        >
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Confirm new password</label>
        <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors"
            placeholder="Repeat your new password"
        >
    </div>

    <button type="submit" :disabled="loading"
        class="w-full py-2.5 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
        <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <span x-text="loading ? 'Resetting...' : 'Reset password'"></span>
    </button>
</form>

<p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
    <a href="{{ route('auth.desktop.login') }}" class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 transition-colors">Back to log in</a>
</p>
@endsection
