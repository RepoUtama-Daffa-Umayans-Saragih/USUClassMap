import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        sourcemap: false,
        reportCompressedSize: false,
        chunkSizeWarningLimit: 2500,
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/copilot.js',
                'resources/js/features/map.js',
                'resources/js/features/admin/map.js',
                'resources/js/features/fotocopy-pdf-uploader.js',
            ],
            refresh: true,
        }),
    ],
});
