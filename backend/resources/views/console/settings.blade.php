<div x-show="showSettings" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
     @click.away="showSettings = false" @keydown.escape.window="showSettings = false">

    <div x-show="showSettings" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="w-full max-w-2xl rounded-xl border shadow-2xl overflow-hidden"
         :class="theme === 'dark' ? 'bg-ide-sidebar border-ide-border' : 'bg-white border-light-border'">

        <div class="flex items-center justify-between px-6 py-4 border-b"
             :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'">
            <h2 class="text-base font-semibold" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Settings</h2>
            <button @click="showSettings = false" class="p-1.5 rounded transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-6 space-y-8">

            <div>
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"
                    :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">
                    <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    Appearance
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium mb-1.5" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Theme</label>
                        <div class="flex gap-2">
                            <button @click="theme = 'dark'"
                                    class="flex-1 flex flex-col items-center gap-2 p-3 rounded-lg border-2 transition-all"
                                    :class="theme === 'dark'
                                        ? 'border-primary-500 bg-ide-panel'
                                        : 'border-transparent bg-ide-panel hover:border-ide-border'">
                                <div class="w-full h-8 rounded bg-slate-800 flex items-center justify-center text-[8px] text-slate-300">🐱</div>
                                <span class="text-xs" :class="theme === 'dark' ? 'text-primary-400 font-medium' : 'text-ide-muted'">Dark</span>
                            </button>
                            <button @click="theme = 'light'"
                                    class="flex-1 flex flex-col items-center gap-2 p-3 rounded-lg border-2 transition-all"
                                    :class="theme === 'light'
                                        ? 'border-primary-500 bg-light-panel'
                                        : 'border-transparent bg-light-panel hover:border-light-border'">
                                <div class="w-full h-8 rounded bg-white border border-slate-200 flex items-center justify-center text-[8px] text-slate-400">☀️</div>
                                <span class="text-xs" :class="theme === 'light' ? 'text-primary-500 font-medium' : 'text-light-muted'">Light</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1.5" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Font Size</label>
                        <div class="flex items-center gap-3">
                            <button @click="fontSize = Math.max(10, fontSize - 1)"
                                    class="px-2.5 py-1 rounded text-xs border transition-colors"
                                    :class="theme === 'dark' ? 'border-ide-border text-ide-muted hover:bg-ide-hover' : 'border-light-border text-light-muted hover:bg-light-hover'">A-</button>
                            <span class="text-sm font-mono min-w-[3ch] text-center"
                                  :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'" x-text="fontSize"></span>
                            <button @click="fontSize = Math.min(30, fontSize + 1)"
                                    class="px-2.5 py-1 rounded text-xs border transition-colors"
                                    :class="theme === 'dark' ? 'border-ide-border text-ide-muted hover:bg-ide-hover' : 'border-light-border text-light-muted hover:bg-light-hover'">A+</button>
                            <input type="range" x-model="fontSize" min="10" max="30" class="flex-1 h-1 accent-primary-500">
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-medium mb-1.5" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Font Family</label>
                    <select x-model="fontFamily"
                            class="w-full text-xs rounded-lg px-3 py-2 border outline-none"
                            :class="theme === 'dark' ? 'bg-ide-panel border-ide-border text-ide-text' : 'bg-white border-light-border text-light-text'">
                        <option value="'JetBrains Mono', monospace">JetBrains Mono</option>
                        <option value="'Fira Code', monospace">Fira Code</option>
                        <option value="'Cascadia Code', monospace">Cascadia Code</option>
                        <option value="'Source Code Pro', monospace">Source Code Pro</option>
                        <option value="'IBM Plex Mono', monospace">IBM Plex Mono</option>
                        <option value="monospace">Default Monospace</option>
                    </select>
                </div>
            </div>

            <div class="border-t" :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'"></div>

            <div>
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"
                    :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">
                    <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Editor
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Tab Size</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Number of spaces per tab</p>
                        </div>
                        <div class="flex gap-1">
                            <button @click="tabSize = 2"
                                    class="px-3 py-1.5 text-xs rounded border transition-colors"
                                    :class="tabSize === 2
                                        ? (theme === 'dark' ? 'border-primary-500 bg-primary-500/20 text-primary-400' : 'border-primary-500 bg-primary-50 text-primary-600')
                                        : (theme === 'dark' ? 'border-ide-border text-ide-muted hover:bg-ide-hover' : 'border-light-border text-light-muted hover:bg-light-hover')">2</button>
                            <button @click="tabSize = 4"
                                    class="px-3 py-1.5 text-xs rounded border transition-colors"
                                    :class="tabSize === 4
                                        ? (theme === 'dark' ? 'border-primary-500 bg-primary-500/20 text-primary-400' : 'border-primary-500 bg-primary-50 text-primary-600')
                                        : (theme === 'dark' ? 'border-ide-border text-ide-muted hover:bg-ide-hover' : 'border-light-border text-light-muted hover:bg-light-hover')">4</button>
                            <button @click="tabSize = 8"
                                    class="px-3 py-1.5 text-xs rounded border transition-colors"
                                    :class="tabSize === 8
                                        ? (theme === 'dark' ? 'border-primary-500 bg-primary-500/20 text-primary-400' : 'border-primary-500 bg-primary-50 text-primary-600')
                                        : (theme === 'dark' ? 'border-ide-border text-ide-muted hover:bg-ide-hover' : 'border-light-border text-light-muted hover:bg-light-hover')">8</button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Word Wrap</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Wrap lines at editor width</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="wordWrap" class="sr-only peer">
                            <div class="w-9 h-5 rounded-full transition-colors peer-checked:bg-primary-500 bg-slate-300 dark:bg-slate-600"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:translate-x-4 shadow"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Line Numbers</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Display line numbers gutter</p>
                        </div>
                        <select x-model="lineNumbers"
                                class="text-xs rounded-lg px-2.5 py-1.5 border outline-none"
                                :class="theme === 'dark' ? 'bg-ide-panel border-ide-border text-ide-text' : 'bg-white border-light-border text-light-text'">
                            <option value="on">On</option>
                            <option value="off">Off</option>
                            <option value="relative">Relative</option>
                            <option value="interval">Interval (10)</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Minimap</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Show code minimap overview</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="minimap" class="sr-only peer">
                            <div class="w-9 h-5 rounded-full transition-colors peer-checked:bg-primary-500 bg-slate-300 dark:bg-slate-600"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:translate-x-4 shadow"></div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="border-t" :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'"></div>

            <div>
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"
                    :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">
                    <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                    Keybindings
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Keybinding Preset</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Choose your preferred keybinding style</p>
                        </div>
                        <select x-model="keybindings"
                                class="text-xs rounded-lg px-2.5 py-1.5 border outline-none"
                                :class="theme === 'dark' ? 'bg-ide-panel border-ide-border text-ide-text' : 'bg-white border-light-border text-light-text'">
                            <option value="default">VS Code (Default)</option>
                            <option value="sublime">Sublime Text</option>
                            <option value="vim">Vim</option>
                            <option value="emacs">Emacs</option>
                            <option value="atom">Atom</option>
                        </select>
                    </div>
                    <div class="rounded-lg p-3 text-xs space-y-2"
                         :class="theme === 'dark' ? 'bg-ide-panel' : 'bg-light-panel'">
                        <div class="flex items-center justify-between">
                            <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Save file</span>
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                                 :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text'">Ctrl+S</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Toggle terminal</span>
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                                 :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text'">Ctrl+`</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Command palette</span>
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                                 :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text'">Ctrl+Shift+P</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Find in file</span>
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                                 :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text'">Ctrl+F</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Find and replace</span>
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                                 :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text'">Ctrl+H</kbd>
                        </div>
                        <div class="flex items-center justify-between">
                            <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Toggle comment</span>
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono"
                                 :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text'">Ctrl+/</kbd>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t" :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'"></div>

            <div>
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"
                    :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">
                    <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Behavior
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Auto Save</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Automatically save files after changes</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="autoSave" class="sr-only peer">
                            <div class="w-9 h-5 rounded-full transition-colors peer-checked:bg-primary-500 bg-slate-300 dark:bg-slate-600"></div>
                            <div class="absolute left-0.5 top-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:translate-x-4 shadow"></div>
                        </label>
                    </div>
                    <div x-show="autoSave" class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Auto Save Delay</p>
                            <p class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Delay before auto-saving (ms)</p>
                        </div>
                        <select class="text-xs rounded-lg px-2.5 py-1.5 border outline-none"
                                :class="theme === 'dark' ? 'bg-ide-panel border-ide-border text-ide-text' : 'bg-white border-light-border text-light-text'">
                            <option>1000</option>
                            <option>2000</option>
                            <option selected>3000</option>
                            <option>5000</option>
                            <option>10000</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="border-t" :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'"></div>

            <div>
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2"
                    :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">
                    <svg class="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    About
                </h3>
                <div class="rounded-lg p-3 text-xs space-y-2"
                     :class="theme === 'dark' ? 'bg-ide-panel' : 'bg-light-panel'">
                    <div class="flex items-center justify-between">
                        <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Version</span>
                        <span class="font-mono" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">1.0.0</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Editor</span>
                        <span class="font-mono" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Monaco 0.45.0</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Terminal</span>
                        <span class="font-mono" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">xterm.js 5.3.0</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">Framework</span>
                        <span class="font-mono" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Laravel 11</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t"
             :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'">
            <button @click="showSettings = false"
                    class="px-4 py-2 text-xs font-medium rounded-lg border transition-colors"
                    :class="theme === 'dark' ? 'border-ide-border text-ide-muted hover:bg-ide-hover' : 'border-light-border text-light-muted hover:bg-light-hover'">Done</button>
            <button @click="showSettings = false"
                    class="px-4 py-2 text-xs font-medium rounded-lg text-white bg-primary-500 hover:bg-primary-600 transition-colors">Apply & Close</button>
        </div>
    </div>
</div>
