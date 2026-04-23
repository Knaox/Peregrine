import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import type { Server } from '@/types/Server';

interface SuspendedOverviewProps {
    server: Server;
}

/**
 * Hero card shown on the Overview page when `server.status === 'suspended'`.
 *
 * Replaces the regular dashboard panes with a calm warning surface : red /
 * orange palette, animated lock badge, clear human message explaining what
 * suspended means and what the customer should do (contact billing /
 * reactivate subscription). No power controls, no stats, no startup vars
 * are shown — they're all gated by `isSuspended` in the parent page.
 *
 * Pelican refuses every Wings command for suspended servers, so anything
 * we'd render here would either be stale or trigger a server-side error.
 */
export function SuspendedOverview({ server }: SuspendedOverviewProps) {
    const { t } = useTranslation();

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
                {/* Subdued banner backdrop — egg image with a warning tint */}
                <div className="relative" style={{ minHeight: 360 }}>
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
                            background:
                                'linear-gradient(180deg, transparent 0%, rgba(245, 158, 11, 0.08) 30%, var(--color-background) 95%)',
                        }}
                    />

                    {/* Slow pulsating warning halo */}
                    <m.div
                        aria-hidden
                        className="pointer-events-none absolute left-1/2 top-1/2 h-80 w-80 -translate-x-1/2 -translate-y-1/2 rounded-full"
                        style={{
                            background:
                                'radial-gradient(circle, rgba(245, 158, 11, 0.18) 0%, transparent 65%)',
                            filter: 'blur(50px)',
                        }}
                        animate={{ scale: [1, 1.08, 1], opacity: [0.5, 0.85, 0.5] }}
                        transition={{ duration: 4, ease: 'easeInOut', repeat: Infinity }}
                    />

                    <div className="relative flex flex-col items-center justify-center gap-5 px-6 py-14 text-center">
                        {/* Lock badge */}
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
                            {/* Animated padlock — closes once on mount, then a
                                gentle keyframe nudge every few seconds */}
                            <m.svg
                                className="h-9 w-9"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                strokeWidth={2}
                                animate={{ rotate: [0, -4, 0, 4, 0] }}
                                transition={{ duration: 2.4, repeat: Infinity, repeatDelay: 3, ease: 'easeInOut' }}
                            >
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0v4" />
                            </m.svg>
                        </m.div>

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
                                    background: 'rgba(245, 158, 11, 0.18)',
                                    color: 'var(--color-warning)',
                                    border: '1px solid rgba(245, 158, 11, 0.3)',
                                }}
                            >
                                <span className="h-1.5 w-1.5 rounded-full" style={{ background: 'var(--color-warning)' }} />
                                {t('servers.suspended.badge')}
                            </p>
                            <p className="mx-auto mt-3 max-w-lg text-sm sm:text-base font-medium text-[var(--color-text-secondary)]">
                                {t('servers.suspended.title')}
                            </p>
                            <p className="mx-auto max-w-lg text-xs sm:text-sm text-[var(--color-text-muted)]">
                                {t('servers.suspended.description')}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Calm info card — what stays, what doesn't */}
            <div
                className="rounded-[var(--radius-lg)] p-5"
                style={{
                    border: '1px solid var(--color-border)',
                    background: 'var(--color-surface)',
                }}
            >
                <h2 className="text-sm font-semibold text-[var(--color-text-primary)] mb-3 flex items-center gap-2">
                    <svg className="h-4 w-4 text-[var(--color-info)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <circle cx="12" cy="12" r="10" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 16v-4M12 8h.01" />
                    </svg>
                    {t('servers.suspended.info_title')}
                </h2>
                <ul className="space-y-2 text-sm text-[var(--color-text-secondary)]">
                    <li className="flex items-start gap-2">
                        <span className="mt-1 h-1 w-1 rounded-full bg-[var(--color-text-muted)] flex-shrink-0" />
                        <span>{t('servers.suspended.info_kept')}</span>
                    </li>
                    <li className="flex items-start gap-2">
                        <span className="mt-1 h-1 w-1 rounded-full bg-[var(--color-text-muted)] flex-shrink-0" />
                        <span>{t('servers.suspended.info_no_power')}</span>
                    </li>
                    <li className="flex items-start gap-2">
                        <span className="mt-1 h-1 w-1 rounded-full bg-[var(--color-text-muted)] flex-shrink-0" />
                        <span>{t('servers.suspended.info_reactivate')}</span>
                    </li>
                </ul>
            </div>
        </m.div>
    );
}
