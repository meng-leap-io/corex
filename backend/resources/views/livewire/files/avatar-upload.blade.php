<div class="flex flex-col items-center gap-3" x-data>
    <div class="relative">
        <div class="w-20 h-20 rounded-full overflow-hidden bg-gray-700 border-2 border-gray-600">
            @if($previewUrl)
                <img src="{{ $previewUrl }}" alt="Avatar" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center text-2xl text-gray-500 font-medium">
                    {{ strtoupper(substr(auth()->user()?->name ?? '?', 0, 1)) }}
                </div>
            @endif
        </div>
        <label class="absolute bottom-0 right-0 w-6 h-6 bg-indigo-600 hover:bg-indigo-500 rounded-full flex items-center justify-center cursor-pointer transition-colors">
            <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <input type="file" wire:model="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
        </label>
    </div>

    <div wire:loading wire:target="avatar" class="text-xs text-indigo-400">Processing...</div>

    @error('avatar') <p class="text-xs text-red-400">{{ $message }}</p> @enderror

    <div class="flex gap-2">
        <button wire:click="save" wire:loading.attr="disabled" wire:target="avatar,save"
                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded text-xs text-white transition-colors"
                {{ $avatar ? '' : 'disabled' }}>
            <span wire:loading.remove wire:target="save">Save</span>
            <span wire:loading wire:target="save">Uploading...</span>
        </button>
        @if($previewUrl)
            <button wire:click="remove" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 rounded text-xs text-gray-300 transition-colors">
                Remove
            </button>
        @endif
    </div>
</div>
