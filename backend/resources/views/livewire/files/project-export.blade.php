<div class="flex flex-col gap-4" x-data>
    <div>
        <h4 class="text-sm font-medium text-gray-200 mb-3">Export Project Files</h4>

        @if($this->projectFiles->isNotEmpty())
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-gray-500">{{ count($selectedFiles) }} of {{ $this->projectFiles->count() }} selected</span>
                <div class="flex gap-2">
                    <button wire:click="selectAll" class="text-xs text-indigo-400 hover:text-indigo-300">Select All</button>
                    <button wire:click="deselectAll" class="text-xs text-gray-500 hover:text-gray-400">Deselect</button>
                </div>
            </div>

            <div class="flex flex-col gap-1 max-h-48 overflow-y-auto">
                @foreach($this->projectFiles as $file)
                    <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-700/30 cursor-pointer transition-colors">
                        <input type="checkbox" wire:click="toggleFile('{{ $file->id }}')"
                               {{ in_array($file->id, $selectedFiles) ? 'checked' : '' }}
                               class="rounded border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs text-gray-300 truncate flex-1">{{ $file->original_name }}</span>
                        <span class="text-[10px] text-gray-500">{{ $file->formatted_size }}</span>
                    </label>
                @endforeach
            </div>
        @else
            <p class="text-xs text-gray-500">No files available for export.</p>
        @endif

        <button wire:click="export" wire:loading.attr="disabled"
                class="mt-3 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded text-xs text-white transition-colors"
                {{ empty($selectedFiles) ? 'disabled' : '' }}>
            <span wire:loading.remove wire:target="export">Export to Supabase Storage</span>
            <span wire:loading wire:target="export">Exporting...</span>
        </button>

        @if($exportPath)
            <div class="mt-3 p-3 bg-gray-800 rounded-lg border border-gray-700">
                <p class="text-xs text-green-400 mb-1">Export complete</p>
                <p class="text-[10px] text-gray-500">Path: {{ $exportPath }}</p>
            </div>
        @endif
    </div>

    <div class="border-t border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-200 mb-3">Import</h4>

        <input type="text" wire:model="importToken" placeholder="Export path to import..."
               class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-xs text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-500 mb-3">

        <button wire:click="import" wire:loading.attr="disabled"
                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 rounded text-xs text-gray-200 transition-colors"
                {{ empty($importToken) ? 'disabled' : '' }}>
            <span wire:loading.remove wire:target="import">Import Files</span>
            <span wire:loading wire:target="import">Importing...</span>
        </button>

        @if(!empty($importedFiles))
            <div class="mt-3 p-3 bg-gray-800 rounded-lg border border-gray-700">
                <p class="text-xs text-green-400 mb-1">Imported {{ count($importedFiles) }} files</p>
                @foreach($importedFiles as $imported)
                    <p class="text-[10px] text-gray-500">{{ $imported->original_name }}</p>
                @endforeach
            </div>
        @endif
    </div>
</div>
