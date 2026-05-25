import { type CSSProperties, useEffect, useRef, useState } from 'react';
import { useServerPlayers } from './useServerPlayers';
import { ResolveRconNotice } from './ResolveRconNotice';
import { useT } from './lib/i18n';

type T = (key: string, opts?: Record<string, unknown>) => string;

const reduced = (): boolean =>
    typeof window !== 'undefined' && !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** Ease-out count-up; returns the target immediately when animation is disabled. */
function useCountUp(target: number, enabled: boolean): number {
    const [val, setVal] = useState(enabled ? 0 : target);
    const prev = useRef(0);
    const raf = useRef(0);

    useEffect(() => {
        if (!enabled) {
            prev.current = target;
            setVal(target);
            return;
        }
        const from = prev.current;
        prev.current = target;
        const start = performance.now();
        const tick = (now: number): void => {
            const p = Math.min((now - start) / 500, 1);
            setVal(from + (target - from) * (1 - Math.pow(1 - p, 3)));
            if (p < 1) raf.current = requestAnimationFrame(tick);
        };
        raf.current = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf.current);
    }, [target, enabled]);

    return val;
}

const MUTED = 'var(--color-text-muted)';

const cardStyle: CSSProperties = {
    background: 'var(--color-surface)',
    border: '1px solid var(--color-border)',
    borderRadius: 'var(--radius-lg, 14px)',
    padding: '1rem 1.25rem',
};

const usersIcon = (
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-3-6.5" />
    </svg>
);

function StatusPill({ state, t }: { state: string; t: T }) {
    if (state !== 'online' && state !== 'offline') return null;
    const on = state === 'online';
    return (
        <span
            style={{
                display: 'inline-flex', alignItems: 'center', gap: 8, borderRadius: 999, padding: '4px 10px', fontSize: 12, fontWeight: 600,
                background: on ? 'color-mix(in srgb, var(--color-success) 15%, transparent)' : 'color-mix(in srgb, var(--color-text-muted) 12%, transparent)',
                color: on ? 'var(--color-success)' : MUTED,
            }}
        >
            <span style={{ position: 'relative', display: 'inline-flex', height: 8, width: 8 }} aria-hidden="true">
                {on && <span className="pgpc-ping" style={{ position: 'absolute', inset: 0, borderRadius: 999, background: 'var(--color-success)', opacity: 0.75 }} />}
                <span style={{ position: 'relative', display: 'inline-flex', height: 8, width: 8, borderRadius: 999, background: on ? 'var(--color-success)' : MUTED }} />
            </span>
            {on ? t('live') : t('offline_short')}
        </span>
    );
}

export function PlayerCountCard({ serverId, serverState }: { serverId: number; serverState?: string }) {
    const t = useT();

    // The host passes the server's live WS power state. We only query (and poll)
    // a truly-running server — so an offline/starting server reads "offline"
    // instantly without firing the slow query or competing with the WS handshake.
    const isRunning = serverState == null || serverState === 'running';
    const { data, isLoading } = useServerPlayers(serverId, isRunning);

    const effState = isRunning ? (data?.state ?? 'unknown') : 'offline';
    const online = effState === 'online' ? (data?.online ?? null) : null;
    const max = data?.max ?? null;
    const names = effState === 'online' ? (data?.players ?? []) : [];
    const isOnline = effState === 'online' && online !== null;
    const isUnsupported = effState === 'unsupported';
    // RCON-counted game (ARK/EOS) that's running but unreachable → offer the fix.
    const canResolveRcon = isRunning && effState === 'offline' && data?.family === 'eos';
    const showSkeleton = isRunning && isLoading && !data;
    const animated = useCountUp(online ?? 0, !reduced() && isOnline);

    if (data?.state === 'unavailable') return null; // plugin disabled — show nothing

    const pct = isOnline && max && max > 0 ? Math.min(100, (online! / max) * 100) : 0;

    return (
        <section style={cardStyle} aria-label={t('title')}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <span style={{ background: 'color-mix(in srgb, var(--color-primary) 12%, transparent)', color: 'var(--color-primary)', borderRadius: 10, padding: 8, display: 'inline-flex' }}>
                        {usersIcon}
                    </span>
                    <span style={{ fontSize: 12, fontWeight: 600, color: MUTED }}>{t('title')}</span>
                </div>
                <StatusPill state={effState} t={t} />
            </div>

            {showSkeleton ? (
                <div style={{ marginTop: 16, height: 34, width: 110, borderRadius: 8, background: 'color-mix(in srgb, var(--color-text-muted) 14%, transparent)' }} />
            ) : isUnsupported ? (
                <p style={{ marginTop: 12, fontSize: 14, color: 'var(--color-text-secondary)' }}>{t('unsupported')}</p>
            ) : (
                <div style={{ marginTop: 12 }}>
                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8 }}>
                        <span
                            style={{ fontSize: 34, fontWeight: 800, lineHeight: 1, fontVariantNumeric: 'tabular-nums', color: isOnline ? 'var(--color-text-primary)' : MUTED }}
                            aria-live="polite"
                            aria-label={isOnline ? t('aria', { count: online!, max: max ?? 0 }) : t('offline')}
                        >
                            {isOnline ? Math.round(animated) : '—'}
                        </span>
                        {isOnline && max != null && (
                            <span style={{ fontSize: 16, fontWeight: 600, paddingBottom: 2, fontVariantNumeric: 'tabular-nums', color: MUTED }}>/ {max}</span>
                        )}
                    </div>

                    {isOnline && max != null && max > 0 ? (
                        <div style={{ marginTop: 12, height: 6, width: '100%', borderRadius: 999, overflow: 'hidden', background: 'color-mix(in srgb, var(--color-text-muted) 18%, transparent)' }}>
                            <div style={{ height: '100%', width: `${pct}%`, borderRadius: 999, background: 'var(--color-success)', transition: reduced() ? 'none' : 'width .5s ease' }} />
                        </div>
                    ) : !isOnline ? (
                        <p style={{ marginTop: 4, fontSize: 12, color: MUTED }}>{t('offline')}</p>
                    ) : null}

                    {isOnline && names.length > 0 && (
                        <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
                            {names.map((n, i) => (
                                <span key={`${n}-${i}`} style={{ display: 'inline-flex', maxWidth: 180, borderRadius: 999, padding: '2px 8px', fontSize: 12, color: 'var(--color-text-secondary)', background: 'color-mix(in srgb, var(--color-text-muted) 12%, transparent)' }}>
                                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{n}</span>
                                </span>
                            ))}
                            {online != null && online > names.length && (
                                <span style={{ fontSize: 12, color: MUTED }}>{t('more', { count: online - names.length })}</span>
                            )}
                        </div>
                    )}

                    {canResolveRcon && <ResolveRconNotice serverId={serverId} t={t} />}
                </div>
            )}
        </section>
    );
}
