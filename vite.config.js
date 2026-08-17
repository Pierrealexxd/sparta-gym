import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/panel-entry.css',
                'resources/css/public-entry.css',
                'resources/css/auth-entry.css',
                'resources/js/app-public.js',
                'resources/js/app-panel.js',
                'resources/js/carnet.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
