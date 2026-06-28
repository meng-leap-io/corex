<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Key
    |--------------------------------------------------------------------------
    |
    | Your NativePHP license key. You can purchase a license at
    | https://nativephp.com/pricing. A valid license is required for
    | production usage.
    |
    */
    'license' => env('NATIVEPHP_LICENSE'),

    /*
    |--------------------------------------------------------------------------
    | Application Details
    |--------------------------------------------------------------------------
    |
    | These values are used by NativePHP to set the application name,
    | version, and description in the operating system.
    |
    */
    'name' => env('APP_NAME', 'Corex'),
    'version' => env('APP_VERSION', '1.0.0'),
    'description' => 'AI-Powered Development Platform',

    /*
    |--------------------------------------------------------------------------
    | Author / Publisher
    |--------------------------------------------------------------------------
    |
    | Used for Windows Authenticode signing and macOS notarization.
    |
    */
    'author' => env('NATIVEPHP_AUTHOR', 'Corex.dev'),
    'company' => env('NATIVEPHP_COMPANY', 'Corex.dev'),
    'copyright' => 'Copyright '.date('Y').' Corex.dev. All rights reserved.',

    /*
    |--------------------------------------------------------------------------
    | Window Configuration
    |--------------------------------------------------------------------------
    |
    | Default window size, position, and behavior.
    |
    */
    'window' => [
        'title' => env('APP_NAME', 'Corex'),
        'width' => 1400,
        'height' => 900,
        'min_width' => 900,
        'min_height' => 600,
        'max_width' => 0,      // 0 = no limit
        'max_height' => 0,     // 0 = no limit
        'x' => null,           // null = center on screen
        'y' => null,
        'center' => true,
        'resizable' => true,
        'maximizable' => true,
        'minimizable' => true,
        'closable' => true,
        'always_on_top' => false,
        'fullscreen' => false,
        'kiosk' => false,
        'frame' => true,                        // false = frameless window
        'transparent' => false,
        'opacity' => 1.0,
        'background_color' => '#0f172a',         // matches surface-dark
        'show_in_taskbar' => true,
        'skip_taskbar' => false,
        'has_shadow' => true,
        'web_preferences' => [
            'node_integration' => false,
            'context_isolation' => true,
            'sandbox' => true,
            'enable_remote_module' => false,
            'allow_running_insecure_content' => false,
            'background_throttling' => true,
            'spellcheck' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Bar
    |--------------------------------------------------------------------------
    |
    | Define the application menu. macOS menus are in the top menu bar;
    | Windows/Linux menus are in the window.
    |
    */
    'menu' => [
        'app_menu' => [
            'label' => env('APP_NAME', 'Corex'),
            'submenu' => [
                ['label' => 'About Corex', 'role' => 'about'],
                ['type' => 'separator'],
                ['label' => 'Preferences...', 'accelerator' => 'CmdOrCtrl+,', 'action' => 'open-settings'],
                ['type' => 'separator'],
                ['label' => 'Services', 'role' => 'services'],
                ['type' => 'separator'],
                ['label' => 'Hide Corex', 'role' => 'hide'],
                ['label' => 'Hide Others', 'role' => 'hideothers'],
                ['label' => 'Show All', 'role' => 'unhide'],
                ['type' => 'separator'],
                ['label' => 'Quit Corex', 'role' => 'quit'],
            ],
        ],

        'file' => [
            'label' => 'File',
            'submenu' => [
                ['label' => 'New File', 'accelerator' => 'CmdOrCtrl+N', 'action' => 'new-file'],
                ['label' => 'Open File...', 'accelerator' => 'CmdOrCtrl+O', 'action' => 'open-file'],
                ['label' => 'Open Folder...', 'accelerator' => 'CmdOrCtrl+Shift+O', 'action' => 'open-folder'],
                ['type' => 'separator'],
                ['label' => 'Save', 'accelerator' => 'CmdOrCtrl+S', 'action' => 'save-file'],
                ['label' => 'Save As...', 'accelerator' => 'CmdOrCtrl+Shift+S', 'action' => 'save-file-as'],
                ['label' => 'Save All', 'accelerator' => 'CmdOrCtrl+Alt+S', 'action' => 'save-all'],
                ['type' => 'separator'],
                ['label' => 'Close File', 'accelerator' => 'CmdOrCtrl+W', 'action' => 'close-file'],
                ['label' => 'Close Window', 'accelerator' => 'CmdOrCtrl+Shift+W', 'action' => 'close-window'],
            ],
        ],

        'edit' => [
            'label' => 'Edit',
            'submenu' => [
                ['label' => 'Undo', 'accelerator' => 'CmdOrCtrl+Z', 'role' => 'undo'],
                ['label' => 'Redo', 'accelerator' => 'CmdOrCtrl+Shift+Z', 'role' => 'redo'],
                ['type' => 'separator'],
                ['label' => 'Cut', 'accelerator' => 'CmdOrCtrl+X', 'role' => 'cut'],
                ['label' => 'Copy', 'accelerator' => 'CmdOrCtrl+C', 'role' => 'copy'],
                ['label' => 'Paste', 'accelerator' => 'CmdOrCtrl+V', 'role' => 'paste'],
                ['label' => 'Select All', 'accelerator' => 'CmdOrCtrl+A', 'role' => 'selectAll'],
                ['type' => 'separator'],
                ['label' => 'Find', 'accelerator' => 'CmdOrCtrl+F', 'action' => 'open-find'],
                ['label' => 'Find in Files', 'accelerator' => 'CmdOrCtrl+Shift+F', 'action' => 'open-find-in-files'],
                ['label' => 'Replace', 'accelerator' => 'CmdOrCtrl+H', 'action' => 'open-replace'],
            ],
        ],

        'view' => [
            'label' => 'View',
            'submenu' => [
                ['label' => 'Command Palette...', 'accelerator' => 'CmdOrCtrl+Shift+P', 'action' => 'open-command-palette'],
                ['type' => 'separator'],
                ['label' => 'Toggle Sidebar', 'accelerator' => 'CmdOrCtrl+B', 'action' => 'toggle-sidebar'],
                ['label' => 'Toggle Terminal', 'accelerator' => 'CmdOrCtrl+`', 'action' => 'toggle-terminal'],
                ['label' => 'Toggle Chat', 'accelerator' => 'CmdOrCtrl+Shift+I', 'action' => 'toggle-chat'],
                ['type' => 'separator'],
                ['label' => 'Zoom In', 'accelerator' => 'CmdOrCtrl+=', 'role' => 'zoomin'],
                ['label' => 'Zoom Out', 'accelerator' => 'CmdOrCtrl+-', 'role' => 'zoomout'],
                ['label' => 'Reset Zoom', 'accelerator' => 'CmdOrCtrl+0', 'role' => 'resetzoom'],
                ['type' => 'separator'],
                ['label' => 'Toggle Full Screen', 'accelerator' => 'F11', 'role' => 'togglefullscreen'],
                ['label' => 'Toggle DevTools', 'accelerator' => 'F12', 'role' => 'toggleDevTools'],
            ],
        ],

        'project' => [
            'label' => 'Project',
            'submenu' => [
                ['label' => 'Open Project...', 'accelerator' => 'CmdOrCtrl+Shift+O', 'action' => 'open-project'],
                ['label' => 'Close Project', 'accelerator' => 'CmdOrCtrl+Shift+W', 'action' => 'close-project'],
                ['type' => 'separator'],
                ['label' => 'New Project...', 'accelerator' => 'CmdOrCtrl+Alt+N', 'action' => 'new-project'],
                ['label' => 'Project Settings', 'accelerator' => 'CmdOrCtrl+,', 'action' => 'open-project-settings'],
                ['type' => 'separator'],
                ['label' => 'Recent Projects', 'action' => 'recent-projects'],
            ],
        ],

        'ai' => [
            'label' => 'AI',
            'submenu' => [
                ['label' => 'New Chat', 'accelerator' => 'CmdOrCtrl+Shift+L', 'action' => 'new-chat'],
                ['label' => 'Ask AI...', 'accelerator' => 'CmdOrCtrl+I', 'action' => 'ai-inline'],
                ['type' => 'separator'],
                ['label' => 'Explain Code', 'action' => 'ai-explain'],
                ['label' => 'Refactor Code', 'action' => 'ai-refactor'],
                ['label' => 'Generate Tests', 'action' => 'ai-generate-tests'],
                ['label' => 'Review Code', 'action' => 'ai-review'],
                ['type' => 'separator'],
                ['label' => 'Manage Models...', 'action' => 'manage-ai-models'],
            ],
        ],

        'window' => [
            'label' => 'Window',
            'submenu' => [
                ['label' => 'Minimize', 'accelerator' => 'CmdOrCtrl+M', 'role' => 'minimize'],
                ['label' => 'Close', 'accelerator' => 'CmdOrCtrl+W', 'role' => 'close'],
                ['type' => 'separator'],
                ['label' => 'Bring All to Front', 'role' => 'front'],
            ],
        ],

        'help' => [
            'label' => 'Help',
            'submenu' => [
                ['label' => 'Documentation', 'accelerator' => 'F1', 'action' => 'open-docs'],
                ['label' => 'Report Issue', 'action' => 'report-issue'],
                ['type' => 'separator'],
                ['label' => 'Check for Updates...', 'action' => 'check-updates'],
                ['label' => 'About Corex', 'action' => 'open-about'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | System Tray
    |--------------------------------------------------------------------------
    |
    | Configure the system tray icon and its context menu.
    |
    */
    'tray' => [
        'enabled' => true,
        'icon' => resource_path('icons/tray-icon.png'),
        'tooltip' => env('APP_NAME', 'Corex'),
        'menu' => [
            ['label' => 'Open Corex', 'action' => 'show-window'],
            ['label' => 'New Chat', 'action' => 'new-chat'],
            ['type' => 'separator'],
            ['label' => 'Check for Updates...', 'action' => 'check-updates'],
            ['type' => 'separator'],
            ['label' => 'Quit', 'action' => 'quit-app'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File System
    |--------------------------------------------------------------------------
    |
    | Configure file system access. NativePHP can request file system
    | permissions from the user at runtime.
    |
    */
    'filesystem' => [
        'default_path' => env('HOME') ?: env('USERPROFILE'),
        'allowed_paths' => [
            env('HOME') ?: env('USERPROFILE'),
            env('LOCALAPPDATA'),
        ],
        'register_protocol' => true,        // Register corex:// protocol handler
        'register_file_associations' => [
            'php', 'js', 'ts', 'vue', 'blade.php', 'json', 'yaml', 'yml',
            'md', 'css', 'scss', 'less', 'html', 'xml', 'env', 'gitignore',
            'sql', 'py', 'rb', 'go', 'rs', 'toml', 'lock',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Native Dialogs
    |--------------------------------------------------------------------------
    |
    | Configure behavior for native dialogs (open file, save file, etc.)
    |
    */
    'dialogs' => [
        'open_file' => [
            'title' => 'Open File',
            'default_path' => env('HOME') ?: env('USERPROFILE'),
            'filters' => [
                ['name' => 'All Supported Files', 'extensions' => ['php', 'js', 'ts', 'vue', 'blade.php', 'json', 'md', 'css', 'scss', 'html', 'py', 'go', 'rs']],
                ['name' => 'PHP', 'extensions' => ['php', 'blade.php']],
                ['name' => 'JavaScript / TypeScript', 'extensions' => ['js', 'ts', 'jsx', 'tsx', 'vue']],
                ['name' => 'Web Files', 'extensions' => ['html', 'css', 'scss', 'less']],
                ['name' => 'Data / Config', 'extensions' => ['json', 'yaml', 'yml', 'xml', 'toml', 'env']],
                ['name' => 'Markdown', 'extensions' => ['md']],
                ['name' => 'All Files', 'extensions' => ['*']],
            ],
            'multi_selections' => true,
            'properties' => ['openFile', 'multiSelections'],
        ],
        'save_file' => [
            'title' => 'Save File',
            'default_path' => env('HOME') ?: env('USERPROFILE'),
            'filters' => [
                ['name' => 'All Files', 'extensions' => ['*']],
            ],
        ],
        'open_folder' => [
            'title' => 'Open Folder',
            'default_path' => env('HOME') ?: env('USERPROFILE'),
            'properties' => ['openDirectory'],
        ],
        'message' => [
            'info' => ['type' => 'info', 'buttons' => ['OK']],
            'warning' => ['type' => 'warning', 'buttons' => ['OK', 'Cancel']],
            'error' => ['type' => 'error', 'buttons' => ['OK']],
            'confirm' => ['type' => 'question', 'buttons' => ['Yes', 'No']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure desktop notifications.
    |
    */
    'notifications' => [
        'enabled' => true,
        'timeout' => 5000,
        'silent' => false,
        'urgency' => 'normal',        // normal | critical | low
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Update
    |--------------------------------------------------------------------------
    |
    | Configure automatic updates for the desktop application.
    |
    */
    'auto_update' => [
        'enabled' => env('NATIVEPHP_AUTO_UPDATE', true),
        'provider' => 'github',       // github | s3 | generic
        'github_repo' => env('NATIVEPHP_GITHUB_REPO'),
        'check_on_startup' => true,
        'check_interval_hours' => 24,
        'download_automatically' => true,
        'install_on_quit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the embedded PHP server that runs the Laravel app.
    |
    */
    'php_server' => [
        'host' => '127.0.0.1',
        'port' => env('NATIVEPHP_PORT', 8100),
        'https' => false,
        'max_execution_time' => 0,
        'memory_limit' => '512M',
        'worker_threads' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Schemes / Deep Links
    |--------------------------------------------------------------------------
    |
    | Register custom URL schemes for deep linking into the app.
    |
    */
    'url_schemes' => [
        'corex' => [
            'scheme' => 'corex',
            'handler' => 'App\\Http\\Controllers\\Desktop\\DeepLinkController@handle',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyboard Shortcuts (global)
    |--------------------------------------------------------------------------
    |
    | Global keyboard shortcuts that work even when the app is minimized.
    |
    */
    'global_shortcuts' => [
        'CmdOrCtrl+Shift+Space' => 'quick-ai',
        'CmdOrCtrl+Shift+A' => 'open-ai-chat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security-related settings for the desktop app.
    |
    */
    'security' => [
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://browser.sentry-cdn.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; connect-src 'self' http://127.0.0.1:* https://api.corex.dev https://*.sentry.io; img-src 'self' data: blob:;",
        'allow_screen_capture' => false,
        'allow_geolocation' => false,
        'allow_microphone' => false,
        'allow_camera' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    |
    | Development and debugging settings.
    |
    */
    'debug' => [
        'enabled' => env('APP_DEBUG', false),
        'open_devtools' => env('NATIVEPHP_DEVTOOLS', false),
        'devtools_mode' => 'bottom',      // right | bottom | detach | undocked
        'log_level' => env('NATIVEPHP_LOG_LEVEL', 'error'),
    ],

];
