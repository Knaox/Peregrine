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
    /**
     * Optional unified-save-bar bridge (newer shells only). When present, the
     * plugin registers its dirty changes so the host's single save bar flushes
     * them alongside the core's; when absent, the plugin renders its own
     * FloatingSaveBar. Feature-detect with `typeof P.registerSaveSource ===
     * 'function'` — never assume it exists. Keeps the plugin autonomous, with
     * no hard dependency on the host's save coordinator.
     */
    registerSaveSource?: (id: string, source: { dirtyCount: number; save: () => Promise<void> }) => void;
    unregisterSaveSource?: (id: string) => void;
    /**
     * Optional slot (newer shells): render an extra control under a startup
     * variable's input on the core server "Configuration" card, keyed by
     * env_variable — used to surface the SandboxCode generator next to the
     * SANDBOX_CODE variable. Feature-detect before calling.
     */
    registerStartupVariableControl?: (
        envVariable: string,
        component: ComponentType<{ value: string; onChange: (value: string) => void; disabled: boolean }>,
    ) => void;
}

declare global {
    interface Window {
        __PEREGRINE_PLUGINS__: PeregrinePlugins;
    }
}

export const P: PeregrinePlugins = window.__PEREGRINE_PLUGINS__;

export const PLUGIN_ID = 'easy-configuration';
export const BASE = `/api/plugins/${PLUGIN_ID}`;

/**
 * SPA path of the admin template manager. It lives under a `/manage` sub-path,
 * NOT the bare `/plugins/easy-configuration`, because the host symlinks
 * `public/plugins/easy-configuration` -> the plugin's `frontend/dist` to serve
 * the bundle; nginx would 403 on the bare directory (autoindex off) instead of
 * falling through to the SPA. A sub-path absent from dist routes to index.php
 * and renders the SPA correctly.
 */
export const ADMIN_PATH = `/plugins/${PLUGIN_ID}/manage`;

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
        const body = (await response.json().catch(() => ({}))) as {
            error?: { code?: string; message?: string; messages?: string[]; fields?: ApiError['fields'] };
            message?: string;
            errors?: Record<string, string[]>;
        };
        const error = body.error;
        // Fall back to Laravel's validation shape ({ message, errors }) so a
        // FormRequest rejection (e.g. SaveTemplateRequest) surfaces its real
        // field messages instead of a generic error.
        const laravelMessages = body.errors ? Object.values(body.errors).flat() : undefined;
        throw {
            status: response.status,
            code: error?.code,
            message: error?.message ?? body.message,
            messages: error?.messages ?? laravelMessages,
            fields: error?.fields,
        } satisfies ApiError;
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}
