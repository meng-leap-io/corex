const path = require('path');

module.exports = {
  appId: 'dev.corex.desktop',
  productName: 'Corex',
  copyright: `Copyright ${new Date().getFullYear()} Corex.dev`,

  directories: {
    output: 'dist',
    buildResources: 'resources',
  },

  files: [
    'main.js',
    'preload.js',
    'package.json',
    'resources/**/*',
  ],

  extraResources: [
    {
      from: path.join(__dirname, '..', 'backend'),
      to: 'app',
      filter: [
        '**/*',
        '!node_modules/**',
        '!vendor/**',
        '!.env',
        '!storage/logs/**',
        '!storage/framework/cache/**',
        '!storage/framework/sessions/**',
        '!storage/framework/views/**',
        '!tests/**',
        '!public/build/**/*.map',
      ],
    },
    {
      from: path.join(__dirname, '..', 'ai-gateway'),
      to: 'ai-gateway',
      filter: ['**/*', '!__pycache__/**', '!.venv/**', '!node_modules/**', '!tests/**'],
    },
  ],

  // ── Windows Configuration ─────────────────────────────────────────
  win: {
    target: [
      { target: 'nsis', arch: ['x64'] },
      { target: 'portable', arch: ['x64'] },
    ],
    icon: 'resources/icon.ico',
    artifactName: 'Corex-Setup-${version}-win-x64.${ext}',
    certificateFile: process.env.CODESIGN_CERT_PATH,
    certificatePassword: process.env.CODESIGN_CERT_PASSWORD,
  },

  nsis: {
    oneClick: false,
    allowToChangeInstallationDirectory: true,
    perMachine: true,
    createDesktopShortcut: true,
    createStartMenuShortcut: true,
    shortcutName: 'Corex',
    uninstallDisplayName: 'Corex ${version}',
    include: 'installer.nsh',
    installerIcon: 'resources/icon.ico',
    uninstallerIcon: 'resources/icon.ico',
    installerHeaderIcon: 'resources/icon.ico',
    language: 'en_US',
    runAfterFinish: true,
  },

  portable: {
    artifactName: 'Corex-Portable-${version}-win-x64.exe',
    requestExecutionLevel: 'user',
  },

  // ── macOS Configuration ───────────────────────────────────────────
  mac: {
    target: [
      { target: 'dmg', arch: ['x64', 'arm64'] },
      { target: 'zip', arch: ['x64', 'arm64'] },
    ],
    icon: 'resources/icon.icns',
    category: 'public.app-category.developer-tools',
    hardenedRuntime: true,
    gatekeeperAssess: false,
    entitlements: 'resources/entitlements.mac.plist',
    entitlementsInherit: 'resources/entitlements.mac.plist',
    notarize: {
      teamId: process.env.APPLE_TEAM_ID,
    },
  },

  dmg: {
    title: 'Corex ${version}',
    artifactName: 'Corex-${version}-mac-${arch}.dmg',
    background: 'resources/dmg-background.png',
    contents: [
      { x: 130, y: 220, type: 'file' },
      { x: 410, y: 220, type: 'link', path: '/Applications' },
    ],
  },

  // ── Linux Configuration ───────────────────────────────────────────
  linux: {
    target: [
      { target: 'AppImage', arch: ['x64'] },
      { target: 'deb', arch: ['x64'] },
      { target: 'rpm', arch: ['x64'] },
    ],
    icon: 'resources/icon.png',
    category: 'Development',
    synopsis: 'AI-Powered Development Platform',
    description: 'Build, test, and deploy faster with AI-powered tools.',
    mimeTypes: ['x-scheme-handler/corex'],
  },

  // ── Publishing ────────────────────────────────────────────────────
  publish: [
    {
      provider: 'github',
      owner: process.env.GITHUB_REPO_OWNER || 'corex-dev',
      repo: process.env.GITHUB_REPO || 'corex',
      releaseType: 'release',
    },
  ],

  // ── Auto Update ───────────────────────────────────────────────────
  generateUpdatesFilesForAllChannels: true,

  // ── File Associations ─────────────────────────────────────────────
  fileAssociations: [
    { ext: 'php', name: 'PHP File', description: 'PHP script', role: 'Editor' },
    { ext: ['js', 'ts', 'jsx', 'tsx'], name: 'JavaScript/TypeScript', description: 'JS/TS source', role: 'Editor' },
    { ext: 'vue', name: 'Vue Component', description: 'Vue SFC', role: 'Editor' },
    { ext: ['json', 'yaml', 'yml', 'toml'], name: 'Config', description: 'Configuration file', role: 'Editor' },
    { ext: ['md', 'css', 'scss', 'html', 'xml'], name: 'Web Asset', description: 'Web asset file', role: 'Editor' },
    { ext: 'blade.php', name: 'Blade Template', description: 'Laravel Blade template', role: 'Editor' },
  ],

  // ── Protocol Handler ──────────────────────────────────────────────
  protocols: [
    { name: 'Corex URL', schemes: ['corex'] },
  ],

  // ── NSIS Installer Script ─────────────────────────────────────────
  include: 'installer.nsh',
};
