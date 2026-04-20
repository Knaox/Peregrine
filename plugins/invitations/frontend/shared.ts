/**
 * Shared runtime accessors + styles + types for the invitations plugin.
 * All plugin modules import from here.
 */
export const S = (window as unknown as Record<string, unknown>).__PEREGRINE_SHARED__ as {
    React: typeof import('react');
    ReactQuery: typeof import('@tanstack/react-query');
    ReactRouterDom: typeof import('react-router-dom');
    useTranslation: () => { t: (k: string, o?: Record<string, unknown>) => string };
};

export const P = (window as unknown as Record<string, unknown>).__PEREGRINE_PLUGINS__ as {
    register: (id: string, c: unknown) => void;
    registerServerPage: (id: string, c: unknown) => void;
};

export const h = S.React.createElement;

export const BASE = '/api/plugins/invitations';

export function csrf(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function api<T>(u: string, o: RequestInit = {}): Promise<T> {
    const r = await fetch(u, {
        ...o,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...o.headers },
    });
    if (!r.ok) throw await r.json().catch(() => ({}));
    return r.json() as Promise<T>;
}

export function svg(d: string, size = 20, color = 'var(--color-primary)'): ReturnType<typeof h> {
    return h('svg', {
        width: size, height: size, viewBox: '0 0 24 24',
        fill: 'none', stroke: color, strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round',
    }, h('path', { d }));
}

export interface Inv {
    id: number;
    email: string;
    permissions: string[];
    expires_at: string;
    inviter: { name: string } | null;
}

export interface Sub {
    uuid: string;
    username: string;
    email: string;
    image: string;
    '2fa_enabled': boolean;
    permissions: string[];
    /** Backend flag: true if this subuser's email matches the currently authenticated user. */
    is_current_user?: boolean;
}

export interface PG {
    group: string;
    label: string;
    permissions: { key: string; label: string }[];
}

export const C = {
    page: { display: 'flex', flexDirection: 'column' as const, gap: '1.5rem' },
    header: { display: 'flex', flexWrap: 'wrap' as const, alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem' },
    headerLeft: { display: 'flex', alignItems: 'center', gap: '0.75rem' },
    iconBox: { width: 40, height: 40, borderRadius: 'var(--radius-lg)', background: 'rgba(var(--color-primary-rgb),0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' },
    title: { fontSize: '1.125rem', fontWeight: 700, color: 'var(--color-text-primary)', margin: 0, lineHeight: 1.3 },
    subtitle: { fontSize: '0.75rem', color: 'var(--color-text-muted)', margin: 0 },
    card: { borderRadius: 'var(--radius-lg)', border: '1px solid var(--color-border)', background: 'var(--color-surface)', padding: '1.25rem', transition: 'border-color 200ms' },
    userRow: { display: 'flex', flexWrap: 'wrap' as const, alignItems: 'center', justifyContent: 'space-between', gap: '0.75rem' },
    userInfo: { display: 'flex', alignItems: 'center', gap: '0.75rem', minWidth: 0, flex: 1 },
    avatar: (bg: string, fg: string) => ({ width: 40, height: 40, borderRadius: '50%', background: bg, color: fg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.875rem', fontWeight: 700, flexShrink: 0 }),
    name: { fontSize: '0.875rem', fontWeight: 600, color: 'var(--color-text-primary)', margin: 0, overflow: 'hidden' as const, textOverflow: 'ellipsis' as const, whiteSpace: 'nowrap' as const },
    meta: { display: 'flex', alignItems: 'center', gap: '0.5rem', marginTop: '0.25rem', flexWrap: 'wrap' as const },
    badge: (bg: string, fg: string) => ({ display: 'inline-flex', alignItems: 'center', gap: '0.25rem', borderRadius: 'var(--radius-full)', padding: '0.125rem 0.625rem', fontSize: '0.6875rem', fontWeight: 500, background: bg, color: fg }),
    actions: { display: 'flex', alignItems: 'center', gap: '0.5rem', flexShrink: 0 },
    permBadge: { fontSize: '0.6875rem', fontFamily: 'var(--font-mono)', padding: '0.125rem 0.5rem', borderRadius: 'var(--radius)', background: 'rgba(var(--color-primary-rgb),0.08)', color: 'var(--color-primary)' },
    btnPrimary: { padding: '0.5rem 1.125rem', fontSize: '0.8125rem', fontWeight: 600, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'var(--color-primary)', color: '#fff', border: 'none', display: 'flex', alignItems: 'center', gap: '0.5rem', transition: 'opacity 150ms, box-shadow 200ms', boxShadow: '0 0 0 0 transparent' },
    btnDanger: { padding: '0.375rem 0.75rem', fontSize: '0.75rem', fontWeight: 500, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'rgba(var(--color-danger-rgb),0.1)', color: 'var(--color-danger)', border: '1px solid rgba(var(--color-danger-rgb),0.15)', transition: 'opacity 150ms' },
    btnGhost: { padding: '0.5rem 1rem', fontSize: '0.8125rem', fontWeight: 500, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'transparent', color: 'var(--color-text-secondary)', border: '1px solid var(--color-border)', transition: 'background 150ms' },
    btnSecondary: { padding: '0.375rem 0.75rem', fontSize: '0.75rem', fontWeight: 500, borderRadius: 'var(--radius)', cursor: 'pointer', background: 'rgba(var(--color-primary-rgb),0.1)', color: 'var(--color-primary)', border: '1px solid rgba(var(--color-primary-rgb),0.15)', transition: 'opacity 150ms' },
    input: { width: '100%', padding: '0.625rem 0.875rem', fontSize: '0.875rem', borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)', color: 'var(--color-text-primary)', outline: 'none', boxSizing: 'border-box' as const, transition: 'border-color 200ms, box-shadow 200ms' },
    sectionLabel: { fontSize: '0.6875rem', fontWeight: 600, textTransform: 'uppercase' as const, letterSpacing: '0.08em', color: 'var(--color-text-muted)', margin: 0 },
    emptyBox: { display: 'flex', flexDirection: 'column' as const, alignItems: 'center', gap: '1rem', padding: '4rem 1rem', textAlign: 'center' as const },
    emptyIcon: { width: 56, height: 56, borderRadius: 'var(--radius-lg)', background: 'var(--color-surface)', border: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', justifyContent: 'center' },
};
