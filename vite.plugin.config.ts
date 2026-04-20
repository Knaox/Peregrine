import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const pluginName = process.env.PLUGIN;

if (!pluginName) {
    throw new Error('PLUGIN environment variable is required. Usage: PLUGIN=hello-world pnpm run build:plugin');
}

const studlyName = pluginName
    .split('-')
    .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
    .join('');

export default defineConfig({
    plugins: [react()],
    build: {
        lib: {
            entry: `plugins/${pluginName}/frontend/index.tsx`,
            name: `PeregrinePlugin_${studlyName}`,
            formats: ['iife'],
            fileName: () => 'bundle.js',
        },
        outDir: `plugins/${pluginName}/frontend/dist`,
        emptyOutDir: true,
        copyPublicDir: false,
        rollupOptions: {
            external: [
                'react',
                'react-dom',
                '@tanstack/react-query',
                'react-router-dom',
                'react-i18next',
            ],
            output: {
                globals: {
                    'react': 'window.__PEREGRINE_SHARED__.React',
                    'react-dom': 'window.__PEREGRINE_SHARED__.ReactDOM',
                    '@tanstack/react-query': 'window.__PEREGRINE_SHARED__.ReactQuery',
                    'react-router-dom': 'window.__PEREGRINE_SHARED__.ReactRouterDom',
                    'react-i18next': 'window.__PEREGRINE_SHARED__',
                },
            },
        },
    },
});
