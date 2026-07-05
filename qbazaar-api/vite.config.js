import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // CSS only — the server-rendered surfaces (welcome + /manage) need
            // no client JS. resources/js/* is vestigial Breeze scaffolding whose
            // Echo import pulls uninstalled deps; excluded so the build stays
            // self-contained.
            input: ['resources/css/app.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
