import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';
import type { Plugin } from 'vite-plus';

/**
 * Serves the app from a subfolder (staging runs it at /pharmacy). Pages and the
 * Wayfinder routes use root-relative literals like '/login', so they need the
 * folder in front. Set APP_PATH_PREFIX at build time
 * (APP_PATH_PREFIX=pharmacy npm run build); unset, this does nothing.
 *
 * ponytail: rewrites every string literal in resources/js starting with "/" plus a
 * letter. If a non-URL string ever starts that way, switch it to a Wayfinder route.
 */
function basePath(): Plugin {
    const prefix = (process.env.APP_PATH_PREFIX ?? '').replace(/^\/|\/$/g, '');

    return {
        name: 'pharmacy:base-path',
        enforce: 'pre',
        // Lazy-loaded chunks are fetched from here; the Laravel plugin would otherwise
        // derive it from ASSET_URL, which the CI build doesn't have.
        config: () => (prefix === '' ? {} : { base: `/${prefix}/build/` }),
        transform(code, id) {
            if (
                prefix === '' ||
                !/resources[\\/]js[\\/].*\.(ts|vue)$/.test(id)
            ) {
                return null;
            }

            // The lookahead keeps an already prefixed URL from gaining a second.
            return code.replace(
                new RegExp(`(['"\`])/(?!${prefix}[/'"\`?])(?=[a-z])`, 'g'),
                `$1/${prefix}/`,
            );
        },
    };
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        basePath(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Figtree', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Bricolage Grotesque', {
                    weights: [600, 700],
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
    ]),
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
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
