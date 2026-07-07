import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';

const resolveAppUrl = (configuredAppUrl?: string): URL | null => {
    if (!configuredAppUrl) {
        return null;
    }

    try {
        return new URL(configuredAppUrl);
    } catch {
        return null;
    }
};

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = resolveAppUrl(env.APP_URL);

    return {
        server: {
            host: '0.0.0.0',
            hmr: appUrl
                ? {
                      host: appUrl.hostname,
                      protocol: appUrl.protocol === 'https:' ? 'wss' : 'ws',
                  }
                : undefined,
        },
        plugins: [
            laravel({
                detectTls: false,
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
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
    };
});
