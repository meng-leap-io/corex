<div x-data="dragDropHandler()"
     @dragover.prevent="dragOver = true"
     @dragleave.prevent="dragOver = false"
     @drop.prevent="handleDrop($event)"
     class="relative"
     :class="{ 'ring-2 ring-primary-500 ring-offset-2 ring-offset-ide-bg rounded-lg': dragOver }">

    <div x-show="dragOver"
         class="absolute inset-0 z-40 flex items-center justify-center bg-primary-500/10 backdrop-blur-sm rounded-lg pointer-events-none">
        <div class="text-center">
            <svg class="w-12 h-12 mx-auto text-primary-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <p class="text-primary-300 text-sm font-medium">Drop files to open</p>
        </div>
    </div>

    <div x-show="uploadProgress !== null" class="absolute inset-0 z-40 flex items-center justify-center bg-black/40 rounded-lg">
        <div class="bg-ide-panel rounded-xl p-6 shadow-2xl w-80">
            <p class="text-sm font-medium mb-3 text-center" x-text="uploadStatus"></p>
            <div class="h-2 bg-ide-border rounded-full overflow-hidden">
                <div class="h-full bg-primary-500 rounded-full transition-all duration-300"
                     :style="'width: ' + uploadProgress + '%'"></div>
            </div>
            <p class="text-xs text-ide-muted mt-2 text-center" x-text="uploadProgress + '%'"></p>
        </div>
    </div>
</div>

<script>
    function dragDropHandler() {
        return {
            dragOver: false,
            uploadProgress: null,
            uploadStatus: '',

            async handleDrop(event) {
                this.dragOver = false;
                const files = event.dataTransfer.files;
                if (!files.length) return;

                for (const file of files) {
                    const path = file.path || file.fullPath || file.name;
                    await this.openDroppedFile(path, file);
                }
            },

            async openDroppedFile(fullPath, fileObj) {
                if (fileObj.size > 10 * 1024 * 1024) {
                    if (window.$native) {
                        window.$native.notify('File too large', `${fileObj.name} exceeds 10MB limit`, { silent: true });
                    }
                    return;
                }

                const ext = fileObj.name.split('.').pop()?.toLowerCase();
                const textExtensions = ['php', 'js', 'ts', 'vue', 'json', 'md', 'css', 'scss', 'html', 'xml', 'yaml', 'yml', 'py', 'go', 'rs', 'sh', 'bat', 'env', 'sql', 'toml', 'txt', 'blade.php'];

                if (textExtensions.includes(ext)) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const content = e.target.result;
                        const fileEntry = {
                            name: fileObj.name,
                            path: fullPath,
                            type: 'file',
                            language: this.detectLanguage(ext),
                            content: content,
                        };

                        if (window.consoleState) {
                            window.consoleState.openFile(fileEntry);
                        }
                    };
                    reader.readAsText(fileObj);
                }
            },

            detectLanguage(ext) {
                const map = {
                    php: 'php', js: 'javascript', ts: 'typescript', vue: 'html',
                    json: 'json', md: 'markdown', css: 'css', scss: 'scss',
                    html: 'html', xml: 'xml', yaml: 'yaml', py: 'python',
                    go: 'go', rs: 'rust', sh: 'shell', bat: 'bat',
                    sql: 'sql', toml: 'toml', env: 'dotenv', txt: 'plaintext',
                };
                return map[ext] || 'plaintext';
            },

            async uploadToServer(file) {
                const formData = new FormData();
                formData.append('file', file);

                this.uploadStatus = `Uploading ${file.name}...`;
                this.uploadProgress = 0;

                try {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    };

                    await new Promise((resolve, reject) => {
                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) resolve();
                            else reject(new Error(xhr.statusText));
                        };
                        xhr.onerror = () => reject(new Error('Network error'));
                        xhr.open('POST', '/_native/files/upload?path=' + encodeURIComponent(file.path || '/'));
                        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content);
                        xhr.send(formData);
                    });

                    this.uploadProgress = 100;
                    this.uploadStatus = 'Upload complete';
                    setTimeout(() => { this.uploadProgress = null; }, 1500);
                } catch (e) {
                    this.uploadStatus = `Upload failed: ${e.message}`;
                    setTimeout(() => { this.uploadProgress = null; }, 3000);
                }
            },
        };
    }
</script>
