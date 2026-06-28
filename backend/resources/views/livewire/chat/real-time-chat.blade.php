<div class="flex flex-col h-full" x-data="realtimeChat()">
    <div class="flex items-center justify-between px-4 py-2 border-b border-gray-700">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-medium text-gray-200">Chat</h3>
            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-gray-700 text-gray-300"
                  x-text="$wire.model"></span>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="model"
                    class="text-xs bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-200">
                <option value="gpt-4o">GPT-4o</option>
                <option value="gpt-4o-mini">GPT-4o Mini</option>
                <option value="claude-3-opus">Claude 3 Opus</option>
                <option value="claude-3-sonnet">Claude 3 Sonnet</option>
                <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
            </select>
            <button wire:click="clearChat" class="text-xs text-gray-400 hover:text-red-400 transition-colors">
                Clear
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="messagesContainer">
        <template x-for="(msg, i) in $wire.messages" :key="i">
            <div :class="'flex ' + (msg.role === 'user' ? 'justify-end' : 'justify-start')">
                <div :class="msg.role === 'user'
                    ? 'bg-indigo-600/30 border-indigo-500/30'
                    : 'bg-gray-700/50 border-gray-600/30'"
                     class="max-w-[80%] rounded-lg px-3 py-2 border">
                    <p class="text-sm text-gray-200 whitespace-pre-wrap" x-text="msg.content"></p>
                    <p class="text-[10px] text-gray-500 mt-1"
                       x-text="new Date(msg.timestamp).toLocaleTimeString()"></p>
                </div>
            </div>
        </template>

        <div x-show="$wire.loading" class="flex justify-start">
            <div class="bg-gray-700/50 border border-gray-600/30 rounded-lg px-3 py-2">
                <div class="flex gap-1">
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-700 p-3">
        <div class="flex gap-2">
            <input type="text"
                   wire:model="message"
                   wire:keydown.enter="sendMessage"
                   wire:keydown="typing"
                   placeholder="Type a message..."
                   class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-500">
            <button wire:click="sendMessage"
                    wire:loading.attr="disabled"
                    class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded-lg text-sm text-white transition-colors">
                Send
            </button>
        </div>
    </div>
</div>
