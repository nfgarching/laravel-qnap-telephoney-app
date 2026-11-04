import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Nur Echo/Reverb für die Anrufliste; Bootstrap kommt im Layout vom CDN.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
    ],
});
