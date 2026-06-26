<section class="py-20 lg:py-28 bg-slate-50 dark:bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="inline-block text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-100 dark:bg-primary-900/40 px-3 py-1 rounded-full uppercase tracking-wider mb-4">Testimonials</span>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white mb-4">Loved by developers worldwide</h2>
            <p class="text-lg text-slate-500 dark:text-slate-400">See what our community says about their experience with Corex.dev.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-6 lg:gap-8" x-data="{
            testimonials: [
                { name: 'Sarah Chen', role: 'Full-Stack Developer', company: 'TechStartup.io', avatar: 'SC', content: 'Corex has completely transformed how I build APIs. The AI code generation is incredibly accurate and saves me hours every day.', rating: 5 },
                { name: 'Marcus Johnson', role: 'Tech Lead', company: 'DevShop Inc.', avatar: 'MJ', content: 'We moved our entire team to Corex. The collaboration features and AI assistance have boosted our productivity by 3x.', rating: 5 },
                { name: 'Elena Rodriguez', role: 'Indie Developer', company: 'Solopreneur.dev', avatar: 'ER', content: 'As a solo developer, Corex feels like having a full team. From code generation to debugging, it handles the heavy lifting.', rating: 5 },
                { name: 'Alex Kim', role: 'CTO', company: 'ScaleUp SaaS', avatar: 'AK', content: 'The code quality and consistency Corex produces is remarkable. It\\'s become an essential part of our development pipeline.', rating: 5 },
                { name: 'Priya Patel', role: 'Backend Engineer', company: 'FinTech Corp', avatar: 'PP', content: 'Security was our biggest concern, but Corex exceeded our expectations. Enterprise-grade with developer-friendly pricing.', rating: 5 },
                { name: 'James Wilson', role: 'Freelancer', company: 'WebCraft Studio', avatar: 'JW', content: 'I\\'ve tried many AI coding tools, but Corex is the only one that truly understands full-stack development workflows.', rating: 5 },
            ]
        }">
            <template x-for="(t, i) in testimonials" :key="i">
                <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-800/50 border border-border-light dark:border-border-dark card-hover" :style="{ animationDelay: (i * 100) + 'ms' }">
                    <div class="flex items-center gap-1 mb-4">
                        <template x-for="s in 5" :key="s">
                            <svg class="w-5 h-5" :class="s <= t.rating ? 'text-amber-400' : 'text-slate-200 dark:text-slate-600'" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </template>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mb-6" x-html="'&ldquo;' + t.content + '&rdquo;'"></p>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center text-white text-xs font-bold shrink-0" x-text="t.avatar"></div>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="t.name"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400" x-text="t.role + ' at ' + t.company"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>
