@extends('layouts.app')

@section('title', 'Pricing - Corex.dev')
@section('description', 'Simple, transparent pricing for Corex.dev. Choose the plan that fits your needs. Free, Pro ($39/mo), and Team ($99/mo) plans available.')
@section('og_title', 'Corex.dev Pricing - Simple, Transparent Plans')

@section('content')

<section class="relative pt-32 pb-16 lg:pt-40 lg:pb-20 overflow-hidden">
    <div class="absolute inset-0 gradient-bg opacity-5 dark:opacity-10"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Pricing</span>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white mb-4">Find the perfect plan for <span class="gradient-text">your team</span></h1>
        <p class="text-lg sm:text-xl text-slate-500 dark:text-slate-400 max-w-3xl mx-auto">Start free and upgrade as you grow. All plans include a 14-day free trial of Pro features.</p>
    </div>
</section>

@include('partials.pricing-tables')

<section class="py-20 lg:py-28 bg-slate-50 dark:bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Compare plans in detail</h2>
            <p class="text-lg text-slate-500 dark:text-slate-400">See exactly what each plan includes.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border-light dark:border-border-dark">
                        <th class="py-4 px-6 text-left text-slate-500 dark:text-slate-400 font-medium w-1/4">Feature</th>
                        <th class="py-4 px-6 text-center text-slate-900 dark:text-white font-semibold w-1/4">Starter</th>
                        <th class="py-4 px-6 text-center text-primary-600 dark:text-primary-400 font-semibold w-1/4">Pro</th>
                        <th class="py-4 px-6 text-center text-slate-900 dark:text-white font-semibold w-1/4">Team</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-light dark:divide-border-dark">
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">API calls / month</td>
                        <td class="py-4 px-6 text-center text-slate-500">1,000</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">10,000</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">50,000</td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">Projects</td>
                        <td class="py-4 px-6 text-center text-slate-500">3</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">Unlimited</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">Unlimited</td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">Team members</td>
                        <td class="py-4 px-6 text-center text-slate-500">1</td>
                        <td class="py-4 px-6 text-center text-slate-500">1</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">Unlimited</td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">AI models</td>
                        <td class="py-4 px-6 text-center text-slate-500">GPT-4o-mini</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">GPT-4o, Claude 3</td>
                        <td class="py-4 px-6 text-center text-slate-900 dark:text-white font-medium">All models</td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">Advanced analytics</td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-slate-300 dark:text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">Priority support</td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-slate-300 dark:text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">SSO / SAML</td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-slate-300 dark:text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-slate-300 dark:text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                    </tr>
                    <tr>
                        <td class="py-4 px-6 text-slate-600 dark:text-slate-300">Dedicated SLA</td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-slate-300 dark:text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-slate-300 dark:text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></td>
                        <td class="py-4 px-6 text-center"><svg class="w-5 h-5 text-emerald-500 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

@include('partials.faq')

<section class="py-20 lg:py-28">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Still have questions?</h2>
        <p class="text-lg text-slate-500 dark:text-slate-400 mb-8">We're here to help you find the right plan.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('contact') }}" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-white gradient-bg hover:opacity-90 transition-all shadow-lg shadow-primary-500/30">
                Contact sales
                <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </a>
            <a href="https://docs.corex.dev" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all border border-border-light dark:border-border-dark">
                Read the docs
            </a>
        </div>
    </div>
</section>
@endsection
