import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

/**
 * Frontend unit tests. Covers plugin frontends plus core SPA helpers under
 * resources/js (pure helpers, validation and hooks). jsdom gives the hook
 * tests a DOM + localStorage.
 */
export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        include: [
            'plugins/**/frontend/src/**/*.test.{ts,tsx}',
            'resources/js/**/*.test.{ts,tsx}',
        ],
    },
});
