import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        target: 'es2022',
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (/[\/]node_modules[\/](vue|@vue|@inertiajs)[\/]/.test(id)) {
                        return 'vendor-core';
                    }
                },
            },
        },
    },
});
