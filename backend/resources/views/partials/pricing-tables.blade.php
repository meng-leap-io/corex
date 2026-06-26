<section class="py-20 lg:py-28">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Pricing</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white mb-4">Simple, transparent pricing</h2>
            <p class="text-lg text-slate-500 dark:text-slate-400">Choose the plan that fits your needs. No hidden fees. Upgrade or cancel anytime.</p>
        </div>

        <div class="flex items-center justify-center gap-3 mb-12" x-data="{ yearly: false }">
            <span class="text-sm font-medium" :class="!yearly ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">Monthly</span>
            <button @click="yearly = !yearly" class="relative w-12 h-6 rounded-full transition-colors" :class="yearly ? 'bg-primary-600' : 'bg-slate-300 dark:bg-slate-600'">
                <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="yearly ? 'translate-x-6' : ''"></span>
            </button>
            <span class="text-sm font-medium" :class="yearly ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                Yearly
                <span class="ml-1 text-xs text-emerald-500 font-semibold">Save 20%</span>
            </span>
        </div>

        <div class="grid md:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto" x-data="{ yearly: false }">

            <div class="relative p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Starter</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Perfect for getting started.</p>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-slate-900 dark:text-white">$0</span>
                    <span class="text-slate-500 dark:text-slate-400 ml-1">/month</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        1,000 API calls / month
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        3 projects
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        GPT-4o-mini access
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Community support
                    </li>
                </ul>
                <a href="https://console.corex.dev/register" class="block w-full text-center py-2.5 rounded-xl text-sm font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors border border-primary-200 dark:border-primary-800">Get started free</a>
            </div>

            <div class="relative p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-800 border-2 border-primary-500 shadow-xl shadow-primary-500/10 card-hover scale-105">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full gradient-bg text-white text-xs font-semibold">Most Popular</div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Pro</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">For serious developers.</p>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-slate-900 dark:text-white" x-text="yearly ? '$29' : '$39'">$39</span>
                    <span class="text-slate-500 dark:text-slate-400 ml-1" x-text="yearly ? '/month' : '/month'">/month</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        10,000 API calls / month
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Unlimited projects
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        GPT-4o & Claude 3 Opus
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Advanced analytics
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Priority email support
                    </li>
                </ul>
                <a href="https://console.corex.dev/register?plan=pro" class="block w-full text-center py-2.5 rounded-xl text-sm font-semibold text-white gradient-bg hover:opacity-90 transition-all shadow-lg shadow-primary-500/25">Start free trial</a>
            </div>

            <div class="relative p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Team</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">For teams and organizations.</p>
                <div class="mb-6">
                    <span class="text-4xl font-bold text-slate-900 dark:text-white" x-text="yearly ? '$79' : '$99'">$99</span>
                    <span class="text-slate-500 dark:text-slate-400 ml-1" x-text="yearly ? '/month' : '/month'">/month</span>
                </div>
                <ul class="space-y-3 mb-8">
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        50,000 API calls / month
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Unlimited projects & team members
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        All AI models
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        SSO & team management
                    </li>
                    <li class="flex items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Dedicated support & SLAs
                    </li>
                </ul>
                <a href="https://console.corex.dev/register?plan=team" class="block w-full text-center py-2.5 rounded-xl text-sm font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors border border-primary-200 dark:border-primary-800">Start free trial</a>
            </div>

        </div>
    </div>
</section>
