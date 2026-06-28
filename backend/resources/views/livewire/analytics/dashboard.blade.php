<div class="space-y-6 p-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-[#e2e8f0]">Analytics Dashboard</h2>
        <div class="flex items-center gap-3">
            <select
                wire:model.live="period"
                class="rounded-lg border border-[#334155] bg-[#1e293b] px-3 py-1.5 text-sm text-[#94a3b8] focus:border-[#6366f1] focus:outline-none"
            >
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="90d">Last 90 Days</option>
            </select>
            <button
                wire:click="refresh"
                class="rounded-lg border border-[#334155] bg-[#1e293b] px-3 py-1.5 text-sm text-[#94a3b8] transition hover:border-[#6366f1] hover:text-[#e2e8f0]"
            >
                ↻ Refresh
            </button>
            <button
                wire:click="recordSnapshot"
                class="rounded-lg border border-[#334155] bg-[#1e293b] px-3 py-1.5 text-sm text-[#94a3b8] transition hover:border-[#22c55e] hover:text-[#e2e8f0]"
            >
                ⊞ Snapshot
            </button>
        </div>
    </div>

    @if ($loading)
        <div class="flex items-center justify-center py-12 text-[#64748b]">
            <span>Loading analytics...</span>
        </div>
    @else
        {{-- Overview Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Total Events</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ number_format($overview['total_events']) }}</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Unique Users</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ number_format($overview['unique_users']) }}</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Page Views</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ number_format($overview['total_page_views']) }}</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Avg Response</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ $overview['avg_response_time'] }}ms</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Errors (5m)</div>
                <div class="mt-1 text-2xl font-bold {{ $overview['error_count_5m'] > 0 ? 'text-[#ef4444]' : 'text-[#22c55e]' }}">
                    {{ $overview['error_count_5m'] }}
                </div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Request Rate</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ $overview['request_rate'] }}/min</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">P95 Response</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ $overview['p95_response'] }}ms</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">P99 Response</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ $overview['p99_response'] }}ms</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Feature Usage</div>
                <div class="mt-1 text-2xl font-bold text-[#e2e8f0]">{{ number_format($overview['feature_usage_count']) }}</div>
            </div>
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <div class="text-xs text-[#64748b]">Total Errors</div>
                <div class="mt-1 text-2xl font-bold {{ $overview['total_errors'] > 0 ? 'text-[#ef4444]' : 'text-[#22c55e]' }}">
                    {{ number_format($overview['total_errors']) }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Event Breakdown --}}
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <h3 class="mb-3 text-sm font-semibold text-[#e2e8f0]">Event Breakdown</h3>
                @if (empty($eventBreakdown))
                    <p class="text-sm text-[#64748b]">No events recorded in this period.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($eventBreakdown as $event)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-[#94a3b8]">{{ $event['event_type'] }}</span>
                                <span class="text-sm font-medium text-[#e2e8f0]">{{ number_format($event['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Top Pages --}}
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <h3 class="mb-3 text-sm font-semibold text-[#e2e8f0]">Top Pages</h3>
                @if (empty($topPages))
                    <p class="text-sm text-[#64748b]">No page views recorded.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($topPages as $page)
                            <div class="flex items-center justify-between">
                                <span class="truncate text-sm text-[#94a3b8]" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="font-medium text-[#e2e8f0]">{{ number_format($page['views']) }}</span>
                                    <span class="text-[#64748b]">{{ round($page['avg_duration'], 0) }}ms</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Feature Usage --}}
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <h3 class="mb-3 text-sm font-semibold text-[#e2e8f0]">Feature Usage</h3>
                @if (empty($featureUsage))
                    <p class="text-sm text-[#64748b]">No feature usage recorded.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($featureUsage as $usage)
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm text-[#94a3b8]">{{ $usage['feature'] }}</span>
                                    <span class="text-xs text-[#64748b]">/{{ $usage['action'] }}</span>
                                </div>
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="font-medium text-[#e2e8f0]">{{ number_format($usage['count']) }}</span>
                                    <span class="text-[#64748b]">{{ round($usage['avg_duration'], 0) }}ms</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Error Summary --}}
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <h3 class="mb-3 text-sm font-semibold text-[#e2e8f0]">Error Summary</h3>
                @if (empty($errorSummary))
                    <p class="text-sm text-[#64748b]">No errors recorded in this period.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($errorSummary as $error)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded bg-[#7f1d1d] px-1.5 py-0.5 text-xs font-medium text-[#fca5a5]">
                                        {{ $error['status_code'] }}
                                    </span>
                                    <span class="truncate text-sm text-[#94a3b8]" title="{{ $error['path'] }}">{{ $error['path'] }}</span>
                                </div>
                                <span class="text-sm font-medium text-[#ef4444]">{{ number_format($error['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Performance History --}}
        <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
            <h3 class="mb-3 text-sm font-semibold text-[#e2e8f0]">Request Volume & Response Time</h3>
            @if (empty($performanceHistory))
                <p class="text-sm text-[#64748b]">No performance data recorded.</p>
            @else
                <div class="overflow-x-auto">
                    <div class="flex gap-1" style="min-height: 120px; align-items: flex-end;">
                        @php
                            $maxRequests = max(array_column($performanceHistory, 'requests'));
                            $maxMs = max(array_column($performanceHistory, 'avg_duration'));
                        @endphp
                        @foreach ($performanceHistory as $point)
                            @php
                                $barHeight = $maxRequests > 0 ? max(3, ($point['requests'] / $maxRequests) * 100) : 3;
                                $lineOpacity = $maxMs > 0 ? max(0.2, $point['avg_duration'] / $maxMs) : 0.2;
                            @endphp
                            <div class="flex flex-col items-center" style="width: 24px;">
                                <div class="relative flex w-full items-end justify-center" style="height: 120px;">
                                    <div
                                        class="w-3 rounded-t bg-[#6366f1] transition-all"
                                        style="height: {{ $barHeight }}px; opacity: {{ $lineOpacity + 0.3 }};"
                                        title="{{ date('M j H:00', strtotime($point['hour'])) }}: {{ number_format($point['requests']) }} req, {{ round($point['avg_duration'], 0) }}ms"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Latest Snapshot --}}
        @if (!empty($latestSnapshot))
            <div class="rounded-lg border border-[#334155] bg-[#1e293b] p-4">
                <h3 class="mb-3 text-sm font-semibold text-[#e2e8f0]">Latest Performance Snapshot</h3>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    @if ($latestSnapshot['cpu_load'] !== null)
                        <div>
                            <span class="text-xs text-[#64748b]">CPU Load</span>
                            <div class="text-sm font-medium text-[#e2e8f0]">{{ round($latestSnapshot['cpu_load'], 2) }}</div>
                        </div>
                    @endif
                    @if ($latestSnapshot['memory_used_mb'] !== null)
                        <div>
                            <span class="text-xs text-[#64748b]">Memory Used</span>
                            <div class="text-sm font-medium text-[#e2e8f0]">{{ round($latestSnapshot['memory_used_mb'], 0) }} MB</div>
                        </div>
                    @endif
                    @if ($latestSnapshot['active_connections'] !== null)
                        <div>
                            <span class="text-xs text-[#64748b]">DB Connections</span>
                            <div class="text-sm font-medium text-[#e2e8f0]">{{ $latestSnapshot['active_connections'] }}</div>
                        </div>
                    @endif
                    @if ($latestSnapshot['queue_size'] !== null)
                        <div>
                            <span class="text-xs text-[#64748b]">Queue Size</span>
                            <div class="text-sm font-medium text-[#e2e8f0]">{{ $latestSnapshot['queue_size'] }}</div>
                        </div>
                    @endif
                </div>
                <div class="mt-2 text-xs text-[#64748b]">
                    Recorded: {{ $latestSnapshot['recorded_at'] }}
                </div>
            </div>
        @endif
    @endif
</div>
