<div class="flex flex-col h-full" x-data>
    <div class="flex items-center justify-between px-4 py-2 border-b border-gray-700">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-medium text-gray-200">Files</h3>
            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-gray-700 text-gray-300">
                <span x-text="$wire.stats.total"></span> files
            </span>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model.live="bucket" class="text-xs bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-200">
                <option value="projects">Projects</option>
                <option value="documents">Documents</option>
                <option value="exports">Exports</option>
            </select>
            <button wire:click="toggleView" class="p-1.5 rounded text-gray-400 hover:text-gray-200 hover:bg-gray-700 transition-colors">
                <template x-if="$wire.view === 'grid'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </template>
                <template x-if="$wire.view === 'list'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2zm0 8a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1v-2z"/></svg>
                </template>
            </button>
            <button wire:click="$set('showUploadModal', true)" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 rounded text-xs text-white transition-colors">
                Upload
            </button>
        </div>
    </div>

    <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-700/50">
        <div class="relative flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search files..."
                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-1.5 text-xs text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-500">
        </div>
        <div class="flex gap-1">
            <button wire:click="setFilter('all')" class="px-2 py-1 text-xs rounded transition-colors"
                    :class="$wire.filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:text-gray-200'">All</button>
            <button wire:click="setFilter('image')" class="px-2 py-1 text-xs rounded transition-colors"
                    :class="$wire.filter === 'image' ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:text-gray-200'">Images</button>
            <button wire:click="setFilter('document')" class="px-2 py-1 text-xs rounded transition-colors"
                    :class="$wire.filter === 'document' ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:text-gray-200'">Docs</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4">
        @if($view === 'grid')
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($files as $file)
                    <div class="group relative bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden hover:border-indigo-500/50 transition-all">
                        <div class="aspect-square flex items-center justify-center bg-gray-800">
                            @if($file->isImage())
                                <img src="{{ $file->url }}" alt="{{ $file->original_name }}"
                                     class="w-full h-full object-cover" loading="lazy">
                            @else
                                <svg class="w-12 h-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="p-2">
                            <p class="text-xs text-gray-300 truncate" title="{{ $file->original_name }}">{{ $file->original_name }}</p>
                            <p class="text-[10px] text-gray-500">{{ $file->formatted_size }}</p>
                        </div>
                        <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                            <button wire:click="shareFile('{{ $file->id }}')" class="p-1 bg-gray-900/80 rounded text-gray-400 hover:text-indigo-400" title="Share">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            </button>
                            <button wire:click="confirmDelete('{{ $file->id }}')" class="p-1 bg-gray-900/80 rounded text-gray-400 hover:text-red-400" title="Delete">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col gap-1">
                @foreach($files as $file)
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-800/50 transition-colors group">
                        <div class="w-8 h-8 rounded bg-gray-700 flex items-center justify-center shrink-0">
                            @if($file->isImage())
                                <img src="{{ $file->url }}" alt="" class="w-8 h-8 rounded object-cover">
                            @else
                                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-300 truncate">{{ $file->original_name }}</p>
                            <p class="text-[10px] text-gray-500">{{ $file->formatted_size }} &middot; {{ $file->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="shareFile('{{ $file->id }}')" class="p-1.5 rounded text-gray-500 hover:text-indigo-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            </button>
                            <button wire:click="confirmDelete('{{ $file->id }}')" class="p-1.5 rounded text-gray-500 hover:text-red-400">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($files->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <svg class="w-16 h-16 text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm text-gray-500 mb-1">No files yet</p>
                <p class="text-xs text-gray-600">Upload files to get started.</p>
            </div>
        @endif
    </div>

    @if($files->hasPages())
        <div class="px-4 py-2 border-t border-gray-700">
            {{ $files->links() }}
        </div>
    @endif

    @if($showUploadModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="$set('showUploadModal', false)">
            <div class="bg-gray-800 border border-gray-700 rounded-lg w-96 p-6 shadow-xl">
                <h4 class="text-sm font-medium text-gray-200 mb-4">Upload File</h4>
                <input type="file" wire:model="upload"
                       class="w-full text-xs text-gray-400 file:mr-3 file:py-2 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
                <div wire:loading wire:target="upload" class="text-xs text-indigo-400 mt-2">Uploading...</div>
                @error('upload') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showUploadModal', false)" class="px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200">Cancel</button>
                    <button wire:click="uploadFile" wire:loading.attr="disabled"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 rounded text-xs text-white disabled:opacity-50">
                        Upload
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="$set('showDeleteModal', false)">
            <div class="bg-gray-800 border border-gray-700 rounded-lg w-80 p-6 shadow-xl">
                <h4 class="text-sm font-medium text-gray-200 mb-2">Delete File?</h4>
                <p class="text-xs text-gray-400 mb-4">This action cannot be undone.</p>
                <div class="flex justify-end gap-2">
                    <button wire:click="$set('showDeleteModal', false)" class="px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200">Cancel</button>
                    <button wire:click="deleteFile" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 rounded text-xs text-white">Delete</button>
                </div>
            </div>
        </div>
    @endif

    @if($showShareModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="$set('showShareModal', false)">
            <div class="bg-gray-800 border border-gray-700 rounded-lg w-96 p-6 shadow-xl">
                <h4 class="text-sm font-medium text-gray-200 mb-4">Share Link</h4>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $shareUrl }}"
                           class="flex-1 bg-gray-700 border border-gray-600 rounded px-3 py-2 text-xs text-gray-200">
                    <button wire:click="copyShareUrl" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 rounded text-xs text-white">Copy</button>
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <label class="text-xs text-gray-400">Expires:</label>
                    <select wire:model.live="shareExpiresIn" class="text-xs bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-200">
                        <option value="3600">1 hour</option>
                        <option value="86400">24 hours</option>
                        <option value="604800">7 days</option>
                    </select>
                </div>
                <div class="flex justify-end mt-4">
                    <button wire:click="$set('showShareModal', false)" class="px-3 py-1.5 text-xs text-gray-400 hover:text-gray-200">Close</button>
                </div>
            </div>
        </div>
    @endif
</div>
