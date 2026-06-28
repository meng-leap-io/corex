<div class="relative" x-data="{ open: false }">
    <button @click="open = !open; $wire.toggleDropdown()"
            class="relative p-2 text-gray-400 hover:text-gray-200 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        <span x-show="$wire.unreadCount > 0"
              class="absolute -top-1 -right-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full"
              x-text="$wire.unreadCount"></span>
    </button>

    <div x-show="open"
         @click.outside="open = false"
         class="absolute right-0 mt-2 w-80 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50 max-h-96 overflow-hidden">
        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-700">
            <h4 class="text-sm font-medium text-gray-200">Notifications</h4>
            <button wire:click="markAllAsRead" class="text-xs text-indigo-400 hover:text-indigo-300">
                Mark all read
            </button>
        </div>

        <div class="overflow-y-auto max-h-72">
            <template x-for="notification in $wire.recentNotifications" :key="notification.id">
                <div class="px-3 py-2 border-b border-gray-700/50 hover:bg-gray-700/30 transition-colors"
                     :class="{'bg-gray-700/10': !notification.read_at}">
                    <div class="flex items-start gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-200 truncate" x-text="notification.title"></p>
                            <p class="text-xs text-gray-400 truncate" x-text="notification.body"></p>
                            <p class="text-[10px] text-gray-500 mt-1"
                               x-text="new Date(notification.created_at).toLocaleDateString()"></p>
                        </div>
                        <button wire:click="dismiss('{{ $notificationId ?? '' }}')"
                                class="text-gray-500 hover:text-gray-300">
                            &times;
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="$wire.recentNotifications.length === 0"
                 class="px-3 py-6 text-center text-sm text-gray-500">
                No notifications yet
            </div>
        </div>
    </div>
</div>
