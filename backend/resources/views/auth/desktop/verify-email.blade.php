@extends('auth.desktop.layout')

@section('title', 'Verify Email - ' . config('app.name'))
@section('heading', 'Verify your email')
@section('subheading', 'Please verify your email address to continue.')

@section('content')
<div x-data="{ 
    loading: false,
    sent: {{ session('status') ? 'true' : 'false' }},
    resend() {
        this.loading = true;
        fetch('{{ route('auth.desktop.verification.send') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            this.sent = true;
            this.loading = false;
        })
        .catch(() => {
            this.loading = false;
        });
    }
}" class="text-center">
    @if (session('status'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
            <svg class="w-12 h-12 mx-auto text-green-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm font-medium text-green-700 dark:text-green-300">Verification email sent!</p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">Check your inbox and click the verification link.</p>
        </div>
    @else
        <div class="mb-6">
            <svg class="w-16 h-16 mx-auto text-primary-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                We need to verify your email address before you can access all features.
                Check your inbox for a verification link.
            </p>
        </div>
    @endif

    <div class="space-y-3">
        <button @click="resend()" :disabled="loading"
            class="w-full py-2.5 rounded-lg text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span x-text="loading ? 'Sending...' : sent ? 'Resend verification email' : 'Send verification email'"></span>
        </button>

        <form method="POST" action="{{ route('auth.desktop.logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors">
                Log out
            </button>
        </form>
    </div>
</div>
@endsection
