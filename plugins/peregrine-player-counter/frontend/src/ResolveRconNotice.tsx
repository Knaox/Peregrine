import { type CSSProperties, useState } from 'react';
import { api, BASE } from './shared';

type T = (key: string, opts?: Record<string, unknown>) => string;
type Phase = 'idle' | 'confirm' | 'working' | 'done' | 'error';

const box: CSSProperties = {
    marginTop: 12,
    padding: '0.6rem 0.75rem',
    borderRadius: 10,
    fontSize: 12,
    background: 'color-mix(in srgb, var(--color-warning, #f59e0b) 12%, transparent)',
    border: '1px solid color-mix(in srgb, var(--color-warning, #f59e0b) 35%, transparent)',
};

const btn: CSSProperties = {
    marginTop: 8,
    padding: '5px 12px',
    borderRadius: 8,
    fontSize: 12,
    fontWeight: 600,
    cursor: 'pointer',
    border: '1px solid transparent',
};

const ghost: CSSProperties = { ...btn, background: 'transparent', border: '1px solid var(--color-border)', color: 'var(--color-text-secondary)' };

/**
 * Shown only when a running server's RCON-counted game can't be reached. Offers
 * a one-click fix that allocates an RCON port + restarts the server — gated
 * behind an inline confirmation because it restarts the server.
 */
export function ResolveRconNotice({ serverId, t }: { serverId: number; t: T }) {
    const [phase, setPhase] = useState<Phase>('idle');
    const [err, setErr] = useState('');

    const run = async (): Promise<void> => {
        setPhase('working');
        try {
            await api(`${BASE}/servers/${serverId}/resolve-rcon`, { method: 'POST' });
            setPhase('done');
        } catch (e) {
            setErr((e as { error?: string })?.error || 'default');
            setPhase('error');
        }
    };

    const heading = phase === 'done' ? t('resolve_done') : phase === 'working' ? t('resolving') : t('warn_rcon');

    return (
        <div style={box}>
            <p style={{ margin: 0, color: 'var(--color-text-secondary)' }}>{heading}</p>

            {phase === 'idle' && (
                <button type="button" onClick={() => setPhase('confirm')} style={{ ...btn, background: 'var(--color-primary)', color: '#fff' }}>
                    {t('resolve')}
                </button>
            )}

            {phase === 'confirm' && (
                <div>
                    <p style={{ margin: '6px 0 0', color: 'var(--color-text-secondary)' }}>{t('resolve_confirm')}</p>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="button" onClick={run} style={{ ...btn, background: 'var(--color-danger, #ef4444)', color: '#fff' }}>{t('resolve_yes')}</button>
                        <button type="button" onClick={() => setPhase('idle')} style={ghost}>{t('resolve_cancel')}</button>
                    </div>
                </div>
            )}

            {phase === 'error' && (
                <div>
                    <p style={{ margin: '6px 0 0', color: 'var(--color-danger, #ef4444)' }}>{t('resolve_error', { error: t(`resolve_errors.${err || 'default'}`, { defaultValue: t('resolve_errors.default') }) })}</p>
                    <button type="button" onClick={() => setPhase('idle')} style={ghost}>{t('resolve_cancel')}</button>
                </div>
            )}
        </div>
    );
}
