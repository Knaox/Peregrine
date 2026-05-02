import { useEffect, useState } from 'react';
import type { ThemeData } from '@/components/ThemeProvider';

export type PreviewMode = 'dark' | 'light';

export interface PreviewBridgeState {
    enabled: boolean;
    theme: ThemeData | null;
    mode: PreviewMode | null;
}

const PEER_ORIGIN = typeof window !== 'undefined' ? window.location.origin : '';

export function isPreviewMode(): boolean {
    if (typeof window === 'undefined') return false;
    return new URLSearchParams(window.location.search).has('preview');
}

/**
 * When the SPA runs inside the Theme Studio iframe (`/somewhere?preview=1`),
 * the parent window drives the theme via postMessage instead of the API.
 *
 * Wire-protocol messages (origin-checked against window.location.origin):
 *   { type: 'peregrine:theme:update', payload: ThemeData }      → full theme replace
 *   { type: 'peregrine:theme:setMode', payload: 'dark'|'light' } → mode override
 *
 * The iframe announces readiness with `peregrine:theme:ready` so the parent
 * knows when to fire its initial payload — avoids a race where the parent
 * sends the theme before the React tree mounted its listener.
 */
export function useThemePreviewBridge(): PreviewBridgeState {
    const [enabled] = useState(() => isPreviewMode());
    const [theme, setTheme] = useState<ThemeData | null>(null);
    const [mode, setMode] = useState<PreviewMode | null>(null);

    useEffect(() => {
        if (!enabled) return;

        const handler = (event: MessageEvent): void => {
            if (event.origin !== PEER_ORIGIN) return;
            const data = event.data as { type?: unknown; payload?: unknown } | null;
            if (!data || typeof data !== 'object') return;

            if (data.type === 'peregrine:theme:update' && data.payload) {
                setTheme(data.payload as ThemeData);
                return;
            }
            if (data.type === 'peregrine:theme:setMode') {
                const next = data.payload;
                if (next === 'dark' || next === 'light') {
                    setMode(next);
                }
            }
        };

        window.addEventListener('message', handler);
        window.parent?.postMessage({ type: 'peregrine:theme:ready' }, PEER_ORIGIN);

        return () => window.removeEventListener('message', handler);
    }, [enabled]);

    return { enabled, theme, mode };
}
