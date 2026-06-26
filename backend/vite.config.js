import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  root: 'resources',
  base: '/build/',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
      '~css': path.resolve(__dirname, 'resources/css'),
    },
  },
  build: {
    outDir: '../public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js',
        sentry: 'resources/js/sentry.js',
      },
      output: {
        manualChunks: {
          vendor: ['alpinejs'],
          editor: ['@vue/repl'],
        },
        chunkFileNames: 'chunks/[name]-[hash].js',
        entryFileNames: '[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
    minify: 'esbuild',
    cssMinify: true,
    sourcemap: false,
    target: 'es2020',
    reportCompressedSize: true,
  },
  server: {
    port: 5173,
    strictPort: true,
    hmr: {
      host: 'localhost',
    },
    watch: {
      ignored: ['**/vendor/**', '**/node_modules/**', '**/storage/**'],
    },
  },
  css: {
    postcss: {
      plugins: [
        require('tailwindcss'),
        require('autoprefixer'),
      ],
    },
  },
  experimental: {
    renderBuiltUrl(filename) {
      const url = new URL(filename, 'http://localhost:5173')
      return url.pathname
    },
  },
})
