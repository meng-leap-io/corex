@if(config('supabase.realtime.livewire_chat', false))
    @livewire('chat.real-time-chat')
@else
<div class="flex flex-col h-full" x-data="chatPanel()" x-init="init()"
    <div class="flex items-center justify-between px-4 py-2 border-b shrink-0"
         :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'">
        <span class="text-xs font-semibold uppercase tracking-wider"
              :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">
            <span class="flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                AI Assistant
            </span>
        </span>
        <div class="flex items-center gap-2">
            <select x-model="selectedModel" @change="switchModel"
                    class="text-xs rounded px-2 py-1 border bg-transparent outline-none"
                    :class="theme === 'dark' ? 'border-ide-border text-ide-text' : 'border-light-border text-light-text'">
                <option value="gpt-4o">GPT-4o</option>
                <option value="gpt-4o-mini">GPT-4o Mini</option>
                <option value="claude-3-opus">Claude 3 Opus</option>
                <option value="claude-3-sonnet">Claude 3 Sonnet</option>
                <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
            </select>
            <button @click="clearChat" class="p-1 rounded hover:bg-ide-hover transition-colors"
                    :class="theme === 'dark' ? 'text-ide-muted hover:text-ide-text' : 'text-light-muted hover:text-light-text'"
                    title="Clear chat">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-3 space-y-4" x-ref="messagesContainer">
        <template x-for="(msg, i) in messages" :key="i">
            <div class="chat-enter" x-data="{ showActions: false }" @mouseenter="showActions = true" @mouseleave="showActions = false">
                <div class="flex items-start gap-2.5">
                    <div class="w-6 h-6 rounded-md shrink-0 flex items-center justify-center text-[10px] font-bold mt-0.5"
                         :class="msg.role === 'user'
                            ? 'bg-primary-500/20 text-primary-400'
                            : 'bg-gradient-to-br from-primary-500 to-purple-600 text-white'">
                        <span x-text="msg.role === 'user' ? 'U' : 'AI'"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium mb-1"
                             :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'"
                             x-text="msg.role === 'user' ? 'You' : 'Corex AI'"></div>
                        <div class="text-xs leading-relaxed prose prose-sm max-w-none"
                             :class="theme === 'dark' ? 'text-ide-muted prose-invert' : 'text-light-muted'"
                             x-html="renderMarkdown(msg.content)"></div>
                        <template x-if="msg.codeBlocks">
                            <template x-for="(block, bi) in msg.codeBlocks" :key="bi">
                                <div class="mt-2 rounded-lg overflow-hidden border"
                                     :class="theme === 'dark' ? 'border-ide-border bg-ide-panel' : 'border-light-border bg-light-panel'">
                                    <div class="flex items-center justify-between px-3 py-1.5 text-[10px]"
                                         :class="theme === 'dark' ? 'bg-ide-sidebar text-ide-muted' : 'bg-light-sidebar text-light-muted'">
                                        <span x-text="block.language || 'code'"></span>
                                        <div class="flex items-center gap-1">
                                            <button @click="copyCode(block.code)" class="p-1 rounded hover:bg-ide-hover transition-colors">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                            <button @click="insertCode(block.code)" class="p-1 rounded hover:bg-ide-hover transition-colors">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <pre class="p-3 text-xs font-mono overflow-x-auto" x-text="block.code"
                                         :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'"></pre>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="loading" class="flex items-start gap-2.5 chat-enter">
            <div class="w-6 h-6 rounded-md shrink-0 bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center text-[10px] font-bold text-white">AI</div>
            <div class="flex items-center gap-1.5 py-2">
                <span class="w-1.5 h-1.5 rounded-full bg-primary-400 pulse-dot"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-primary-400 pulse-dot" style="animation-delay: .3s"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-primary-400 pulse-dot" style="animation-delay: .6s"></span>
            </div>
        </div>

        <div x-show="!loading && messages.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
            <svg class="w-12 h-12 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <p class="text-sm font-medium mb-1" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">AI Assistant</p>
            <p class="text-xs max-w-xs" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">
                Ask me anything about your code. Try<br>
                <span class="text-primary-400 font-mono">/explain</span> — explain selected code<br>
                <span class="text-primary-400 font-mono">/refactor</span> — refactor code<br>
                <span class="text-primary-400 font-mono">/test</span> — generate tests<br>
                <span class="text-primary-400 font-mono">/docs</span> — add documentation
            </p>
        </div>
    </div>

    <div class="p-3 border-t shrink-0"
         :class="theme === 'dark' ? 'border-ide-border' : 'border-light-border'">
        <div class="flex items-end gap-2">
            <div class="flex-1 relative">
                <textarea x-model="input" @keydown="handleKeydown"
                          placeholder="Ask AI or type / for commands..."
                          rows="2"
                          class="w-full resize-none rounded-lg px-3 py-2 text-xs outline-none border transition-colors"
                          :class="theme === 'dark'
                              ? 'bg-ide-panel border-ide-border text-ide-text placeholder-ide-muted focus:border-primary-500'
                              : 'bg-white border-light-border text-light-text placeholder-light-muted focus:border-primary-500'"></textarea>
                <div x-show="showCommands" x-cloak x-transition
                     class="absolute bottom-full left-0 right-0 mb-1 rounded-lg border overflow-hidden shadow-xl"
                     :class="theme === 'dark' ? 'bg-ide-panel border-ide-border' : 'bg-white border-light-border'">
                    <template x-for="(cmd, i) in filteredCommands" :key="cmd.id">
                        <button @click="selectCommand(cmd)"
                                class="w-full flex items-center gap-2 px-3 py-2 text-xs text-left transition-colors"
                                :class="i === selectedCommandIndex
                                    ? (theme === 'dark' ? 'bg-ide-active text-ide-text' : 'bg-light-hover text-light-text')
                                    : (theme === 'dark' ? 'text-ide-muted hover:bg-ide-hover' : 'text-light-muted hover:bg-light-hover')">
                            <span class="text-primary-400 font-mono text-[10px]" x-text="cmd.id"></span>
                            <span x-text="cmd.label"></span>
                        </button>
                    </template>
                </div>
            </div>
            <button @click="sendMessage" :disabled="loading || !input.trim()"
                    class="px-3 py-2 rounded-lg text-xs font-medium transition-all disabled:opacity-40"
                    :class="loading
                        ? 'bg-primary-500/50 text-white cursor-not-allowed'
                        : 'bg-primary-500 text-white hover:bg-primary-600 shadow-sm'">
                <svg x-show="!loading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </button>
        </div>
        <div class="flex items-center gap-3 mt-1.5 px-0.5">
            <span class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'"
                  x-text="'Token count: ~' + (input.split(/\\s+/).filter(Boolean).length * 1.3).toFixed(0)"></span>
            <span class="text-[10px]" :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'"
                  x-text="selectedModel"></span>
        </div>
    </div>

    <script>
        function chatPanel() {
            return {
                input: '',
                messages: [],
                loading: false,
                selectedModel: 'gpt-4o-mini',
                showCommands: false,
                selectedCommandIndex: 0,
                commands: [
                    { id: '/explain', label: 'Explain the selected code', prompt: 'Explain this code in detail:\n\n' },
                    { id: '/refactor', label: 'Refactor the selected code', prompt: 'Refactor this code to be more efficient and maintainable:\n\n' },
                    { id: '/test', label: 'Generate unit tests', prompt: 'Write comprehensive unit tests for this code:\n\n' },
                    { id: '/docs', label: 'Add documentation', prompt: 'Add comprehensive PHPDoc/docstring documentation to this code:\n\n' },
                    { id: '/fix', label: 'Fix issues in the code', prompt: 'Find and fix any bugs or issues in this code:\n\n' },
                    { id: '/optimize', label: 'Optimize performance', prompt: 'Optimize this code for better performance:\n\n' },
                    { id: '/review', label: 'Review code', prompt: 'Review this code for best practices, security issues, and improvements:\n\n' },
                ],

                get filteredCommands() {
                    const q = this.input.toLowerCase();
                    if (!q.startsWith('/')) return [];
                    return this.commands.filter(c => c.id.includes(q));
                },

                init() {
                    this.$watch('showCommands', v => { if (v) this.selectedCommandIndex = 0; });
                    document.addEventListener('click', (e) => {
                        if (!e.target.closest('[x-data="chatPanel()"]')) this.showCommands = false;
                    });
                },

                handleKeydown(e) {
                    if (this.showCommands && this.filteredCommands.length) {
                        if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedCommandIndex = Math.min(this.selectedCommandIndex + 1, this.filteredCommands.length - 1); return; }
                        if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedCommandIndex = Math.max(this.selectedCommandIndex - 1, 0); return; }
                        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.selectCommand(this.filteredCommands[this.selectedCommandIndex]); return; }
                        if (e.key === 'Escape') { this.showCommands = false; return; }
                    }
                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }
                    this.showCommands = this.input.startsWith('/');
                },

                selectCommand(cmd) {
                    const sel = window.getSelection()?.toString();
                    this.input = cmd.prompt + (sel || '');
                    this.showCommands = false;
                    this.$nextTick(() => this.sendMessage());
                },

                async sendMessage() {
                    const text = this.input.trim();
                    if (!text || this.loading) return;
                    this.input = '';
                    this.showCommands = false;
                    this.messages.push({ role: 'user', content: text });
                    this.loading = true;
                    this.$nextTick(() => this.scrollBottom());

                    try {
                        const response = await fetch('/api/ai/chat', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                            body: JSON.stringify({ message: text, model: this.selectedModel, context: this.getEditorContext() })
                        });
                        if (!response.ok) throw new Error('Request failed');
                        const data = await response.json();
                        this.addAssistantMessage(data.content || data.message);
                    } catch (e) {
                        this.addAssistantMessage('I encountered an error processing your request. Please try again.\n\n> ' + e.message);
                    }
                    this.loading = false;
                    this.$nextTick(() => this.scrollBottom());
                },

                addAssistantMessage(content) {
                    const blocks = this.extractCodeBlocks(content);
                    this.messages.push({ role: 'assistant', content, codeBlocks: blocks });
                },

                extractCodeBlocks(text) {
                    const blocks = [];
                    const regex = /```(\w+)?\n([\s\S]*?)```/g;
                    let match;
                    while ((match = regex.exec(text)) !== null) {
                        blocks.push({ language: match[1] || 'text', code: match[2].trim() });
                    }
                    return blocks.length ? blocks : null;
                },

                renderMarkdown(text) {
                    if (typeof marked !== 'undefined') {
                        return marked.parse(text, { breaks: true, gfm: true });
                    }
                    return text.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre class="p-2 rounded bg-ide-panel text-xs overflow-x-auto">$2</pre>')
                               .replace(/`([^`]+)`/g, '<code class="px-1 py-0.5 rounded text-xs bg-ide-panel text-primary-300">$1</code>')
                               .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                               .replace(/\n/g, '<br>');
                },

                copyCode(code) {
                    navigator.clipboard.writeText(code).catch(() => {});
                },

                insertCode(code) {
                    if (window.editor) {
                        const sel = window.editor.getSelection();
                        window.editor.executeEdits('chat-insert', [{ range: sel, text: code }]);
                        window.editor.focus();
                    }
                },

                getEditorContext() {
                    if (!window.editor) return null;
                    const sel = window.editor.getSelection();
                    const model = window.editor.getModel();
                    const selectedText = window.editor.getModel()?.getValueInRange(sel);
                    return {
                        language: model?.getLanguageId(),
                        selection: selectedText,
                        filePath: model?.uri.path,
                    };
                },

                clearChat() {
                    this.messages = [];
                },

                switchModel(e) {
                    localStorage.setItem('chat-model', this.selectedModel);
                },

                scrollBottom() {
                    const el = this.$refs.messagesContainer;
                    if (el) el.scrollTop = el.scrollHeight;
                }
            }
        }
    </script>
</div>
@endif
