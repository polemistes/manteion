import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Schibsted Grotesk', {
                    weights: [400, 500, 600],
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                    optimizedFallbacks: false,
                    preload: [{ weight: 400 }, { weight: 600 }],
                }),
                bunny('Newsreader', {
                    weights: [400, 500],
                    styles: ['normal', 'italic'],
                    fallbacks: ['Iowan Old Style', 'Georgia', 'serif'],
                    optimizedFallbacks: false,
                    preload: [{ weight: 500 }],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
});
