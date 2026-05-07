/**
 * Shared runtime accessors, types, styles and HTTP helpers for the modpack
 * installer plugin. All other modules import from here.
 *
 * Mirrors the pattern used in plugins/invitations/frontend/shared.ts —
 * keeps the rest of the codebase free of `window.__PEREGRINE_*__` casts.
 */

export const S = (window as unknown as Record<string, unknown>).__PEREGRINE_SHARED__ as {
    React: typeof import('react');
    ReactQuery: typeof import('@tanstack/react-query');
    ReactRouterDom: typeof import('react-router-dom');
    useTranslation: (ns?: string) => { t: (k: string, o?: Record<string, unknown>) => string };
};

export const P = (window as unknown as Record<string, unknown>).__PEREGRINE_PLUGINS__ as {
    register: (id: string, c: unknown) => void;
    registerServerPage: (id: string, c: unknown) => void;
};

export const h = S.React.createElement;

export const PLUGIN_ID = 'minecraft-modpack-installer';
export const BASE = `/api/plugins/${PLUGIN_ID}`;

export function csrf(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function api<T>(u: string, o: RequestInit = {}): Promise<T> {
    const r = await fetch(u, {
        ...o,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            ...o.headers,
        },
    });
    if (!r.ok) {
        const errorBody = await r.json().catch(() => ({}));
        throw { status: r.status, ...errorBody };
    }
    return r.json() as Promise<T>;
}

export function svg(d: string, size = 20, color = 'currentColor'): ReturnType<typeof h> {
    return h('svg', {
        width: size, height: size, viewBox: '0 0 24 24',
        fill: 'none', stroke: color, strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round',
    }, h('path', { d }));
}

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface Capabilities {
    search: boolean;
    pagination: boolean;
    minecraft_version_filter: boolean;
    loader_filter: boolean;
    server_marker: boolean;
    multiple_versions: boolean;
    /**
     * Canonical sort identifiers the provider supports
     * (`relevance | popular | downloads | updated | newest | name | follows | plays | featured`).
     * Empty list means the provider doesn't expose a sort knob.
     */
    sort_modes: string[];
    /** Whether the provider exposes a category/tag filter. */
    category_filter: boolean;
}

export interface Category {
    id: string;
    label: string;
    icon_url: string | null;
}

export interface Provider {
    id: string;
    name: string;
    configured: boolean;
    external_register_url: string | null;
    capabilities: Capabilities;
}

export interface ModpackHit {
    provider: string;
    modpack_id: string;
    name: string;
    slug: string | null;
    description: string | null;
    icon_url: string | null;
    external_url: string | null;
    is_server_compatible: boolean | null;
}

export interface ModpackVersion {
    version_id: string;
    label: string;
    minecraft_versions: string[];
    loaders: string[];
    release_type: string;
}

export interface InstallationState {
    id: number;
    provider: string;
    modpack_id: string;
    modpack_name: string;
    icon_url: string | null;
    version_id: string;
    version_label: string | null;
    external_url: string | null;
    status: 'pending' | 'installing' | 'completed' | 'failed' | 'uninstalling';
    status_message: string | null;
    is_active: boolean;
    java_version: number | null;
    started_at: string | null;
    completed_at: string | null;
}

export interface SearchMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

