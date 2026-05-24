import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

/**
 * Frontend unit tests. Currently scoped to the easy-configuration plugin
 * (pure helpers, validation and the collapse hook); broaden `include` as other
 * plugins gain tests. jsdom gives the hook tests a DOM + localStorage.
 */
export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        include: ['plugins/**/frontend/src/**/*.test.{ts,tsx}'],
    },
});
