import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.scss', 
                'resources/js/app.js',
                'resources/js/admin/dashboard/index.js',
                'resources/js/admin/content/index.js',
                'resources/js/admin/content/channel-import.js',
                'resources/js/admin/shared/admin-forms.js',
                'resources/js/admin/shared/admin-layout.js',
                'resources/js/admin/shared/admin-password-modal.js',
                'resources/js/admin/shared/bulk-actions.js',
                'resources/js/admin/settings/quota-monitor.js',
                'resources/js/admin/users/pending.js',
                'resources/js/resources/accounts/forgot-password.js',
                'resources/js/resources/galleries/index.js',
                'resources/js/resources/pins/entry.js',
                'resources/js/resources/player/show.js',
                'resources/js/resources/shared/options-menu-offcanvas.js',
                'resources/js/resources/shared/profile-picture-selector.js',
                'resources/js/resources/welcome/index.js'
            ],
            refresh: true,
        }),
    ],
    build: {
        // Enable minification (esbuild is faster and built-in)
        minify: 'esbuild',
        // Optimize chunk splitting
        rollupOptions: {
            output: {
                // Don't force IIFE format - let Vite handle it naturally
                // Our code uses IIFE internally and attaches to global namespace
                // Laravel Vite plugin will output scripts correctly
                manualChunks: (id) => {
                    // Separate vendor chunks for better caching
                    if (id.includes('node_modules')) {
                        if (id.includes('bootstrap')) {
                            return 'bootstrap';
                        }
                        if (id.includes('alpinejs')) {
                            return 'alpine';
                        }
                        if (id.includes('sortablejs')) {
                            return 'sortable';
                        }
                        if (id.includes('axios')) {
                            return 'axios';
                        }
                        // Other vendor modules will be auto-split by Vite
                        return 'vendor';
                    }
                    // Split admin and frontend code for better caching
                    if (id.includes('resources/js/admin')) {
                        return 'admin';
                    }
                    if (id.includes('resources/js/resources')) {
                        return 'frontend';
                    }
                    // Core modules stay in main bundle (app.js)
                    if (id.includes('resources/js/core')) {
                        return undefined; // Keep in main chunk
                    }
                    // Modules stay in main bundle (they're dependencies)
                    if (id.includes('resources/js/modules')) {
                        return undefined; // Keep in main chunk or with dependent resource
                    }
                },
                // Optimize chunk file names for caching
                chunkFileNames: 'assets/js/[name]-[hash].js',
                entryFileNames: 'assets/js/[name]-[hash].js',
                assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
            },
        },
        // Enable source maps for production (optional, can be disabled for smaller builds)
        sourcemap: false,
        // Optimize asset inlining threshold (files smaller than this will be inlined)
        assetsInlineLimit: 4096,
        // Enable CSS code splitting
        cssCodeSplit: true,
        // Target ES2020 - PS4 browser supports modern JavaScript features
        // No polyfills needed - native support for URLSearchParams, TextEncoder, padStart, etc.
        target: 'es2020',
        // Increase chunk size warning limit for better optimization
        chunkSizeWarningLimit: 1000,
        // Enable CSS minification (removes unused CSS similar to PurgeCSS)
        cssMinify: true,
    },
});
