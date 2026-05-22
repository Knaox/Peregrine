import type { ComponentType } from 'react';

/**
 * Runtime bridge to the host shell. React, TanStack Query, react-router-dom and
 * react-i18next are externalised by the plugin build (vite.plugin.config.ts) to
 * `window.__PEREGRINE_SHARED__`, so the rest of the plugin imports them as
 * normal modules. Only the plugin-registration bridge is accessed directly off
 * `window` here.
 */
interface PeregrinePlugins {
    register: (pluginId: string, component: ComponentType) => void;
    registerServerHomeSection: (sectionId: string, component: ComponentType<{ serverId: number }>) => void;
}

declare global {
    interface Window {
        __PEREGRINE_PLUGINS__: PeregrinePlugins;
    }
}

export const P: PeregrinePlugins = window.__PEREGRINE_PLUGINS__;

export const PLUGIN_ID = 'easy-configuration';
export const BASE = `/api/plugins/${PLUGIN_ID}`;

/** A structured API error thrown by {@link api}. */
export interface ApiError {
    status: number;
    code?: string;
    message?: string;
    messages?: string[];
    fields?: Record<string, Record<string, string>>;
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Same-origin JSON fetch wrapper. Mirrors the host's request conventions
 * (CSRF header, JSON accept) without importing the host's `@/services/http`,
 * which isn't available to the externalised plugin bundle.
 */
export async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    });

    if (!response.ok) {
        const body: unknown = await response.json().catch(() => ({}));
        const error = (body as { error?: { code?: string; message?: string; messages?: string[]; fields?: ApiError['fields'] } }).error;
        throw {
            status: response.status,
            code: error?.code,
            message: error?.message,
            messages: error?.messages,
            fields: error?.fields,
        } satisfies ApiError;
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}
