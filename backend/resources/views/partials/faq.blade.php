<section class="py-20 lg:py-28 bg-slate-50 dark:bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">FAQ</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white mb-4">Frequently asked questions</h2>
            <p class="text-lg text-slate-500 dark:text-slate-400">Everything you need to know about Corex.dev.</p>
        </div>

        <div class="max-w-3xl mx-auto space-y-4" x-data="{ open: null }">

            <div class="rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark overflow-hidden transition-all" :class="open === 0 ? 'shadow-lg' : ''">
                <button @click="open = open === 0 ? null : 0" class="w-full flex items-center justify-between px-6 py-5 text-left">
                    <span class="text-base font-semibold text-slate-900 dark:text-white">What is Corex.dev?</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform shrink-0" :class="open === 0 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open === 0" x-collapse class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Corex.dev is an AI-powered development platform that helps developers build, test, and deploy applications faster. It combines intelligent code generation, real-time collaboration tools, and automated workflows to accelerate your development process from idea to production.</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark overflow-hidden transition-all" :class="open === 1 ? 'shadow-lg' : ''">
                <button @click="open = open === 1 ? null : 1" class="w-full flex items-center justify-between px-6 py-5 text-left">
                    <span class="text-base font-semibold text-slate-900 dark:text-white">What AI models do you support?</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform shrink-0" :class="open === 1 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open === 1" x-collapse class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">We support a wide range of AI models including GPT-4o, GPT-4o-mini, Claude 3 Opus/Sonnet/Haiku, Gemini 1.5 Pro/Flash, and more. Pro and Team plans have access to premium models, while the free tier includes GPT-4o-mini.</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark overflow-hidden transition-all" :class="open === 2 ? 'shadow-lg' : ''">
                <button @click="open = open === 2 ? null : 2" class="w-full flex items-center justify-between px-6 py-5 text-left">
                    <span class="text-base font-semibold text-slate-900 dark:text-white">How does pricing work?</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform shrink-0" :class="open === 2 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open === 2" x-collapse class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">We offer three plans: Starter (free, 1,000 API calls/month), Pro ($39/month, 10,000 API calls), and Team ($99/month, 50,000 API calls). Save 20% with annual billing. Each plan includes a different set of features and AI model access. You can upgrade, downgrade, or cancel at any time.</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark overflow-hidden transition-all" :class="open === 3 ? 'shadow-lg' : ''">
                <button @click="open = open === 3 ? null : 3" class="w-full flex items-center justify-between px-6 py-5 text-left">
                    <span class="text-base font-semibold text-slate-900 dark:text-white">Is my code secure?</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform shrink-0" :class="open === 3 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open === 3" x-collapse class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Absolutely. We take security seriously with end-to-end encryption, SOC 2 compliance, and strict data handling policies. Your code is encrypted at rest and in transit, and we never train our models on your proprietary code. We also offer SSO and team management for enterprise customers.</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark overflow-hidden transition-all" :class="open === 4 ? 'shadow-lg' : ''">
                <button @click="open = open === 4 ? null : 4" class="w-full flex items-center justify-between px-6 py-5 text-left">
                    <span class="text-base font-semibold text-slate-900 dark:text-white">Can I integrate with my existing tools?</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform shrink-0" :class="open === 4 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open === 4" x-collapse class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Yes! Corex.dev integrates seamlessly with GitHub, GitLab, Bitbucket, Slack, Discord, and popular CI/CD pipelines. We also provide a comprehensive API and webhooks for custom integrations. Our platform is designed to fit into your existing workflow without disruption.</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark overflow-hidden transition-all" :class="open === 5 ? 'shadow-lg' : ''">
                <button @click="open = open === 5 ? null : 5" class="w-full flex items-center justify-between px-6 py-5 text-left">
                    <span class="text-base font-semibold text-slate-900 dark:text-white">What kind of support do you offer?</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform shrink-0" :class="open === 5 ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open === 5" x-collapse class="px-6 pb-5">
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Free plan users get community support through our Discord server and documentation. Pro plan includes priority email support with 24-hour response time. Team plan offers dedicated support with SLAs, onboarding assistance, and a customer success manager.</p>
                </div>
            </div>

        </div>
    </div>
</section>
