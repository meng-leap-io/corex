@extends('layouts.app')

@section('title', 'Features - Corex.dev')
@section('description', 'Explore all the features Corex.dev offers: AI code generation, real-time collaboration, smart analytics, and more.')
@section('og_title', 'Corex.dev Features - AI-Powered Development Tools')

@section('content')

<section class="relative pt-32 pb-16 lg:pt-40 lg:pb-20 overflow-hidden">
    <div class="absolute inset-0 gradient-bg opacity-5 dark:opacity-10"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Features</span>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white mb-4">Everything you need to <span class="gradient-text">ship faster</span></h1>
        <p class="text-lg sm:text-xl text-slate-500 dark:text-slate-400 max-w-3xl mx-auto">From AI-powered code generation to enterprise-grade security, Corex.dev provides all the tools you need to accelerate your development workflow.</p>
    </div>
</section>

<section class="py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-24">
            <div>
                <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">AI Code Generation</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Generate production-ready code in seconds</h2>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed mb-6">Describe what you need in plain English, and our AI generates clean, well-documented code. Supports Laravel, React, Vue, Python, Go, and more.</p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-primary-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Support for 15+ programming languages and frameworks
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-primary-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Context-aware generation that understands your codebase
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-primary-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Real-time code review and optimization suggestions
                    </li>
                </ul>
            </div>
            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-br from-primary-500/20 to-purple-600/20 rounded-2xl"></div>
                <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-border-light dark:border-border-dark overflow-hidden">
                    <div class="flex items-center gap-1.5 px-4 py-3 border-b border-border-light dark:border-border-dark bg-slate-50 dark:bg-slate-800/50">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    </div>
                    <div class="p-4">
                        <pre class="text-xs font-mono text-slate-800 dark:text-slate-200 leading-relaxed"><span class="text-slate-500 dark:text-slate-400">// Generate a Laravel controller with resource methods</span>
<span class="text-primary-600 dark:text-primary-400">corex</span> generate:controller Api/PostController --api --model=Post

