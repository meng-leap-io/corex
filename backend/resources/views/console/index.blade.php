<!DOCTYPE html>
<html lang="en" class="h-full" x-data="consoleState()" :class="{ 'dark': theme === 'dark' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title x-text="activeFile ? activeFile.name + ' - Corex Console' : 'Corex Console'">Corex Console</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @if(config('nativephp.license'))
    {{-- NativePHP Desktop Bridge --}}
    <script>
        // Inject native bridge into Alpine
        document.addEventListener('alpine:init', () => {
            if (typeof window.native !== 'undefined') {
                // Menu actions handler
                window.native.on('menu-action', (action) => {
                    if (window.consoleState) {
                        switch (action) {
                            case 'new-file':
                                window.consoleState.newFile();
                                break;
                            case 'open-file':
                                window.consoleState.openFileDialog();
                                break;
                            case 'save-file':
                                window.consoleState.saveCurrentFile();
                                break;
                            case 'save-file-as':
                                window.consoleState.saveFileAs();
                                break;
                            case 'toggle-terminal':
                                window.consoleState.showTerminal = !window.consoleState.showTerminal;
                                break;
                            case 'toggle-chat':
                                window.consoleState.showChat = !window.consoleState.showChat;
                                break;
                            case 'toggle-sidebar':
                                window.consoleState.showSidebar = !window.consoleState.showSidebar;
                                break;
                            case 'open-settings':
                                window.consoleState.showSettings = true;
                                break;
                            case 'new-chat':
                                window.consoleState.newChat?.();
                                break;
                        }
                    }
                });

                window.native.on('files-opened', (filePaths) => {
                    if (window.consoleState) {
                        filePaths.forEach(p => window.consoleState.loadFileFromPath(p));
                    }
                });

                window.native.on('folder-opened', (folderPath) => {
                    if (window.consoleState) {
                        window.consoleState.loadFolder(folderPath);
                    }
                });
            }
        });
    </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="{{ asset('js/supabase-realtime.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-web-links@0.9.0/lib/xterm-addon-web-links.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81' },
                        ide: { bg: '#1e1e2e', sidebar: '#252536', panel: '#2a2a3c', border: '#363650', hover: '#313145', active: '#3b3b52', text: '#cdd6f4', muted: '#6c7086', accent: '#89b4fa' },
                        light: { bg: '#ffffff', sidebar: '#f8fafc', panel: '#f1f5f9', border: '#e2e8f0', hover: '#e2e8f0', active: '#cbd5e1', text: '#0f172a', muted: '#64748b', accent: '#6366f1' },
                    },
                    fontFamily: { mono: ['JetBrains Mono', 'Fira Code', 'Cascadia Code', 'monospace'] }
                }
            }
        }
    </script>
    <style>
        html, body { height: 100%; overflow: hidden; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #45475a; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #585b70; }

        .resize-handle { position: relative; }
        .resize-handle::after { content: ''; position: absolute; transition: background .15s; }
        .resize-handle:hover::after { background: #6366f1; }
        .resize-handle-x { cursor: col-resize; }
        .resize-handle-x::after { top: 0; bottom: 0; left: 50%; width: 2px; transform: translateX(-50%); }
        .resize-handle-y { cursor: row-resize; }
        .resize-handle-y::after { left: 0; right: 0; top: 50%; height: 2px; transform: translateY(-50%); }

        .tab-active { border-bottom-color: #6366f1; color: #cdd6f4; }
        .dark .tab-active { color: #cdd6f4; }
        .tab-active { color: #0f172a; }
        .tree-item { transition: background .1s; border-radius: 4px; cursor: pointer; }
        .tree-item:hover { background: rgba(99,102,241,.08); }
        .tree-item.active { background: rgba(99,102,241,.15); }
        .context-menu { animation: fadeIn .1s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes slideIn { from { opacity: 0; transform: translateX(8px); } to { opacity: 1; transform: translateX(0); } }
        .chat-enter { animation: slideIn .2s ease; }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
        .monaco-editor .margin { background: transparent !important; }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="h-full flex flex-col text-[13px] font-sans"
      :class="theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-light-bg text-light-text'"
      @keydown="handleKeydown($event)">

    <header class="flex items-center h-10 shrink-0 px-3 gap-2 border-b select-none"
            :class="theme === 'dark' ? 'bg-ide-sidebar border-ide-border' : 'bg-light-sidebar border-light-border'">
        <div class="flex items-center gap-1.5 mr-3">
            <div class="w-5 h-5 rounded bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center text-[9px] font-bold text-white">C</div>
            <span class="text-xs font-semibold" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Corex</span>
        </div>

        <div class="flex items-center gap-1">
            <template x-for="tab in openTabs" :key="tab.path">
                <button @click="switchTab(tab)" @mousedown.middle="closeTab(tab)"
                        class="flex items-center gap-1.5 px-3 py-1 rounded text-xs transition-colors whitespace-nowrap"
                        :class="activeFile?.path === tab.path
                            ? theme === 'dark' ? 'bg-ide-bg text-ide-text border-t-2 border-primary-500' : 'bg-light-bg text-light-text border-t-2 border-primary-500'
                            : theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'">
                    <span x-text="tab.name"></span>
                    <button @click.stop="closeTab(tab)" class="hover:bg-ide-active rounded p-0.5 opacity-60 hover:opacity-100">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </button>
            </template>
        </div>

        <div class="flex-1"></div>

        <div class="flex items-center gap-1">
            <button @click="showChat = !showChat" class="p-1.5 rounded transition-colors"
                    :class="showChat ? 'bg-primary-500/20 text-primary-400' : (theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover')"
                    title="Toggle AI Chat">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </button>
            <button @click="showTerminal = !showTerminal" class="p-1.5 rounded transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'"
                    title="Toggle Terminal">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </button>
            <button @click="showSettings = !showSettings" class="p-1.5 rounded transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'"
                    title="Settings">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>
            <div class="w-px h-5 mx-1" :class="theme === 'dark' ? 'bg-ide-border' : 'bg-light-border'"></div>
            <a href="{{ route('home') }}" class="p-1.5 rounded transition-colors"
               :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'" title="Back to landing">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </a>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <aside x-show="showSidebar" class="flex flex-col w-52 shrink-0 border-r"
               :class="theme === 'dark' ? 'bg-ide-sidebar border-ide-border' : 'bg-light-sidebar border-light-border'">
            <div class="flex items-center gap-1 px-3 py-1.5 border-b text-xs font-medium uppercase tracking-wider"
                 :class="theme === 'dark' ? 'border-ide-border text-ide-muted' : 'border-light-border text-light-muted'">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                Explorer
            </div>
            <div class="flex-1 overflow-y-auto p-1.5" x-data="fileExplorer()">
                <template x-for="item in files" :key="item.path">
                    <div>
                        <div @click="toggleFolder(item)" @contextmenu.prevent="openContext($event, item)"
                             class="tree-item flex items-center gap-1.5 px-2 py-1 text-xs"
                             :class="item.path === activeFile?.path ? 'active' : ''"
                             :style="{ paddingLeft: (item.depth * 12 + 8) + 'px' }">
                            <template x-if="item.type === 'folder'">
                                <svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="item.open ? 'rotate-90' : ''"
                                     :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </template>
                            <template x-if="item.type === 'folder'">
                                <svg class="w-4 h-4 shrink-0" :class="item.open ? 'text-amber-400' : 'text-amber-300'" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                            </template>
                            <template x-if="item.type === 'file'">
                                <span class="text-sky-400" x-html="fileIcon(item.name)"></span>
                            </template>
                            <span x-text="item.name" class="truncate"
                                  :class="item.path === activeFile?.path ? (theme === 'dark' ? 'text-ide-text' : 'text-light-text') : (theme === 'dark' ? 'text-ide-muted' : 'text-light-muted')"></span>
                        </div>
                        <div x-show="item.open" x-transition>
                            <template x-for="child in item.children" :key="child.path">
                                <div @click="openFile(child)" @contextmenu.prevent="openContext($event, child)"
                                     class="tree-item flex items-center gap-1.5 px-2 py-1 text-xs cursor-pointer"
                                     :class="child.path === activeFile?.path ? 'active' : ''"
                                     :style="{ paddingLeft: ((child.depth || item.depth + 1) * 12 + 8) + 'px' }">
                                    <span class="text-sky-400" x-html="fileIcon(child.name)"></span>
                                    <span x-text="child.name" class="truncate"
                                          :class="child.path === activeFile?.path ? (theme === 'dark' ? 'text-ide-text' : 'text-light-text') : (theme === 'dark' ? 'text-ide-muted' : 'text-light-muted')"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </aside>

        <div class="flex flex-1 overflow-hidden">
            <div class="flex flex-col flex-1 overflow-hidden">
                <div class="flex-1 overflow-hidden" id="monaco-container"></div>
                @include('console.terminal')
            </div>

            <aside x-show="showChat" x-cloak class="w-96 border-l flex flex-col shrink-0"
                   :class="theme === 'dark' ? 'bg-ide-sidebar border-ide-border' : 'bg-light-sidebar border-light-border'">
                @include('console.chat')
            </aside>
        </div>
    </div>

    @include('console.settings')
    @include('desktop.drag-drop')

    {{-- Context Menu --}}
    <div id="context-menu" class="hidden fixed z-50 w-48 rounded-lg border shadow-xl py-1 text-xs"
         :class="theme === 'dark' ? 'bg-ide-panel border-ide-border' : 'bg-white border-light-border'">
        <button @click="const t = document.getElementById('context-menu'); t.classList.add('hidden'); openFile(t._target)"
                class="w-full text-left px-3 py-1.5 transition-colors" :class="theme === 'dark' ? 'hover:bg-ide-hover' : 'hover:bg-light-hover'">
            Open
        </button>
        <button @click="const t = document.getElementById('context-menu'); t.classList.add('hidden'); renameItem(t._target)"
                class="w-full text-left px-3 py-1.5 transition-colors" :class="theme === 'dark' ? 'hover:bg-ide-hover' : 'hover:bg-light-hover'">
            Rename
        </button>
        <button @click="const t = document.getElementById('context-menu'); t.classList.add('hidden'); deleteItem(t._target)"
                class="w-full text-left px-3 py-1.5 transition-colors text-red-400" :class="theme === 'dark' ? 'hover:bg-ide-hover' : 'hover:bg-light-hover'">
            Delete
        </button>
        <div class="border-t my-1" :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'"></div>
        <button @click="const t = document.getElementById('context-menu'); t.classList.add('hidden'); copyPath(t._target)"
                class="w-full text-left px-3 py-1.5 transition-colors" :class="theme === 'dark' ? 'hover:bg-ide-hover' : 'hover:bg-light-hover'">
            Copy Path
        </button>
        <button @click="const t = document.getElementById('context-menu'); t.classList.add('hidden'); revealInExplorer(t._target)"
                class="w-full text-left px-3 py-1.5 transition-colors" :class="theme === 'dark' ? 'hover:bg-ide-hover' : 'hover:bg-light-hover'">
            Show in Explorer
        </button>
    </div>

    <script>
        function consoleState() {
            return {
                theme: localStorage.getItem('console-theme') || 'dark',
                fontSize: parseInt(localStorage.getItem('console-font-size') || '14'),
                fontFamily: localStorage.getItem('console-font-family') || "'JetBrains Mono', monospace",
                tabSize: parseInt(localStorage.getItem('console-tab-size') || '4'),
                wordWrap: localStorage.getItem('console-word-wrap') === 'true',
                autoSave: localStorage.getItem('console-auto-save') === 'true',
                lineNumbers: localStorage.getItem('console-line-numbers') || 'on',
                minimap: localStorage.getItem('console-minimap') !== 'false',
                keybindings: localStorage.getItem('console-keybindings') || 'default',
                showSidebar: true,
                showChat: false,
                showTerminal: true,
                showSettings: false,
                activeFile: null,
                openTabs: [],
                files: [],
                terminalHeight: 200,
                editor: null,

                init() {
                    this.loadFiles();
                    this.$watch('theme', val => {
                        localStorage.setItem('console-theme', val);
                        this.updateEditorTheme();
                    });
                    this.$watch('fontSize', val => {
                        localStorage.setItem('console-font-size', val);
                        this.updateEditorOptions();
                    });
                    this.$watch('showTerminal', () => setTimeout(() => this.editor?.layout(), 100));
                },

                loadFiles() {
                    this.files = [
                        { name: 'app', path: '/app', type: 'folder', open: true, depth: 0, children: [
                            { name: 'Http', path: '/app/Http', type: 'folder', open: true, depth: 1, children: [
                                { name: 'Controllers', path: '/app/Http/Controllers', type: 'folder', open: true, depth: 2, children: [
                                    { name: 'Controller.php', path: '/app/Http/Controllers/Controller.php', type: 'file', depth: 3, language: 'php', content: '<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n    //\n}' },
                                    { name: 'AuthController.php', path: '/app/Http/Controllers/AuthController.php', type: 'file', depth: 3, language: 'php', content: '<?php\n\nnamespace App\\Http\\Controllers;\n\nclass AuthController extends Controller\n{\n    public function index()\n    {\n        return response()->json([\'message\' => \'Hello\']);\n    }\n}' },
                                ]},
                                { name: 'Middleware', path: '/app/Http/Middleware', type: 'folder', open: false, depth: 2, children: [] },
                                { name: 'Requests', path: '/app/Http/Requests', type: 'folder', open: false, depth: 2, children: [] },
                            ]},
                            { name: 'Models', path: '/app/Models', type: 'folder', open: false, depth: 1, children: [
                                { name: 'User.php', path: '/app/Models/User.php', type: 'file', depth: 2, language: 'php', content: '<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass User extends Model\n{\n    protected $fillable = [\'name\', \'email\', \'password\'];\n}' },
                            ]},
                        ]},
                        { name: 'config', path: '/config', type: 'folder', open: false, depth: 0, children: [
                            { name: 'app.php', path: '/config/app.php', type: 'file', depth: 1, language: 'php', content: '<?php\n\nreturn [\n    \'name\' => env(\'APP_NAME\', \'Corex\'),\n    \'env\' => env(\'APP_ENV\', \'production\'),\n];' },
                            { name: 'database.php', path: '/config/database.php', type: 'file', depth: 1, language: 'php', content: '<?php\n\nreturn [\n    \'default\' => env(\'DB_CONNECTION\', \'pgsql\'),\n];' },
                        ]},
                        { name: 'routes', path: '/routes', type: 'folder', open: false, depth: 0, children: [
                            { name: 'web.php', path: '/routes/web.php', type: 'file', depth: 1, language: 'php', content: '<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get(\'/\', function () {\n    return view(\'landing.index\');\n});' },
                            { name: 'api.php', path: '/routes/api.php', type: 'file', depth: 1, language: 'php', content: '<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get(\'/health\', function () {\n    return response()->json([\'status\' => \'ok\']);\n});' },
                        ]},
                        { name: 'package.json', path: '/package.json', type: 'file', depth: 0, language: 'json', content: '{\n    "name": "corex",\n    "private": true,\n    "scripts": {\n        "dev": "vite",\n        "build": "vite build"\n    },\n    "dependencies": {\n        "laravel-vite-plugin": "^1.0"\n    }\n}' },
                        { name: '.env', path: '/.env', type: 'file', depth: 0, language: 'dotenv', content: 'APP_NAME=Corex\nAPP_ENV=local\nAPP_DEBUG=true\nAPP_URL=http://localhost:8000\n\nDB_CONNECTION=pgsql\nDB_HOST=127.0.0.1\nDB_PORT=5432\nDB_DATABASE=corex\nDB_USERNAME=postgres\nDB_PASSWORD=' },
                    ];
                },

                openFile(file) {
                    if (file.type !== 'file') return;
                    if (!this.openTabs.find(t => t.path === file.path)) {
                        this.openTabs.push(file);
                    }
                    this.activeFile = file;
                    this.$nextTick(() => this.loadEditor(file));
                },

                switchTab(tab) {
                    this.activeFile = tab;
                    this.$nextTick(() => this.loadEditor(tab));
                },

                closeTab(tab) {
                    this.openTabs = this.openTabs.filter(t => t.path !== tab.path);
                    if (this.activeFile?.path === tab.path) {
                        this.activeFile = this.openTabs.length ? this.openTabs[this.openTabs.length - 1] : null;
                        if (this.activeFile) this.$nextTick(() => this.loadEditor(this.activeFile));
                        else this.destroyEditor();
                    }
                },

                loadEditor(file) {
                    require(['vs/editor/editor.main'], () => {
                        if (this.editor) {
                            const model = this.editor.getModel();
                            if (model?.uri.path !== file.path) {
                                monaco.editor.getModels().forEach(m => m.dispose());
                                this.editor.dispose();
                                this.createEditorInstance(file);
                            }
                        } else {
                            this.createEditorInstance(file);
                        }
                    });
                },

                createEditorInstance(file) {
                    const el = document.getElementById('monaco-container');
                    if (!el) return;
                    el.innerHTML = '';
                    const uri = monaco.Uri.parse('file://' + file.path);
                    let model = monaco.editor.getModel(uri);
                    if (!model) model = monaco.editor.createModel(file.content || '', file.language, uri);

                    this.editor = monaco.editor.create(el, {
                        model,
                        theme: this.theme === 'dark' ? 'vs-dark' : 'vs',
                        fontSize: this.fontSize,
                        fontFamily: this.fontFamily,
                        tabSize: this.tabSize,
                        wordWrap: this.wordWrap ? 'on' : 'off',
                        lineNumbers: this.lineNumbers,
                        minimap: { enabled: this.minimap },
                        automaticLayout: true,
                        cursorBlinking: 'smooth',
                        cursorSmoothCaretAnimation: 'on',
                        smoothScrolling: true,
                        bracketPairColorization: { enabled: true },
                        padding: { top: 12 },
                        suggest: { showMethods: true, showFunctions: true, showConstructors: true, showFields: true, showVariables: true },
                        'semanticHighlighting.enabled': true,
                    });

                    this.editor.onDidChangeModelContent(() => {
                        if (this.autoSave) this.autoSaveFile(file);
                    });

                    this.editor.focus();
                    window.addEventListener('resize', () => this.editor?.layout());
                },

                destroyEditor() {
                    if (this.editor) { this.editor.dispose(); this.editor = null; }
                    const el = document.getElementById('monaco-container');
                    if (el) el.innerHTML = '<div class="flex items-center justify-center h-full" :class="theme === \'dark\' ? \'text-ide-muted\' : \'text-light-muted\'"><div class="text-center"><svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><p class="text-sm">Select a file to start editing</p></div></div>';
                },

                updateEditorTheme() {
                    if (this.editor) monaco.editor.setTheme(this.theme === 'dark' ? 'vs-dark' : 'vs');
                },

                updateEditorOptions() {
                    if (this.editor) {
                        this.editor.updateOptions({
                            fontSize: this.fontSize,
                            fontFamily: this.fontFamily,
                            tabSize: this.tabSize,
                            wordWrap: this.wordWrap ? 'on' : 'off',
                            lineNumbers: this.lineNumbers,
                            minimap: { enabled: this.minimap },
                        });
                    }
                },

                autoSaveFile(file) {
                    clearTimeout(this._saveTimer);
                    this._saveTimer = setTimeout(() => {
                        const content = this.editor?.getValue();
                        if (file) file.content = content;
                    }, 2000);
                },

                // ── Native Desktop Methods ─────────────────────────────────
                async loadFileFromPath(filePath) {
                    try {
                        const res = await fetch('/_native/files/read?path=' + encodeURIComponent(filePath));
                        const data = await res.json();
                        if (data.error) return;
                        this.openFile({
                            name: data.name,
                            path: data.path,
                            type: 'file',
                            language: data.language || 'plaintext',
                            content: data.content,
                        });
                    } catch (e) {
                        console.error('Failed to load file:', e);
                    }
                },

                async loadFolder(folderPath) {
                    try {
                        const res = await fetch('/_native/files/tree?path=' + encodeURIComponent(folderPath) + '&depth=5');
                        const data = await res.json();
                        if (data.children) {
                            this.files = data.children;
                        }
                    } catch (e) {
                        console.error('Failed to load folder:', e);
                    }
                },

                async saveCurrentFile() {
                    const file = this.activeFile;
                    if (!file || !this.editor) return;
                    file.content = this.editor.getValue();
                    try {
                        await fetch('/_native/files/write', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ path: file.path, content: file.content }),
                        });
                    } catch (e) {
                        console.error('Failed to save:', e);
                    }
                },

                async saveFileAs() {
                    if (!this.editor) return;
                    let targetPath = null;
                    if (window.native) {
                        const result = await window.native.dialog.saveFile({ title: 'Save As' });
                        if (!result.canceled) targetPath = result.filePath;
                    } else {
                        targetPath = prompt('Save as path:', this.activeFile?.path || '');
                    }
                    if (!targetPath) return;
                    const content = this.editor.getValue();
                    await fetch('/_native/files/write', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ path: targetPath, content }),
                    });
                    this.openFile({
                        name: targetPath.split('\\').pop().split('/').pop(),
                        path: targetPath,
                        type: 'file',
                        language: this.detectLang(targetPath),
                        content,
                    });
                },

                async newFile() {
                    const name = prompt('File name:');
                    if (!name) return;
                    const parent = this.currentFolder || '/';
                    try {
                        const res = await fetch('/_native/files/create', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ parent, name, type: 'file' }),
                        });
                        const data = await res.json();
                        this.loadFiles();
                        this.openFile(data);
                    } catch (e) {
                        console.error('Failed to create file:', e);
                    }
                },

                detectLang(path) {
                    const ext = path.split('.').pop()?.toLowerCase();
                    const map = { php: 'php', js: 'javascript', ts: 'typescript', vue: 'html', json: 'json', md: 'markdown', css: 'css', py: 'python', rs: 'rust', go: 'go', yaml: 'yaml', yml: 'yaml', xml: 'xml', sql: 'sql', sh: 'shell', bat: 'bat' };
                    return map[ext] || 'plaintext';
                },

                async openFileDialog() {
                    if (window.native) {
                        const result = await window.native.dialog.openFile({ multi: true });
                        if (!result.canceled && result.filePaths) {
                            result.filePaths.forEach(p => this.loadFileFromPath(p));
                        }
                    }
                },

                openContext(event, item) {
                    const menu = document.getElementById('context-menu');
                    if (!menu) return;
                    menu.style.left = event.clientX + 'px';
                    menu.style.top = event.clientY + 'px';
                    menu.classList.remove('hidden');
                    menu._target = item;
                    const close = () => { menu.classList.add('hidden'); document.removeEventListener('click', close); };
                    setTimeout(() => document.addEventListener('click', close), 0);
                },

                handleKeydown(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                        e.preventDefault();
                        const file = this.activeFile;
                        if (file && this.editor) {
                            file.content = this.editor.getValue();
                        }
                    }
                    if ((e.ctrlKey || e.metaKey) && e.key === '`') {
                        e.preventDefault();
                        this.showTerminal = !this.showTerminal;
                    }
                    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                        e.preventDefault();
                    }
                    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'p') {
                        e.preventDefault();
                        this.showSettings = !this.showSettings;
                    }
                },

                toggleFolder(item) {
                    if (item.type === 'folder') item.open = !item.open;
                }
            }
        }

        function fileExplorer() {
            return {}
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('fileExplorer', () => ({}));
        });

        document.addEventListener('DOMContentLoaded', () => {
            if (window.SupabaseRealtime && window.sbRealtimeConfig) {
                window.SupabaseRealtime.init(window.sbRealtimeConfig);
            }
        });
    </script>

    <script>
        window.sbRealtimeConfig = @json($realtimeConfig ?? null);
        window.USER_ID = '{{ auth()->id() ?? '' }}';
        window.SUPABASE_URL = '{{ config('supabase.url') }}';
        window.SUPABASE_ANON_KEY = '{{ config('supabase.key') }}';
    </script>
</body>
</html>
