import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { m } from 'motion/react';
import type { Server } from '@/types/Server';
import type { ServerConflictKind } from '@/utils/serverConflictState';

interface ServerConflictScreenProps {
    server: Server;
    kind: Exclude<ServerConflictKind, null>;
}

/**
 * Full-pane block surfaced by `withServerConflictGate` whenever a user
 * lands on a server page that isn't accessible in the current state
 * (Files / Databases / SFTP / Network / Backups / Schedules during
 * suspended or provisioning). Mirrors Pterodactyl's
 * `ConflictStateRenderer` with our visual language — same animated
 * halo + badge as `SuspendedOverview` / `InstallationOverview` so the
 * shell stays cohesive.
 *
 * The CTAs always include "back to overview" (which has its own
 * status-aware hero) and, during provisioning, "open the console"
 * (the install logs stream there — that's the one page that survives
 * the gate, matching Pelican's `Console::mount()` AlertBanner pattern).
 */
export function ServerConflictScreen({ server, kind }: ServerConflictScreenProps) {
    const { t } = useTranslation();

    const palette = paletteFor(kind);

    return (
        <m.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.4, 0, 0.2, 1] }}
            className="space-y-6"
        >
            <div
                className="relative overflow-hidden rounded-[var(--radius-xl)]"
                style={{ border: '1px solid var(--color-border)' }}
            >
                <div className="relative" style={{ minHeight: 320 }}>
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="absolute inset-0 h-full w-full object-cover opacity-30 grayscale"
                        />
                    ) : (
                        <div
                            className="absolute inset-0"
                            style={{
                                background:
                                    'linear-gradient(135deg, var(--color-surface-hover), var(--color-background))',
                            }}
                        />
                    )}
                    <div
                        className="absolute inset-0"
                        style={{
                            background: `linear-gradient(180deg, transparent 0%, ${palette.haloColor} 30%, var(--color-background) 95%)`,
                        }}
                    />

                    {/* Pulsating halo — same vibe as SuspendedOverview but
                        re-coloured per state so the user reads the kind at a
                        glance even on a 4K screen */}
                    <m.div
                        aria-hidden
                        className="pointer-events-none absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full"
                        style={{
                            background: `radial-gradient(circle, ${palette.haloColor} 0%, transparent 65%)`,
                            filter: 'blur(50px)',
                        }}
                        animate={{ scale: [1, 1.08, 1], opacity: [0.5, 0.85, 0.5] }}
                        transition={{ duration: 4, ease: 'easeInOut', repeat: Infinity }}
                    />

                    <div className="relative flex flex-col items-center justify-center gap-5 px-6 py-12 text-center">
                        <ConflictBadge kind={kind} />

                        <div className="space-y-2">
                            <h1
                                className="text-2xl sm:text-3xl font-extrabold text-white"
                                style={{ textShadow: '0 2px 30px rgba(0,0,0,0.6)' }}
                            >
                                {server.name}
                            </h1>
                            <p
                                className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                                style={{
                                    background: palette.pillBg,
                                    color: palette.pillFg,
                                    border: `1px solid ${palette.pillBorder}`,
                                }}
                            >
                                <span className="h-1.5 w-1.5 rounded-full" style={{ background: palette.pillFg }} />
                                {t(`servers.status.${kind}`)}
                            </p>
                            <p className="mx-auto mt-3 max-w-lg text-sm sm:text-base font-medium text-[var(--color-text-secondary)]">
                                {t(titleKeyFor(kind))}
                            </p>
                            <p className="mx-auto max-w-lg text-xs sm:text-sm text-[var(--color-text-muted)]">
                                {t(descriptionKeyFor(kind))}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center justify-center gap-2 pt-2">
                            <Link
                                to={`/servers/${server.id}`}
                                className="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition-opacity hover:opacity-90"
                                style={{
                                    background: 'var(--color-primary)',
                                    color: 'var(--color-primary-fg, white)',
                                }}
                            >
                                {t('servers.conflict.go_overview')}
                            </Link>
                            {kind === 'provisioning' ? (
                                <Link
                                    to={`/servers/${server.id}/console`}
                                    className="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition-colors"
                                    style={{
                                        background: 'transparent',
                                        color: 'var(--color-text-primary)',
                                        border: '1px solid var(--color-border)',
                                    }}
                                >
                                    {t('servers.install.view_console')}
                                </Link>
                            ) : null}
                        </div>
                    </div>
                </div>
            </div>
        </m.div>
    );
}

