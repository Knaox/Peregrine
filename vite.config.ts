import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import tsconfigPaths from 'vite-tsconfig-paths';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        hmr: {
            host: '172.16.1.175',
        },
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
});