<span class="text-slate-500 dark:text-slate-400">// Output:</span>
<span class="text-purple-600 dark:text-purple-400">class</span> PostController <span class="text-purple-600 dark:text-purple-400">extends</span> Controller
{
    <span class="text-purple-600 dark:text-purple-400">public function</span> index(): <span class="text-sky-600 dark:text-sky-400">PostCollection</span> { ... }
    <span class="text-purple-600 dark:text-purple-400">public function</span> store(<span class="text-sky-600 dark:text-sky-400">StorePostRequest</span> $request): <span class="text-sky-600 dark:text-sky-400">PostResource</span> { ... }
    <span class="text-purple-600 dark:text-purple-400">public function</span> show(<span class="text-sky-600 dark:text-sky-400">Post</span> $post): <span class="text-sky-600 dark:text-sky-400">PostResource</span> { ... }
    <span class="text-purple-600 dark:text-purple-400">public function</span> update(<span class="text-sky-600 dark:text-sky-400">UpdatePostRequest</span> $request, <span class="text-sky-600 dark:text-sky-400">Post</span> $post): <span class="text-sky-600 dark:text-sky-400">PostResource</span> { ... }
    <span class="text-purple-600 dark:text-purple-400">public function</span> destroy(<span class="text-sky-600 dark:text-sky-400">Post</span> $post): <span class="text-sky-600 dark:text-sky-400">JsonResponse</span> { ... }
}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-24">
            <div class="order-last lg:order-first relative">
                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/20 to-teal-600/20 rounded-2xl"></div>
                <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-border-light dark:border-border-dark p-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center"><svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Sarah and Alex joined the project</p>
                                <p class="text-xs text-slate-500">2 minutes ago</p>
                            </div>
                        </div>
                        <div class="ml-13 p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-border-light dark:border-border-dark">
                            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                                <span class="w-5 h-5 rounded-full bg-gradient-to-br from-primary-400 to-purple-500 flex items-center justify-center text-white text-[8px] font-bold">SC</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">Sarah</span>
                                edited app/Http/Controllers/Api/PostController.php
                            </div>
                            <pre class="text-xs font-mono text-emerald-600 dark:text-emerald-400">+    public function index(): PostCollection</pre>
                        </div>
                        <div class="flex -space-x-2">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-purple-500 ring-2 ring-white dark:ring-slate-800 flex items-center justify-center text-white text-[9px] font-bold">SC</div>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 ring-2 ring-white dark:ring-slate-800 flex items-center justify-center text-white text-[9px] font-bold">AK</div>
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 ring-2 ring-white dark:ring-slate-800 flex items-center justify-center text-white text-[9px] font-bold">MJ</div>
                            <div class="w-8 h-8 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600 ring-2 ring-white dark:ring-slate-800 flex items-center justify-center text-slate-400 text-[9px]">+</div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <span class="inline-block text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Collaboration</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Real-time collaboration for your team</h2>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed mb-6">Work together on projects in real-time with live cursors, instant sync, and built-in code review. Your team stays in flow, never in the way.</p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Live cursor presence and real-time editing
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Built-in code review with inline comments
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Version history with instant rollback
                    </li>
                </ul>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-24">
            <div>
                <span class="inline-block text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Analytics</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Track everything with smart analytics</h2>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed mb-6">Understand your project metrics, API usage patterns, and team productivity with comprehensive dashboards and insights.</p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Real-time API usage monitoring and cost tracking
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Team productivity metrics and code generation stats
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Exportable reports and custom dashboards
                    </li>
                </ul>
            </div>
            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/20 to-orange-600/20 rounded-2xl"></div>
                <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-border-light dark:border-border-dark p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-700/50">
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">12.4k</p>
                            <p class="text-xs text-slate-500">API calls today</p>
                            <span class="text-xs text-emerald-600 dark:text-emerald-400">+12% vs yesterday</span>
                        </div>
                        <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-700/50">
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">$0.42</p>
                            <p class="text-xs text-slate-500">Total cost today</p>
                            <span class="text-xs text-emerald-600 dark:text-emerald-400">Within budget</span>
                        </div>
                    </div>
                    <div class="h-32 rounded-lg bg-slate-50 dark:bg-slate-700/50 flex items-end gap-1 p-2">
                        <div class="flex-1 bg-primary-400 rounded-t" style="height: 40%"></div>
                        <div class="flex-1 bg-primary-500 rounded-t" style="height: 65%"></div>
                        <div class="flex-1 bg-primary-400 rounded-t" style="height: 45%"></div>
                        <div class="flex-1 bg-primary-500 rounded-t" style="height: 80%"></div>
                        <div class="flex-1 bg-primary-400 rounded-t" style="height: 55%"></div>
                        <div class="flex-1 bg-primary-500 rounded-t" style="height: 90%"></div>
                        <div class="flex-1 bg-primary-400 rounded-t" style="height: 70%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <div class="order-last lg:order-first relative">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-500/20 to-pink-600/20 rounded-2xl"></div>
                <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-border-light dark:border-border-dark p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Enterprise-grade security</p>
                            <p class="text-xs text-slate-500">SOC 2 Type II certified</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-center">
                            <svg class="w-6 h-6 text-emerald-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span class="text-[10px] text-slate-500">Encrypted at rest</span>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-center">
                            <svg class="w-6 h-6 text-emerald-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span class="text-[10px] text-slate-500">SSO / SAML</span>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-center">
                            <svg class="w-6 h-6 text-emerald-500 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            <span class="text-[10px] text-slate-500">Audit logs</span>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <span class="inline-block text-xs font-semibold text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Security</span>
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Enterprise security you can trust</h2>
                <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed mb-6">Your code and data are protected with industry-leading security measures. SOC 2 compliant, encrypted at rest and in transit, with granular access controls.</p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-purple-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        End-to-end encryption for all data
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-purple-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        SOC 2 Type II certified infrastructure
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-purple-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Role-based access control and audit trails
                    </li>
                </ul>
            </div>
        </div>

    </div>
</section>

<section class="py-20 lg:py-28 bg-slate-50 dark:bg-slate-900/30">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Ready to experience all these features?</h2>
        <p class="text-lg text-slate-500 dark:text-slate-400 mb-8">Start your free trial today. No credit card required.</p>
        <a href="https://console.corex.dev/register" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-white gradient-bg hover:opacity-90 transition-all shadow-lg shadow-primary-500/30">
            Start building free
            <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>
@endsection
