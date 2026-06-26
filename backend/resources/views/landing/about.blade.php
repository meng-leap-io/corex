@extends('layouts.app')

@section('title', 'About - Corex.dev')
@section('description', 'Learn about Corex.dev — our mission, team, and the story behind building the AI-powered development platform.')
@section('og_title', 'About Corex.dev - Our Mission & Team')

@section('content')

<section class="relative pt-32 pb-16 lg:pt-40 lg:pb-20 overflow-hidden">
    <div class="absolute inset-0 gradient-bg opacity-5 dark:opacity-10"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">About Us</span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white mb-4">Building the future of <span class="gradient-text">development</span></h1>
                <p class="text-lg text-slate-500 dark:text-slate-400 leading-relaxed">We're a team of engineers, designers, and AI researchers on a mission to make software development faster, smarter, and more accessible for everyone.</p>
            </div>
            <div class="relative">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark text-center">
                        <p class="text-3xl font-bold gradient-text">10K+</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Active developers</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark text-center">
                        <p class="text-3xl font-bold gradient-text">1M+</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Code generations</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark text-center">
                        <p class="text-3xl font-bold gradient-text">99.9%</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Uptime SLA</p>
                    </div>
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark text-center">
                        <p class="text-3xl font-bold gradient-text">50+</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Countries reached</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-20 lg:py-28">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="max-w-3xl mb-20">
            <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-6">Our story</h2>
            <div class="space-y-4 text-base text-slate-500 dark:text-slate-400 leading-relaxed">
                <p>Corex.dev was born from a simple observation: developers spend too much time on repetitive tasks and not enough time on creative problem-solving. We founded Corex in 2024 with the vision of building an AI-powered platform that handles the boilerplate so developers can focus on what matters.</p>
                <p>Our team comes from diverse backgrounds — from open source maintainers to ML researchers at top tech companies. We believe that AI should augment human creativity, not replace it. Every feature we build is designed to make developers more productive while keeping them in full control of their code.</p>
                <p>Today, Corex.dev is trusted by thousands of developers and teams worldwide, from solo founders to enterprise engineering organizations. We're committed to continuous improvement, transparency, and building a platform that developers love to use.</p>
            </div>
        </div>

        <div class="mb-20">
            <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-12 text-center">Meet the team</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover">
                    <div class="w-20 h-20 rounded-full gradient-bg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">AK</div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Alex Kumar</h3>
                    <p class="text-sm text-primary-600 dark:text-primary-400 mb-2">CEO & Co-Founder</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Former Eng Lead at ScaleAI. Open source contributor.</p>
                </div>
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover">
                    <div class="w-20 h-20 rounded-full gradient-bg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">MJ</div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Maya Johnson</h3>
                    <p class="text-sm text-primary-600 dark:text-primary-400 mb-2">CTO & Co-Founder</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">ML researcher, previously at DeepMind. PhD in NLP.</p>
                </div>
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover">
                    <div class="w-20 h-20 rounded-full gradient-bg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">TL</div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Tomás López</h3>
                    <p class="text-sm text-primary-600 dark:text-primary-400 mb-2">Head of Product</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ex-Stripe, built developer tools used by millions.</p>
                </div>
                <div class="text-center p-6 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover">
                    <div class="w-20 h-20 rounded-full gradient-bg flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">SY</div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Sarah Yamasaki</h3>
                    <p class="text-sm text-primary-600 dark:text-primary-400 mb-2">Head of Design</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Design lead at Figma. Passionate about DX and UX.</p>
                </div>
            </div>
        </div>

        <div class="p-8 sm:p-12 rounded-2xl bg-gradient-to-br from-primary-600 to-purple-700 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">Join us in shaping the future of development</h2>
            <p class="text-lg text-primary-100 mb-8 max-w-2xl mx-auto">We're building the platform that will define how developers work in the AI era. Come be a part of it.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="https://console.corex.dev/register" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-primary-700 bg-white hover:bg-primary-50 transition-all shadow-lg">
                    Start building free
                    <svg class="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="https://careers.corex.dev" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-white bg-white/10 hover:bg-white/20 transition-all border border-white/30">
                    View open positions
                </a>
            </div>
        </div>

    </div>
</section>
@endsection
