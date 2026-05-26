import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useCountUp } from '@/hooks/useCountUp';
import { AdminModeToggle } from '@/components/admin/AdminModeToggle';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';
import { useNamespace } from '@/i18n/useNamespace';

interface BiomeDashboardHeaderProps {
    userName?: string;
    isAdmin?: boolean;
    servers: Server[];
    statsMap: ServerStatsMap | undefined;
}

function greetingKey(): string {
    const h = new Date().getHours();
    if (h < 6) return 'nav.greeting_night';
    if (h < 12) return 'nav.greeting_morning';
    if (h < 18) return 'nav.greeting_afternoon';
    return 'nav.greeting_evening';
}

function Stat({ value, label, color, delay }: { value: number; label: string; color: string; delay: number }) {
    // useCountUp tweens through fractional frames — round for display so the
    // counter never shows a 16-digit float mid-animation.
    const n = Math.round(useCountUp(value));
    return (
        <m.div
            initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay, duration: 0.4 }}
            className="flex items-center gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-border)]/70 bg-[var(--color-surface)]/60 px-3.5 py-2 backdrop-blur-md"
        >
            <span className="h-2.5 w-2.5 rounded-full" style={{ background: color, boxShadow: `0 0 10px ${color}` }} />
            <div className="flex flex-col leading-none">
                <span className="font-mono text-lg font-bold tabular-nums text-[var(--color-text-primary)]">{n}</span>
                <span className="mt-0.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">{label}</span>
            </div>
        </m.div>
    );
}

/**
 * Hero header for the Biome dashboard variant. A glassy band with a drifting
 * conic halo, a shimmering greeting and live count-up fleet stats (total /
 * online / offline). Variant-scoped: the classic header still renders for
 * every other layout, so reverting the theme reverts the header too.
 */
export function BiomeDashboardHeader({ userName, isAdmin, servers, statsMap }: BiomeDashboardHeaderProps) {
    useNamespace(["server-overview"] as const);
    const { t } = useTranslation();
    const greet = useMemo(greetingKey, []);

    const online = useMemo(() => servers.reduce((acc, s) => {
        const state = statsMap?.[s.id]?.state ?? s.status;
        return acc + (state === 'running' || state === 'active' ? 1 : 0);
    }, 0), [servers, statsMap]);
    const total = servers.length;
    const offline = Math.max(0, total - online);

    return (
        <m.div
            initial={{ opacity: 0, y: -12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, ease: 'easeOut' }}
            className="relative mb-6 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)]/50 px-5 py-6 sm:px-7 sm:py-7 backdrop-blur-xl"
        >
            <div aria-hidden className="biome-hero-halo pointer-events-none absolute -right-24 -top-28 h-72 w-72" />
            <div aria-hidden className="pointer-events-none absolute inset-0 opacity-[0.04]"
                style={{ backgroundImage: 'linear-gradient(var(--color-text-primary) 1px, transparent 1px), linear-gradient(90deg, var(--color-text-primary) 1px, transparent 1px)', backgroundSize: '34px 34px' }} />

            <div className="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <m.h1
                        initial={{ opacity: 0, x: -18 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.1, duration: 0.45 }}
                        className="biome-shimmer-text text-2xl font-extrabold tracking-tight sm:text-3xl"
                    >
                        {t(greet, { name: userName ?? '' })}
                    </m.h1>
                    <m.p
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.22 }}
                        className="mt-1.5 text-sm text-[var(--color-text-secondary)]"
                    >
                        {t('server-overview:list.title')}
                    </m.p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <Stat value={total} label={t('server-overview:list.title')} color="var(--color-primary)" delay={0.15} />
                    <Stat value={online} label={t('server-overview:status.running', 'Online')} color="var(--color-success)" delay={0.22} />
                    <Stat value={offline} label={t('server-overview:status.stopped', 'Offline')} color={resolveHealthColor('stopped').color} delay={0.29} />
                    {isAdmin && (
                        <m.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }} transition={{ delay: 0.35, type: 'spring', stiffness: 300, damping: 20 }}
                            className="flex items-center gap-2">
                            <AdminModeToggle />
                            <a href="/admin" aria-label={t('common:nav.settings_admin')}
                                className="inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius-md)] border border-[var(--color-primary)]/30 text-[var(--color-primary)] transition-all duration-200 hover:border-[var(--color-primary)]/60 hover:shadow-[0_0_22px_var(--color-primary-glow)]">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </a>
                        </m.div>
                    )}
                </div>
            </div>
        </m.div>
    );
}
