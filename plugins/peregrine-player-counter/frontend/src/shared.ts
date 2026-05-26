import type { ComponentType } from 'react';

/**
 * Runtime bridge to the host shell. React, TanStack Query and react-i18next are
 * externalised by the plugin build to `window.__PEREGRINE_SHARED__`, so the
 * rest of the plugin imports them as normal modules. Only the registration
 * bridge is read off `window` here. `registerServerHomeSection` is optional:
 * older shells lack the slot, so `index.tsx` feature-detects it.
 */
interface PeregrinePlugins {
    registerServerHomeSection?: (
        id: string,
        component: ComponentType<{ serverId: number; serverState?: string }>
    ) => void;
}

declare global {
    interface Window {
        __PEREGRINE_PLUGINS__: PeregrinePlugins;
    }
}

export const P: PeregrinePlugins = window.__PEREGRINE_PLUGINS__;

export const PLUGIN_ID = 'peregrine-player-counter';
export const BASE = `/api/plugins/${PLUGIN_ID}`;

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Same-origin JSON fetch wrapper mirroring the host's CSRF conventions without
 * importing `@/services/http` (unavailable to the externalised bundle).
 */
export async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    });

    if (!response.ok) {
        const body = (await response.json().catch(() => ({}))) as { error?: string; message?: string };
        throw { status: response.status, error: body.error, message: body.message };
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}
