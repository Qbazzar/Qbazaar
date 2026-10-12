import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // CSS only — the server-rendered surfaces (welcome + /manage) need
            // no client JS. resources/js/* is vestigial Breeze scaffolding whose
            // Echo import pulls uninstalled deps; excluded so the build stays
            // self-contained. admin/rich-editor.js is a real entry, loaded only by the
            // admin forms that use <x-admin.rich-editor>.
            input: ['resources/css/app.css', 'resources/js/admin/rich-editor.js'],
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
