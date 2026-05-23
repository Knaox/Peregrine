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
    // Bundled CommonJS deps (e.g. zustand's `use-sync-external-store` shim)
    // switch on `process.env.NODE_ENV` at runtime. Vite does NOT shim `process`
    // for an IIFE library build, so the reference survives into bundle.js and
    // throws `ReferenceError: process is not defined` in the browser before the
    // plugin can register itself ("Plugin … is not loaded"). Statically replace
    // it at build time so the prod branch is taken and `process` never appears.
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
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
