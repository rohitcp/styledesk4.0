import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
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
        /**
         * Keep previously built bundles on disk.
         *
         * Vite clears the output directory on every build, so each rebuild
         * deletes the hashed files any already-open page is still pointing at.
         * That page's JavaScript then 404s and nothing mounts — no Vue, no
         * pickers, clicks that do nothing and not a single request reaching
         * the server, which is indistinguishable from a broken feature.
         *
         * Old files are dead weight, cleared with `rm -rf public/build`, and
         * that is a far better trade than a class of failure that looks like a
         * bug in whatever you happened to be using at the time.
         */
        emptyOutDir: false,
    },

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
