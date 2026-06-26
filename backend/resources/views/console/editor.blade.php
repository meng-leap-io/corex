<div x-data="editorPanel()" x-init="initEditor">
    <div id="monaco-container" class="h-full w-full"></div>

    <div x-show="!activeFile" class="flex items-center justify-center h-full"
         :class="theme === 'dark' ? 'text-ide-muted' : 'text-light-muted'">
        <div class="text-center max-w-md">
            <svg class="w-24 h-24 mx-auto mb-6 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <h3 class="text-lg font-semibold mb-2" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Corex Editor</h3>
            <p class="text-sm mb-6">Select a file from the explorer to start editing, or use <kbd class="px-1.5 py-0.5 rounded text-xs font-mono" :class="theme === 'dark' ? 'bg-ide-panel text-ide-text' : 'bg-light-panel text-light-text'">Ctrl+O</kbd> to open a file.</p>
            <div class="grid grid-cols-2 gap-3 text-left text-xs max-w-xs mx-auto">
                <div class="p-3 rounded-lg" :class="theme === 'dark' ? 'bg-ide-panel' : 'bg-light-panel'">
                    <p class="font-medium mb-1" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Multi-cursor</p>
                    <p class="text-ide-muted">Alt+Click</p>
                </div>
                <div class="p-3 rounded-lg" :class="theme === 'dark' ? 'bg-ide-panel' : 'bg-light-panel'">
                    <p class="font-medium mb-1" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Command Palette</p>
                    <p class="text-ide-muted">Ctrl+Shift+P</p>
                </div>
                <div class="p-3 rounded-lg" :class="theme === 'dark' ? 'bg-ide-panel' : 'bg-light-panel'">
                    <p class="font-medium mb-1" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">Find & Replace</p>
                    <p class="text-ide-muted">Ctrl+H</p>
                </div>
                <div class="p-3 rounded-lg" :class="theme === 'dark' ? 'bg-ide-panel' : 'bg-light-panel'">
                    <p class="font-medium mb-1" :class="theme === 'dark' ? 'text-ide-text' : 'text-light-text'">AI Chat</p>
                    <p class="text-ide-muted">Ctrl+I</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editorPanel() {
            return {
                initEditor() {
                    if (!window.require) {
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js';
                        s.onload = () => this.setupMonaco();
                        document.head.appendChild(s);
                    } else if (!window.monaco) {
                        this.setupMonaco();
                    }
                },

                setupMonaco() {
                    require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' } });
                    require(['vs/editor/editor.main'], () => {
                        monaco.languages.registerCompletionItemProvider('php', {
                            provideCompletionItems: (model, position) => {
                                const word = model.getWordUntilPosition(position);
                                const range = { startLineNumber: position.lineNumber, endLineNumber: position.lineNumber, startColumn: word.startColumn, endColumn: word.endColumn };
                                return { suggestions: [
                                    { label: 'public function', kind: monaco.languages.CompletionItemKind.Snippet, insertText: 'public function ${1:name}(${2:params})\n{\n    ${3}\n}', insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet, range, documentation: 'Define a public method' },
                                    { label: 'private function', kind: monaco.languages.CompletionItemKind.Snippet, insertText: 'private function ${1:name}(${2:params})\n{\n    ${3}\n}', insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet, range },
                                    { label: 'protected function', kind: monaco.languages.CompletionItemKind.Snippet, insertText: 'protected function ${1:name}(${2:params})\n{\n    ${3}\n}', insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet, range },
                                    { label: '__construct', kind: monaco.languages.CompletionItemKind.Snippet, insertText: 'public function __construct(${1:params})\n{\n    $this->${2:prop} = ${3:$2};\n}', insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet, range },
                                    { label: 'foreach', kind: monaco.languages.CompletionItemKind.Snippet, insertText: 'foreach (${1:$array} as ${2:$key} => ${3:$value}) {\n    ${4}\n}', insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet, range },
                                    { label: 'if', kind: monaco.languages.CompletionItemKind.Snippet, insertText: 'if (${1:condition}) {\n    ${2}\n}', insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet, range },
                                ]};
                            }
                        });

                        monaco.languages.registerSignatureHelpProvider('php', {
                            signatureHelpTriggerCharacters: ['(', ','],
                            provideSignatureHelp: (model, position) => ({ signatures: [], activeSignature: 0, activeParameter: 0 })
                        });

                        monaco.languages.registerDocumentFormattingEditProvider('php', {
                            provideDocumentFormattingEdits: (model) => {
                                const text = model.getValue();
                                return [{ range: model.getFullModelRange(), text }];
                            }
                        });
                    });
                }
            }
        }

        function fileIcon(name) {
            const ext = name.split('.').pop().toLowerCase();
            const icons = {
                php: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="#777bb3"><path d="M7.01 10.2h1.65l-.52 2.56H6.47l-.8 3.72H3.87l.8-3.72H2.44l.8-3.72h3.09l-.32 1.16zm.76-1.16h1.65l-.52 2.56h1.65l.52-2.56h1.65l-.8 3.72h-1.65l-.44 2.1H8.17l.44-2.1H6.96l-.8 3.72H4.51l.8-3.72H4.35l.44-2.1h.96l.52-2.56zm7.55 0h1.65l-.52 2.56h1.65l.52-2.56h1.65l-.8 3.72h-1.65l-.44 2.1h-1.65l.44-2.1h-1.65l-.8 3.72h-1.65l.8-3.72h-.96l.44-2.1h.96l.52-2.56z"/></svg>',
                js: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="#f7df1e"><path d="M0 0h24v24H0V0zm22.034 18.276c-.175-1.095-.888-2.015-3.003-2.873-.736-.345-1.554-.585-1.797-1.14-.091-.33-.105-.51-.046-.705.15-.646.915-.84 1.515-.66.39.12.75.42.976.9 1.034-.676 1.034-.676 1.755-1.125-.27-.42-.404-.601-.586-.78-.63-.705-1.469-1.065-2.834-1.034l-.705.089c-.676.165-1.32.525-1.71 1.005-1.14 1.291-.811 3.541.569 4.471 1.365 1.02 3.361 1.244 3.616 2.205.24 1.17-.87 1.545-1.966 1.41-.811-.18-1.26-.586-1.755-1.336l-1.83 1.051c.21.48.45.689.81 1.109 1.74 1.756 6.09 1.666 6.871-1.004.029-.09.24-.705.074-1.65l.046.067zm-8.983-7.245h-2.248c0 1.938-.009 3.864-.009 5.805 0 1.232.063 2.363-.138 2.711-.33.689-1.18.601-1.566.48-.396-.196-.597-.466-.83-.855-.063-.105-.11-.196-.127-.196l-1.825 1.125c.305.63.75 1.172 1.324 1.517.855.51 2.004.675 3.207.405.783-.226 1.458-.691 1.811-1.411.51-.93.402-2.07.397-3.346.012-2.054 0-4.109 0-6.179l.004-.056z"/></svg>',
                json: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="#f5a623"><path d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2zm3.5 12.5a.5.5 0 00.5-.5v-2.5a.5.5 0 01.5-.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1a.5.5 0 01-.5-.5V7a.5.5 0 00-.5-.5h-1a.5.5 0 00-.5.5v2.5a1.5 1.5 0 001.5 1.5H8A1.5 1.5 0 019.5 12.5V15a.5.5 0 00.5.5h1a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5h-1a.5.5 0 01-.5-.5V12a.5.5 0 00-.5-.5h-1a.5.5 0 00-.5.5v1a.5.5 0 00.5.5h1a.5.5 0 01.5.5V15a.5.5 0 00.5.5h1zm8.5-1a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-2.5a.5.5 0 00-.5-.5h-1a.5.5 0 01-.5-.5v-1a.5.5 0 01.5-.5h1a.5.5 0 00.5-.5V7a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v2.5a1.5 1.5 0 01-1.5 1.5H15a1.5 1.5 0 00-1.5 1.5V15a.5.5 0 01-.5.5h-1a.5.5 0 01-.5-.5v-1a.5.5 0 01.5-.5h1a.5.5 0 00.5-.5v-1a.5.5 0 01.5-.5h1a.5.5 0 01.5.5v1a.5.5 0 00.5.5h1a.5.5 0 01.5.5v1z"/></svg>',
                env: '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="#f5a623"><path d="M4 5v14h16V5H4zm2 2h12v10H6V7zm2 2v6h2V9H8zm4 0v6h2V9h-2zm4 0v6h2V9h-2z"/></svg>',
            };
            return icons[ext] || '<svg class="w-4 h-4 text-ide-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>';
        }
    </script>
</div>