// ---------------------------------------------------------------------
// Per-kind cosmetic mapping. Kept in this file (not in CSS) because the
// sub-200-line constraint plus the radial-gradient computation favour
// inline definitions over a separate stylesheet.
// ---------------------------------------------------------------------

interface Palette {
    haloColor: string;
    pillBg: string;
    pillFg: string;
    pillBorder: string;
}

function paletteFor(kind: Exclude<ServerConflictKind, null>): Palette {
    if (kind === 'suspended') {
        return {
            haloColor: 'rgba(245, 158, 11, 0.18)',
            pillBg: 'rgba(245, 158, 11, 0.18)',
            pillFg: 'var(--color-warning)',
            pillBorder: 'rgba(245, 158, 11, 0.3)',
        };
    }
    if (kind === 'provisioning_failed') {
        return {
            haloColor: 'rgba(239, 68, 68, 0.18)',
            pillBg: 'rgba(239, 68, 68, 0.18)',
            pillFg: 'var(--color-danger)',
            pillBorder: 'rgba(239, 68, 68, 0.3)',
        };
    }
    // provisioning
    return {
        haloColor: 'rgba(99, 102, 241, 0.18)',
        pillBg: 'rgba(99, 102, 241, 0.18)',
        pillFg: 'var(--color-primary)',
        pillBorder: 'rgba(99, 102, 241, 0.3)',
    };
}

function titleKeyFor(kind: Exclude<ServerConflictKind, null>): string {
    if (kind === 'suspended') return 'servers.suspended.title';
    if (kind === 'provisioning_failed') return 'servers.conflict.failed_title';
    return 'servers.install.in_progress_title';
}

function descriptionKeyFor(kind: Exclude<ServerConflictKind, null>): string {
    if (kind === 'suspended') return 'servers.suspended.description';
    if (kind === 'provisioning_failed') return 'servers.conflict.failed_description';
    return 'servers.install.console_only_notice';
}

// ---------------------------------------------------------------------
// Badge — one of three icons matching the conflict kind. Kept inline
// rather than separate file so callers only need one import.
// ---------------------------------------------------------------------

function ConflictBadge({ kind }: { kind: Exclude<ServerConflictKind, null> }) {
    if (kind === 'suspended') {
        return (
            <m.div
                initial={{ scale: 0.85, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ duration: 0.5, ease: [0.34, 1.56, 0.64, 1] }}
                className="flex h-20 w-20 items-center justify-center rounded-full"
                style={{
                    background: 'rgba(245, 158, 11, 0.16)',
                    border: '1px solid rgba(245, 158, 11, 0.45)',
                    color: 'var(--color-warning)',
                    boxShadow: '0 0 36px rgba(245, 158, 11, 0.32)',
                }}
            >
                <svg className="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <rect x="5" y="11" width="14" height="10" rx="2" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0v4" />
                </svg>
            </m.div>
        );
    }

    if (kind === 'provisioning_failed') {
        return (
            <m.div
                initial={{ scale: 0.85, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ duration: 0.5, ease: [0.34, 1.56, 0.64, 1] }}
                className="flex h-20 w-20 items-center justify-center rounded-full"
                style={{
                    background: 'rgba(239, 68, 68, 0.16)',
                    border: '1px solid rgba(239, 68, 68, 0.45)',
                    color: 'var(--color-danger)',
                    boxShadow: '0 0 36px rgba(239, 68, 68, 0.32)',
                }}
            >
                <svg className="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3m0 4h.01" />
                    <circle cx="12" cy="12" r="9" />
                </svg>
            </m.div>
        );
    }

    // provisioning — concentric spinning rings, same vibe as
    // InstallationOverview's hero badge
    return (
        <m.div
            initial={{ scale: 0.85, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ duration: 0.5, ease: [0.34, 1.56, 0.64, 1] }}
            className="relative flex h-20 w-20 items-center justify-center rounded-full"
            style={{
                background: 'rgba(99, 102, 241, 0.16)',
                border: '1px solid rgba(99, 102, 241, 0.45)',
                color: 'var(--color-primary)',
                boxShadow: '0 0 36px rgba(99, 102, 241, 0.32)',
            }}
        >
            <m.div
                aria-hidden
                className="absolute inset-1 rounded-full"
                style={{ border: '2px dashed rgba(99, 102, 241, 0.5)' }}
                animate={{ rotate: 360 }}
                transition={{ duration: 6, repeat: Infinity, ease: 'linear' }}
            />
            <svg className="h-8 w-8 relative" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2" />
                <circle cx="12" cy="12" r="9" />
            </svg>
        </m.div>
    );
}
