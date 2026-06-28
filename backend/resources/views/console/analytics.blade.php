<!DOCTYPE html>
<html lang="en" class="h-full" x-data="analyticsState()" :class="{ 'dark': theme === 'dark' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Analytics - Corex Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @livewireStyles
</head>
<body class="h-full bg-[#0f172a] text-[#e2e8f0] antialiased">
    <div class="flex h-full">
        {{-- Sidebar --}}
        <aside class="flex w-56 flex-col border-r border-[#1e293b] bg-[#0f172a]">
            <div class="flex h-12 items-center border-b border-[#1e293b] px-4">
                <span class="text-sm font-semibold text-[#6366f1]">Corex</span>
                <span class="ml-1 text-xs text-[#64748b]">Analytics</span>
            </div>
            <nav class="flex-1 space-y-1 p-3">
                <a href="{{ route('console') }}" class="flex items-center gap-2 rounded px-3 py-2 text-sm text-[#94a3b8] transition hover:bg-[#1e293b] hover:text-[#e2e8f0]">
                    <span>⌘</span> Console
                </a>
                <a href="{{ route('console.chat') }}" class="flex items-center gap-2 rounded px-3 py-2 text-sm text-[#94a3b8] transition hover:bg-[#1e293b] hover:text-[#e2e8f0]">
                    <span>💬</span> Chat
                </a>
                <a href="{{ route('console.analytics') }}" class="flex items-center gap-2 rounded bg-[#1e293b] px-3 py-2 text-sm text-[#e2e8f0]">
                    <span>📊</span> Analytics
                </a>
                <a href="{{ route('console.settings') }}" class="flex items-center gap-2 rounded px-3 py-2 text-sm text-[#94a3b8] transition hover:bg-[#1e293b] hover:text-[#e2e8f0]">
                    <span>⚙</span> Settings
                </a>
            </nav>
            <div class="border-t border-[#1e293b] p-3 text-xs text-[#64748b]">
                v{{ config('app.version', '1.0.0') }}
            </div>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 overflow-auto">
            @livewire(\App\Livewire\Analytics\Dashboard::class, key('analytics-dashboard'))
        </main>
    </div>

    <script>
        function analyticsState() {
            return {
                theme: localStorage.getItem('theme') || 'dark',
                init() {
                    this.$watch('theme', val => {
                        localStorage.setItem('theme', val);
                        document.documentElement.classList.toggle('dark', val === 'dark');
                    });
                },
            };
        }
    </script>
    @livewireScripts
</body>
</html>
