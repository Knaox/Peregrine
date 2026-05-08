import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import tsconfigPaths from 'vite-tsconfig-paths';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const devHost = env.VITE_DEV_SERVER_HOST || 'localhost';

    return {
        server: {
            host: devHost,
            hmr: { host: devHost },
            cors: true,
        },
        plugins: [
            laravel({
                input: [
                    'resources/js/app.tsx',
                    'resources/js/setup/main.tsx',
                ],
                refresh: true,
            }),
            react(),
            tailwindcss(),
            tsconfigPaths(),
        ],
    };
});
