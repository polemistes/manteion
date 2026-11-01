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
                // The Aegean skin's titles; fetched only when that skin is on.
                bunny('Alegreya', {
                    weights: [400, 500],
                    styles: ['normal', 'italic'],
                    fallbacks: ['Iowan Old Style', 'Georgia', 'serif'],
                    optimizedFallbacks: false,
                    preload: false,
                }),
                // The Taverna skin's titles (one weight); likewise on demand.
                bunny('Young Serif', {
                    weights: [400],
                    fallbacks: ['Iowan Old Style', 'Georgia', 'serif'],
                    optimizedFallbacks: false,
                    preload: false,
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
