import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/features/map.js',
                'resources/js/features/admin/map.js',
                'resources/js/features/fotocopy-pdf-uploader.js',
            ],
            refresh: true,
        }),
    ],
});
