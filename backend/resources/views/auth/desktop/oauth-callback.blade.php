@extends('auth.desktop.layout')

@section('title', 'Completing Authentication... - ' . config('app.name'))
@section('heading', 'Completing authentication')
@section('subheading', 'Please wait while we complete your sign-in.')

@section('content')
<div class="text-center py-8" x-data="{ error: null }" x-init="
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');
    const errorDesc = params.get('error_description');

    if (errorDesc) {
        error = errorDesc;
        return;
    }

    if (!code) {
        error = 'No authorization code received.';
        return;
    }

    fetch('{{ route('auth.desktop.oauth.callback.submit') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ code: code, redirect_url: window.location.href.split('?')[0] }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.redirect) {
            window.location.href = data.redirect;
        } else if (data.message) {
            window.location.href = '/console';
        } else {
            error = 'Authentication failed. Please try again.';
        }
    })
    .catch(() => {
        error = 'Network error. Please try again.';
    });
">
    <template x-if="!error">
        <div>
            <svg class="w-12 h-12 mx-auto text-primary-500 animate-spin mb-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <p class="text-sm text-slate-500 dark:text-slate-400">Signing you in...</p>
        </div>
    </template>

    <template x-if="error">
        <div>
            <svg class="w-12 h-12 mx-auto text-red-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <p class="text-sm font-medium text-red-600 dark:text-red-400 mb-2">Authentication failed</p>
            <p class="text-xs text-red-500 dark:text-red-400 mb-4" x-text="error"></p>
            <a href="{{ route('auth.desktop.login') }}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 transition-colors">
                Back to log in
            </a>
        </div>
    </template>
</div>
@endsection
