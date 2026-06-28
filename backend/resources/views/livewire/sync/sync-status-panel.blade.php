<div class="space-y-6" x-data="{ tab: 'overview' }">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-ide-foreground">Sync Status</h2>

        <div class="flex items-center gap-2">
            <button
                wire:click="refreshData"
                class="btn-ide-sm"
                title="Refresh"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>

            <button
                wire:click="startSync"
                wire:loading.attr="disabled"
                class="btn-ide-primary"
            >
                <span wire:loading.remove wire:target="startSync">Sync Now</span>
                <span wire:loading wire:target="startSync">Syncing...</span>
            </button>
        </div>
    </div>

    <div class="flex gap-4 border-b border-ide-border">
        <button
            class="tab-ide"
            :class="{ 'active': tab === 'overview' }"
            @click="tab = 'overview'"
        >
            Overview
        </button>
        <button
            class="tab-ide"
            :class="{ 'active': tab === 'conflicts' }"
            @click="tab = 'conflicts'"
        >
            Conflicts
            @if(count($recentConflicts) > 0)
                <span class="ml-1 px-1.5 py-0.5 text-xs bg-red-500 text-white rounded-full">{{ count($recentConflicts) }}</span>
            @endif
        </button>
        <button
            class="tab-ide"
            :class="{ 'active': tab === 'history' }"
            @click="tab = 'history'"
        >
            History
        </button>
    </div>

    <div x-show="tab === 'overview'" class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card-ide">
                <div class="text-xs text-ide-muted uppercase tracking-wide">Progress</div>
                <div class="mt-1 text-2xl font-bold text-ide-foreground">{{ $syncProgress['progress'] ?? 100 }}%</div>
            </div>

            <div class="card-ide">
                <div class="text-xs text-ide-muted uppercase tracking-wide">Pending</div>
                <div class="mt-1 text-2xl font-bold text-primary-400">{{ $syncProgress['pending'] ?? 0 }}</div>
            </div>

            <div class="card-ide">
                <div class="text-xs text-ide-muted uppercase tracking-wide">Conflicts</div>
                <div class="mt-1 text-2xl font-bold text-red-400">{{ $syncProgress['conflicts'] ?? 0 }}</div>
            </div>

            <div class="card-ide">
                <div class="text-xs text-ide-muted uppercase tracking-wide">Queue</div>
                <div class="mt-1 text-2xl font-bold text-ide-foreground">{{ $queueStats['pending'] ?? 0 }}</div>
            </div>
        </div>

        <div class="card-ide">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-ide-foreground">Sync Progress</div>
                <div class="text-xs text-ide-muted">{{ $syncProgress['synced'] ?? 0 }} / {{ $syncProgress['total'] ?? 0 }} records</div>
            </div>
            <div class="w-full bg-ide-bg rounded-full h-2">
                <div
                    class="bg-primary-500 h-2 rounded-full transition-all duration-500"
                    style="width: {{ $syncProgress['progress'] ?? 100 }}%"
                ></div>
            </div>
        </div>

        <div class="card-ide">
            <div class="text-sm font-medium text-ide-foreground mb-3">Queue Details</div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-ide-muted">Pending:</span>
                    <span class="ml-1 text-ide-foreground font-mono">{{ $queueStats['pending'] ?? 0 }}</span>
                </div>
                <div>
                    <span class="text-ide-muted">Dead:</span>
                    <span class="ml-1 text-ide-foreground font-mono">{{ $queueStats['dead'] ?? 0 }}</span>
                </div>
            </div>

            @if(($queueStats['dead'] ?? 0) > 0)
                <button
                    wire:click="retryDead"
                    class="mt-3 btn-ide-sm text-yellow-400 border-yellow-400/30"
                >
                    Retry Dead Jobs
                </button>
            @endif
        </div>
    </div>

    <div x-show="tab === 'conflicts'" class="space-y-4">
        @if(count($recentConflicts) === 0)
            <div class="card-ide text-center py-8">
                <div class="text-ide-muted">No pending conflicts</div>
            </div>
        @else
            @foreach($recentConflicts as $conflict)
                <div class="card-ide">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-ide-foreground">
                                {{ $conflict['table_name'] }}:{{ substr($conflict['record_id'], 0, 8) }}
                            </div>
                            <div class="text-xs text-ide-muted mt-0.5">
                                Local v{{ $conflict['local_version'] }} vs Remote v{{ $conflict['remote_version'] }}
                                &middot; {{ $conflict['reason'] }}
                            </div>
                        </div>
                        <button
                            wire:click="viewConflict('{{ $conflict['id'] }}')"
                            class="btn-ide-sm"
                        >
                            View &amp; Resolve
                        </button>
                    </div>
                </div>
            @endforeach
        @endif

        @if($selectedConflictId && $conflictDetail)
            <div class="card-ide border-primary-500/30">
                <div class="text-sm font-medium text-ide-foreground mb-3">Resolve Conflict</div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <div class="text-xs text-ide-muted mb-1">Local Data</div>
                        <pre class="text-xs text-ide-foreground bg-ide-bg rounded p-2 overflow-auto max-h-48">{{ json_encode($conflictDetail['local_data'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    <div>
                        <div class="text-xs text-ide-muted mb-1">Remote Data</div>
                        <pre class="text-xs text-ide-foreground bg-ide-bg rounded p-2 overflow-auto max-h-48">{{ json_encode($conflictDetail['remote_data'] ?? [], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="text-xs text-ide-muted block mb-1">Resolution (JSON)</label>
                    <textarea
                        wire:model="resolutionJson"
                        class="w-full h-32 bg-ide-bg text-ide-foreground text-xs font-mono rounded border border-ide-border p-2 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    ></textarea>
                </div>

                <div class="flex gap-2">
                    <button
                        wire:click="resolveConflict"
                        class="btn-ide-primary"
                    >
                        Apply Resolution
                    </button>
                    <button
                        wire:click="$set('selectedConflictId', null)"
                        class="btn-ide-sm"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div x-show="tab === 'history'" class="space-y-2">
        @if(count($recentSnapshots) === 0)
            <div class="card-ide text-center py-8">
                <div class="text-ide-muted">No snapshots yet</div>
            </div>
        @else
            @foreach($recentSnapshots as $snapshot)
                <div class="card-ide flex items-center justify-between">
                    <div>
                        <div class="text-sm text-ide-foreground">
                            <span class="font-medium">{{ $snapshot['table_name'] }}</span>
                            :{{ substr($snapshot['record_id'], 0, 8) }}
                        </div>
                        <div class="text-xs text-ide-muted">
                            v{{ $snapshot['version'] }} &middot; {{ $snapshot['reason'] }}
                            &middot; {{ \Carbon\Carbon::parse($snapshot['created_at'])->diffForHumans() }}
                        </div>
                    </div>
                    <div class="text-xs text-ide-muted">{{ substr($snapshot['id'], 0, 8) }}</div>
                </div>
            @endforeach
        @endif
    </div>

    <div
        x-data
        x-on:sync-completed.window="$wire.refreshData()"
        x-on:sync-error.window="$wire.refreshData()"
    ></div>
</div>
