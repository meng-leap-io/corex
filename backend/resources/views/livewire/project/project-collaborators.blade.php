<div class="flex items-center gap-2" x-data>
    <div class="flex -space-x-2">
        @foreach($projectUsers as $user)
            <div class="relative" title="{{ $user['name'] }}">
                <div class="w-7 h-7 rounded-full bg-gray-600 border-2 border-gray-800 flex items-center justify-center text-xs text-gray-300">
                    {{ strtoupper(substr($user['name'], 0, 1)) }}
                </div>
                <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border border-gray-800"
                     :class="isOnline('{{ $user['id'] }}') ? 'bg-green-500' : 'bg-gray-500'"></div>
            </div>
        @endforeach
    </div>

    <span class="text-xs text-gray-500" x-show="$wire.onlineCount > 0">
        <span x-text="$wire.onlineCount"></span> online
    </span>
</div>
