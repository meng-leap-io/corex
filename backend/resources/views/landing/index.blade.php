@extends('layouts.app')

@section('title', 'Corex.dev - AI-Powered Development Platform')
@section('description', 'Build, test, and deploy faster with Corex.dev. AI-powered development tools for modern teams.')

@section('content')
    @include('partials.hero')

    <div class="relative">
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary-500/50 to-transparent"></div>
        @include('partials.features-grid')
    </div>

    @include('partials.testimonials')
    @include('partials.pricing-tables')
    @include('partials.faq')

    <section class="py-20 lg:py-28">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white mb-4">Ready to build smarter?</h2>
            <p class="text-lg text-slate-500 dark:text-slate-400 mb-8 max-w-2xl mx-auto">Join thousands of developers who are already using Corex.dev to accelerate their development workflow.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="https://console.corex.dev/register" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-white gradient-bg hover:opacity-90 transition-all shadow-lg shadow-primary-500/30">
                    Start building free
                    <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="https://docs.corex.dev" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all border border-border-light dark:border-border-dark">
                    View documentation
                </a>
            </div>
        </div>
    </section>
@endsection
