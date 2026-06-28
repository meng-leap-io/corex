<div class="flex flex-col gap-2" x-data>
    <div class="flex items-center justify-between">
        <h4 class="text-xs font-medium text-gray-400 uppercase tracking-wider">Team Online</h4>
        <span class="text-xs text-gray-500" x-text="$wire.onlineCount + ' / ' + $wire.totalMembers + ' online'"></span>
    </div>

    <div class="flex flex-col gap-1">
        @foreach($members as $member)
            <div class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-700/30 transition-colors">
                <div class="w-6 h-6 rounded-full bg-gray-600 flex items-center justify-center text-[10px] text-gray-300">
                    {{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}
                </div>
                <span class="text-sm text-gray-300 flex-1">{{ $member['name'] ?? 'Unknown' }}</span>
                <div class="w-2 h-2 rounded-full"
                     :class="getStatusColor('{{ $member['id'] ?? $member['user_id'] }}')"></div>
            </div>
        @endforeach
    </div>

    <div x-show="$wire.members.length === 0" class="text-xs text-gray-500 text-center py-2">
        No team members yet
    </div>
</div>
