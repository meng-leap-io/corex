<div x-data="terminalPanel()" x-init="initTerminal"
     x-show="showTerminal" x-cloak
     class="border-t shrink-0 resize-handle resize-handle-y overflow-hidden flex flex-col"
     :class="theme === 'dark' ? 'border-ide-border bg-ide-panel' : 'border-light-border bg-light-panel'"
     :style="{ height: terminalHeight + 'px' }">

    <div class="flex items-center justify-between px-3 py-1 border-b shrink-0"
         :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'">
        <div class="flex items-center gap-1">
            <template x-for="(tab, i) in tabs" :key="i">
                <button @click="switchTab(i)"
                        class="flex items-center gap-1 px-2 py-0.5 text-xs rounded-t transition-colors"
                        :class="activeTab === i
                            ? (theme === 'dark' ? 'bg-ide-bg text-ide-text' : 'bg-white text-light-text')
                            : (theme === 'dark' ? 'text-ide-muted hover:text-ide-text' : 'text-light-muted hover:text-light-text')">
                    <span x-text="tab.name"></span>
                    <button @click.stop="closeTab(i)" x-show="tabs.length > 1"
                            class="hover:bg-ide-active rounded p-0.5 opacity-60 hover:opacity-100">
                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </button>
            </template>
            <button @click="newTab()" class="p-1 rounded transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
        </div>
        <div class="flex items-center gap-1">
            <button @click="clearTerminal" class="p-1 rounded transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
            <div class="w-px h-4 mx-0.5" :class="theme === 'dark' ? 'bg-ide-border' : 'bg-light-border'"></div>
            <button @mousedown="startResize" class="p-1 rounded transition-colors cursor-row-resize"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 12h8M8 17h8"/></svg>
            </button>
            <button @click="showTerminal = false" class="p-1 rounded transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text hover:bg-ide-hover' : 'text-light-muted hover:text-light-text hover:bg-light-hover'">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-hidden p-0" x-ref="terminalContainer"></div>

    <script>
        function terminalPanel() {
            return {
                term: null,
                fitAddon: null,
                activeTab: 0,
                tabs: [{ name: 'bash', history: [], historyIndex: -1 }],
                resizing: false,

                initTerminal() {
                    this.$nextTick(() => {
                        if (!window.Terminal) {
                            const s = document.createElement('script');
                            s.src = 'https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js';
                            document.head.appendChild(s);
                            const s2 = document.createElement('script');
                            s2.src = 'https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js';
                            document.head.appendChild(s2);
                            s.onload = () => this.createTerminal();
                        } else {
                            this.createTerminal();
                        }
                    });

                    window.addEventListener('mouseup', () => { this.resizing = false; });
                    window.addEventListener('mousemove', (e) => {
                        if (this.resizing) {
                            const rect = document.body.getBoundingClientRect();
                            this.terminalHeight = Math.max(100, Math.min(600, rect.bottom - e.clientY));
                        }
                    });
                },

                createTerminal() {
                    if (this.term) { this.term.dispose(); }
                    const el = this.$refs.terminalContainer;
                    if (!el) return;

                    this.fitAddon = new FitAddon.FitAddon();
                    this.term = new Terminal({
                        cursorBlink: true,
                        cursorStyle: 'block',
                        fontSize: 13,
                        fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
                        theme: this.theme === 'dark'
                            ? { background: '#1e1e2e', foreground: '#cdd6f4', cursor: '#f5e0dc', selectionBackground: '#585b70', black: '#45475a', red: '#f38ba8', green: '#a6e3a1', yellow: '#f9e2af', blue: '#89b4fa', magenta: '#f5c2e7', cyan: '#94e2d5', white: '#bac2de' }
                            : { background: '#ffffff', foreground: '#0f172a', cursor: '#6366f1', selectionBackground: '#c7d2fe' },
                        allowTransparency: false,
                        allowProposedApi: true,
                    });

                    this.term.loadAddon(this.fitAddon);
                    this.term.open(el);
                    this.fitAddon.fit();

                    this.writeWelcome();

                    this.term.onKey(e => {
                        const ev = e.domEvent;
                        if (ev.ctrlKey && ev.key === 'l') { this.term.clear(); ev.preventDefault(); return; }
                        if (ev.key === 'Enter') {
                            const line = this.term.buffer.active.getLine(this.term.buffer.active.cursorY)?.translateToString() || '';
                            const cmd = line.replace(/^[\$\#]\s*/, '').trim();
                            if (cmd) {
                                this.tabs[this.activeTab].history.push(cmd);
                                this.tabs[this.activeTab].historyIndex = -1;
                                this.executeCommand(cmd);
                            } else {
                                this.term.write('\r\n$ ');
                            }
                            return;
                        }
                        if (ev.key === 'ArrowUp') {
                            ev.preventDefault();
                            const h = this.tabs[this.activeTab].history;
                            if (h.length) {
                                const idx = this.tabs[this.activeTab].historyIndex === -1 ? h.length - 1 : Math.max(0, this.tabs[this.activeTab].historyIndex - 1);
                                this.tabs[this.activeTab].historyIndex = idx;
                                this.term.write('\r\x1b[K$ ' + h[idx]);
                            }
                            return;
                        }
                        if (ev.key === 'ArrowDown') {
                            ev.preventDefault();
                            const h = this.tabs[this.activeTab].history;
                            const idx = this.tabs[this.activeTab].historyIndex + 1;
                            if (idx >= h.length) {
                                this.tabs[this.activeTab].historyIndex = -1;
                                this.term.write('\r\x1b[K$ ');
                            } else {
                                this.tabs[this.activeTab].historyIndex = idx;
                                this.term.write('\r\x1b[K$ ' + h[idx]);
                            }
                            return;
                        }
                        if (ev.key === 'Tab') { ev.preventDefault(); return; }
                        if (ev.key === 'Backspace') {
                            const line = this.term.buffer.active.getLine(this.term.buffer.active.cursorY)?.translateToString() || '';
                            if (line.replace(/^[\$\#]\s*/, '').length > 0) { this.term.write('\b \b'); }
                            return;
                        }
                        if (e.key.length === 1) { this.term.write(e.key); }
                    });

                    window.addEventListener('resize', () => { if (this.fitAddon) this.fitAddon.fit(); });
                },

                writeWelcome() {
                    const term = this.term;
                    term.write('\r\n\x1b[1;36m  ⚡ Corex Terminal v1.0\x1b[0m');
                    term.write('\r\n\x1b[2;37m  Type commands or use Ctrl+` to toggle\x1b[0m');
                    term.write('\r\n\r\n$ ');
                },

                executeCommand(cmd) {
                    const term = this.term;
                    const parts = cmd.split(/\s+/);
                    const command = parts[0].toLowerCase();

                    const commands = {
                        help: () => {
                            term.write('\r\n');
                            const list = ['help', 'clear', 'echo', 'date', 'whoami', 'pwd', 'ls', 'php', 'artisan', 'composer', 'npm', 'node', 'git'];
                            list.forEach(c => term.write(`\r\n  \x1b[1;32m${c}\x1b[0m`));
                            term.write('\r\n');
                        },
                        clear: () => term.clear(),
                        echo: () => term.write('\r\n' + parts.slice(1).join(' ')),
                        date: () => term.write('\r\n' + new Date().toString()),
                        whoami: () => term.write('\r\n\x1b[1;32mcorex\x1b[0m'),
                        pwd: () => term.write('\r\n\x1b[1;34m/home/corex/project\x1b[0m'),
                        ls: () => term.write('\r\n\x1b[1;34mapp\x1b[0m  \x1b[1;34mconfig\x1b[0m  \x1b[1;34mdatabase\x1b[0m  \x1b[1;34mpublic\x1b[0m  \x1b[1;34mresources\x1b[0m  \x1b[1;34mroutes\x1b[0m  package.json  \x1b[1;34mvendor\x1b[0m'),
                        php: () => {
                            if (parts[1] === 'artisan') {
                                const sub = parts.slice(2).join(' ');
                                if (sub.includes('make:model') || sub.includes('make:controller') || sub.includes('make:migration')) {
                                    term.write(`\r\n\x1b[1;33m  ✓\x1b[0m Created: app/${sub.includes('controller') ? 'Http/Controllers' : sub.includes('migration') ? 'database/migrations' : 'Models'}/${sub.split(' ').pop()}.php`);
                                } else if (sub === 'route:list') {
                                    term.write('\r\n  GET|HEAD  / ................................................. landing.index');
                                    term.write('\r\n  GET|HEAD  /api/health ..................................... closure');
                                    term.write('\r\n  POST      /api/auth/login ................................ AuthController@login');
                                    term.write('\r\n  POST      /api/auth/register ............................. AuthController@register');
                                    term.write('\r\n  GET|HEAD  /api/user ..................................... closure');
                                } else if (sub === 'tinker') {
                                    term.write('\r\n\x1b[1;33m  Psy Shell v0.12.0 (PHP 8.3)\x1b[0m');
                                    term.write('\r\n  > ');
                                } else {
                                    term.write(`\r\n  \x1b[1;32m✓\x1b[0m Done. ${sub || 'cache cleared'}`);
                                }
                            } else {
                                term.write(`\r\nPHP 8.3.x (cli) (built: ...)`);
                            }
                        },
                        node: () => term.write(`\r\n\x1b[1;32mv20.11.0\x1b[0m`),
                        npm: () => term.write(`\r\n\x1b[1;32m✓\x1b[0m npm ${parts.includes('install') ? 'dependencies installed' : 'command executed'}`),
                        composer: () => term.write(`\r\n\x1b[1;32m✓\x1b[0m Composer ${parts.includes('install') ? 'dependencies installed' : parts.includes('require') ? `Package "${parts.slice(2).join(' ')}" added` : 'command executed'}`),
                        git: () => {
                            if (parts[1] === 'status') term.write('\r\nOn branch \x1b[1;32mmain\x1b[0m\nnothing to commit, working tree clean');
                            else if (parts[1] === 'log') term.write('\r\n\x1b[1;33mcommit\x1b[0m a1b2c3d4...\n\x1b[1;34mAuthor:\x1b[0m Corex Dev <dev@corex.dev>\n\n    Initial commit');
                            else if (parts[1] === 'branch') term.write('\r\n* \x1b[1;32mmain\x1b[0m\n  develop\n  feature/new-ui');
                            else term.write(`\r\n\x1b[1;32m✓\x1b[0m git ${parts.slice(1).join(' ')}`);
                        },
                    };

                    if (commands[command]) {
                        commands[command]();
                        term.write('\r\n$ ');
                    } else if (cmd.trim()) {
                        term.write(`\r\n\x1b[1;31mcommand not found:\x1b[0m ${command}`);
                        term.write('\r\n$ ');
                    } else {
                        term.write('\r\n$ ');
                    }
                },

                switchTab(i) { this.activeTab = i; this.$nextTick(() => this.reconnectTerminal()); },
                newTab() { this.tabs.push({ name: 'bash ' + (this.tabs.length + 1), history: [], historyIndex: -1 }); this.activeTab = this.tabs.length - 1; this.$nextTick(() => this.reconnectTerminal()); },
                closeTab(i) { if (this.tabs.length > 1) { this.tabs.splice(i, 1); this.activeTab = Math.min(this.activeTab, this.tabs.length - 1); this.$nextTick(() => this.reconnectTerminal()); }},
                clearTerminal() { this.term?.clear(); this.writeWelcome(); },
                reconnectTerminal() { this.createTerminal(); },
                startResize(e) { this.resizing = true; e.preventDefault(); },
            }
        }
    </script>
</div>