export const C = {
    page: { display: 'flex', flexDirection: 'column' as const, gap: '1.25rem' },
    header: { display: 'flex', flexWrap: 'wrap' as const, alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem' },
    headerLeft: { display: 'flex', alignItems: 'center', gap: '0.75rem' },
    iconBox: { width: 40, height: 40, borderRadius: 'var(--radius-lg)', background: 'rgba(var(--color-primary-rgb),0.1)', color: 'var(--color-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 },
    title: { fontSize: '1.125rem', fontWeight: 700, color: 'var(--color-text-primary)', margin: 0, lineHeight: 1.3 },
    subtitle: { fontSize: '0.75rem', color: 'var(--color-text-muted)', margin: 0 },
    sectionLabel: { fontSize: '0.6875rem', fontWeight: 600, textTransform: 'uppercase' as const, letterSpacing: '0.08em', color: 'var(--color-text-muted)', margin: 0 },

    card: { borderRadius: 'var(--radius-lg)', border: '1px solid var(--color-border)', background: 'var(--color-surface)', padding: '1rem', transition: 'border-color 200ms, transform 200ms' },
    glassCard: { borderRadius: 'var(--radius-lg)', border: '1px solid var(--color-border)', background: 'var(--color-glass)', backdropFilter: 'var(--glass-blur)', padding: '1.25rem' },

    grid: { display: 'grid', gap: '0.875rem', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))' },
    cardThumb: { width: '100%', aspectRatio: '16/9', borderRadius: 'var(--radius)', background: 'var(--color-surface-elevated, var(--color-surface))', objectFit: 'cover' as const, display: 'block' },
    cardName: { fontSize: '0.9375rem', fontWeight: 600, color: 'var(--color-text-primary)', margin: 0, overflow: 'hidden' as const, textOverflow: 'ellipsis' as const, whiteSpace: 'nowrap' as const },
    cardDesc: { fontSize: '0.8125rem', color: 'var(--color-text-secondary)', margin: 0, display: '-webkit-box' as const, WebkitLineClamp: 2 as unknown as string, WebkitBoxOrient: 'vertical' as const, overflow: 'hidden' as const },
    cardMeta: { display: 'flex', flexWrap: 'wrap' as const, alignItems: 'center', gap: '0.375rem', marginTop: '0.375rem' },

    btnPrimary: { padding: '0.5rem 0.875rem', fontSize: '0.8125rem', fontWeight: 600, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'var(--color-primary)', color: '#fff', border: 'none', display: 'inline-flex', alignItems: 'center', gap: '0.375rem', transition: 'opacity 150ms, transform 150ms' },
    btnGhost: { padding: '0.5rem 0.875rem', fontSize: '0.8125rem', fontWeight: 500, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'transparent', color: 'var(--color-text-secondary)', border: '1px solid var(--color-border)', display: 'inline-flex', alignItems: 'center', gap: '0.375rem' },
    btnDanger: { padding: '0.5rem 0.875rem', fontSize: '0.8125rem', fontWeight: 500, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'rgba(var(--color-danger-rgb),0.1)', color: 'var(--color-danger)', border: '1px solid rgba(var(--color-danger-rgb),0.15)', display: 'inline-flex', alignItems: 'center', gap: '0.375rem' },
    btnIcon: { width: 32, height: 32, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'transparent', color: 'var(--color-text-secondary)', border: '1px solid var(--color-border)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' },

    input: { width: '100%', padding: '0.5rem 0.75rem', fontSize: '0.8125rem', borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)', color: 'var(--color-text-primary)', outline: 'none', boxSizing: 'border-box' as const },
    select: { padding: '0.4375rem 1.875rem 0.4375rem 0.75rem', fontSize: '0.8125rem', borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)', color: 'var(--color-text-primary)', outline: 'none', cursor: 'pointer', appearance: 'none' as const, backgroundImage: "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b849e' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>\")", backgroundRepeat: 'no-repeat', backgroundPosition: 'right 0.625rem center' },

    badge: (bg: string, fg: string) => ({ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', borderRadius: 'var(--radius-full)', padding: '0.125rem 0.625rem', fontSize: '0.6875rem', fontWeight: 500, background: bg, color: fg }),

    bannerInfo: { display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.875rem 1rem', borderRadius: 'var(--radius-lg)', border: '1px solid rgba(var(--color-info-rgb,59 130 246),0.25)', background: 'rgba(var(--color-info-rgb,59 130 246),0.08)', color: 'var(--color-text-primary)' },
    bannerWarn: { display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.875rem 1rem', borderRadius: 'var(--radius-lg)', border: '1px solid rgba(var(--color-warning-rgb,245 158 11),0.25)', background: 'rgba(var(--color-warning-rgb,245 158 11),0.08)', color: 'var(--color-text-primary)' },
    bannerError: { display: 'flex', alignItems: 'center', gap: '0.75rem', padding: '0.875rem 1rem', borderRadius: 'var(--radius-lg)', border: '1px solid rgba(var(--color-danger-rgb),0.25)', background: 'rgba(var(--color-danger-rgb),0.08)', color: 'var(--color-text-primary)' },

    modalScrim: { position: 'fixed' as const, inset: 0, zIndex: 60, background: 'rgba(0,0,0,0.7)', backdropFilter: 'blur(4px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '1rem' },
    modalCard: { width: '100%', maxWidth: 560, maxHeight: 'calc(100vh - 2rem)', overflowY: 'auto' as const, borderRadius: 'var(--radius-lg)', border: '1px solid var(--color-border)', background: 'var(--color-surface)', padding: '1.25rem', display: 'flex', flexDirection: 'column' as const, gap: '1rem', boxShadow: 'var(--shadow-lg)' },
    versionList: { display: 'flex', flexDirection: 'column' as const, gap: '0.375rem', maxHeight: 320, overflowY: 'auto' as const, padding: '0.25rem', borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)' },
    versionRow: (selected: boolean, compatible: boolean) => ({
        display: 'flex',
        alignItems: 'center',
        gap: '0.625rem',
        padding: '0.5rem 0.625rem',
        borderRadius: 'var(--radius)',
        cursor: 'pointer',
        border: '1px solid ' + (selected ? 'var(--color-primary)' : 'transparent'),
        background: selected ? 'rgba(var(--color-primary-rgb),0.08)' : 'transparent',
        opacity: compatible ? 1 : 0.55,
        transition: 'background 120ms, border-color 120ms',
    }),

    skeleton: { borderRadius: 'var(--radius-lg)', minHeight: 220 },
    pagination: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '0.5rem', padding: '0.5rem 0' },
};

export const PROVIDER_LABEL_KEY = (id: string): string => `modpacks.providers.${id}.label`;
