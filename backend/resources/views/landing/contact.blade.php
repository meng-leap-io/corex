@extends('layouts.app')

@section('title', 'Contact - Corex.dev')
@section('description', 'Get in touch with the Corex.dev team. We\'re here to help with questions, feedback, or anything you need.')
@section('og_title', 'Contact Corex.dev - We\'re Here to Help')

@section('content')

<section class="relative pt-32 pb-16 lg:pt-40 lg:pb-20 overflow-hidden">
    <div class="absolute inset-0 gradient-bg opacity-5 dark:opacity-10"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-start">

            <div>
                <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Contact</span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 dark:text-white mb-4">Let's <span class="gradient-text">talk</span></h1>
                <p class="text-lg text-slate-500 dark:text-slate-400 leading-relaxed mb-8">Have a question, feedback, or want to discuss enterprise plans? We'd love to hear from you.</p>

                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Email</h3>
                            <a href="mailto:hello@corex.dev" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">hello@corex.dev</a>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Discord</h3>
                            <a href="https://discord.gg/corexdev" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">Join our community</a>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Team & Enterprise</h3>
                            <a href="mailto:sales@corex.dev" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">sales@corex.dev</a>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Response time</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">We typically respond within 24 hours. Enterprise customers get priority support with SLA.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative" x-data="contactForm()">
                <div class="absolute inset-0 bg-gradient-to-br from-primary-500/20 to-purple-600/20 rounded-2xl"></div>
                <form @submit.prevent="submit" class="relative p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-800 border border-border-light dark:border-border-dark shadow-xl">

                    <div class="space-y-5">

                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Full Name</label>
                                <input type="text" id="name" x-model="form.name" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="Jane Doe">
                                <p x-show="errors.name" x-text="errors.name" class="text-xs text-red-500 mt-1"></p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
                                <input type="email" id="email" x-model="form.email" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors" placeholder="jane@example.com">
                                <p x-show="errors.email" x-text="errors.email" class="text-xs text-red-500 mt-1"></p>
                            </div>
                        </div>

                        <div>
                            <label for="subject" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Subject</label>
                            <select id="subject" x-model="form.subject" required class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors">
                                <option value="">Select a subject...</option>
                                <option value="general">General inquiry</option>
                                <option value="support">Technical support</option>
                                <option value="sales">Sales & pricing</option>
                                <option value="enterprise">Enterprise plan</option>
                                <option value="feedback">Feedback & suggestions</option>
                                <option value="bug">Bug report</option>
                            </select>
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Message</label>
                            <textarea id="message" x-model="form.message" required rows="5" class="w-full px-4 py-2.5 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-700/50 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors resize-none" placeholder="How can we help you?"></textarea>
                            <p x-show="errors.message" x-text="errors.message" class="text-xs text-red-500 mt-1"></p>
                        </div>

                        <button type="submit" :disabled="loading" class="w-full py-3 rounded-xl text-sm font-semibold text-white gradient-bg hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2">
                            <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="loading ? 'Sending...' : 'Send message'"></span>
                        </button>

                        <div x-show="success" x-cloak x-transition class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800">
                            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Message sent successfully! We'll get back to you within 24 hours.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="py-20 lg:py-28 bg-slate-50 dark:bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white mb-4">Prefer to chat with us directly?</h2>
        <p class="text-lg text-slate-500 dark:text-slate-400 mb-8">Join our Discord community for real-time support and discussions.</p>
        <a href="https://discord.gg/corexdev" class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl text-base font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-all shadow-lg">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286z"/></svg>
            Join Discord
        </a>
    </div>
</section>
@endsection

@push('scripts')
<script>
    function contactForm() {
        return {
            form: { name: '', email: '', subject: '', message: '' },
            errors: {},
            loading: false,
            success: false,
            submit() {
                this.errors = {};
                this.loading = true;
                this.success = false;

                if (!this.form.name.trim()) { this.errors.name = 'Name is required.'; this.loading = false; return; }
                if (!this.form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email)) { this.errors.email = 'Valid email is required.'; this.loading = false; return; }
                if (!this.form.subject) { this.errors.subject = 'Please select a subject.'; this.loading = false; return; }
                if (!this.form.message.trim() || this.form.message.trim().length < 10) { this.errors.message = 'Message must be at least 10 characters.'; this.loading = false; return; }

                setTimeout(() => {
                    this.loading = false;
                    this.success = true;
                    this.form = { name: '', email: '', subject: '', message: '' };
                }, 1500);
            }
        }
    }
</script>
@endpush
