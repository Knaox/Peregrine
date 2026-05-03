import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { copyToClipboard } from '@/utils/clipboard';
import { formatBytes, formatCpu, formatUptime } from '@/utils/format';
import { ServerCardPowerButtons } from '@/components/server/ServerCardPowerButtons';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStats } from '@/types/ServerStats';

interface PulseDetailDrawerProps {
    server: Server | null;
    stats: ServerStats | undefined;
    cardConfig: CardConfig;
    onClose: () => void;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
}

/**
 * Right-edge slide-in detail panel for the Pulse Grid layout. Shows
 * everything the classic card surfaces (banner, stats, power, address)
 * without leaving the grid context — clicking a tile in the heatmap
 * pops this open so the admin can triage without losing scroll position.
 *
 * Closes on outside click, ESC key, or the explicit close button.
 */
export function PulseDetailDrawer({
    server,
    stats,
    cardConfig,
    onClose,
    onPower,
    isPowerPending,
}: PulseDetailDrawerProps) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    // Track sm+ breakpoint to switch between bottom-sheet (mobile) and
    // side-slide (sm+). SSR-safe initial value. Listener kept lightweight —
    // the drawer is mounted only when a server is selected so re-subscribing
    // on each open isn't a perf concern.
    const [isDesktop, setIsDesktop] = useState<boolean>(
        () => typeof window !== 'undefined' && window.matchMedia('(min-width: 640px)').matches,
    );
    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) return;
        const mq = window.matchMedia('(min-width: 640px)');
        const handler = (e: MediaQueryListEvent) => setIsDesktop(e.matches);
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, []);

    const isOpen = server !== null;

    const lifecycleStatus = server
        ? server.status === 'suspended' ||
          server.status === 'provisioning' ||
          server.status === 'provisioning_failed' ||
          server.status === 'terminated'
            ? server.status
            : null
        : null;
    const state = (lifecycleStatus ?? stats?.state ?? server?.status ?? 'stopped') as string;
    const health = resolveHealthColor(state);
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const isInactive = state === 'suspended' || state === 'provisioning' || state === 'provisioning_failed';
    const address = server?.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const ramLimitMb = server?.plan?.ram ?? server?.limits?.memory ?? 0;
    const ramUsedMb = stats ? stats.memory_bytes / (1024 * 1024) : 0;
    const ramPct = ramLimitMb > 0 ? Math.min(100, (ramUsedMb / ramLimitMb) * 100) : 0;

    const handleCopy = () => {
        if (!address) return;
        void copyToClipboard(address).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    // Portal to <body> so the drawer escapes any stacking context built
    // by ancestors (`<motion.main relative z-10>` in AppLayout, transforms
    // on parents, etc.). Without this the drawer ends up trapped under
    // the sticky navbar regardless of z-index value.
    const drawer = (
        <AnimatePresence>
            {isOpen && server && (
                <>
                    <m.div
                        key="pulse-scrim"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        onClick={onClose}
                        className="fixed inset-0 z-[1000] bg-black/50 backdrop-blur-sm"
                        aria-hidden
                    />
                    <m.aside
                        key="pulse-drawer"
                        initial={isDesktop ? { x: '100%' } : { y: '100%' }}
                        animate={isDesktop ? { x: 0 } : { y: 0 }}
                        exit={isDesktop ? { x: '100%' } : { y: '100%' }}
                        transition={{ type: 'spring', stiffness: 320, damping: 32 }}
                        className={
                            isDesktop
                                ? 'fixed right-0 top-0 z-[1001] flex h-full w-full max-w-md flex-col overflow-hidden border-l border-[var(--color-border)] bg-[var(--color-surface)] shadow-2xl'
                                : 'fixed inset-x-0 bottom-0 z-[1001] flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-[var(--radius-xl)] border-t border-[var(--color-border)] bg-[var(--color-surface)] shadow-2xl'
                        }
                        role="dialog"
                        aria-label={server.name}
                    >
                        <DrawerHeader
                            server={server}
                            healthColor={health.color}
                            stateLabel={t(`servers.status.${state}`, state)}
                            isAlive={health.isAlive}
                            onClose={onClose}
                        />

                        <div className="flex-1 overflow-y-auto px-5 pb-6">
                            {address && (
                                <Section label={t('servers.list.address', 'Address')}>
                                    <button
                                        type="button"
                                        onClick={handleCopy}
                                        className="inline-flex items-center gap-2 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-background)]/40 px-3 py-2 font-mono text-sm text-[var(--color-text-primary)] hover:border-[var(--color-primary)] cursor-pointer transition-colors w-full"
                                    >
                                        <span className="flex-1 text-left">{address}</span>
                                        <span className="text-xs text-[var(--color-text-muted)]">
                                            {copied ? t('servers.list.copied') : t('common.copy', 'Copy')}
                                        </span>
                                    </button>
                                </Section>
                            )}

                            {stats && !isInactive && (
                                <Section label={t('servers.list.stats', 'Live stats')}>
                                    <div className="grid grid-cols-2 gap-3">
                                        <Metric label="CPU" value={formatCpu(stats.cpu)} pct={Math.min(100, stats.cpu)} color={health.color} />
                                        <Metric label="RAM" value={formatBytes(stats.memory_bytes)} pct={ramPct} color={health.color} />
                                        <Metric label="Disk" value={formatBytes(stats.disk_bytes)} pct={null} color={health.color} />
                                        <Metric label={t('servers.list.uptime', 'Uptime')} value={stats.uptime > 0 ? formatUptime(stats.uptime) : '—'} pct={null} color={health.color} />
                                    </div>
                                </Section>
                            )}

                            {(server.egg || server.plan) && (
                                <Section label={t('servers.list.config', 'Configuration')}>
                                    <dl className="space-y-2 text-sm">
                                        {server.egg && (
                                            <Row label={t('servers.list.egg', 'Egg')} value={server.egg.name} />
                                        )}
                                        {server.plan && (
                                            <Row label={t('servers.list.plan', 'Plan')} value={server.plan.name} />
                                        )}
                                    </dl>
                                </Section>
                            )}
                        </div>

                        <div className="flex items-center justify-between gap-3 border-t border-[var(--color-border)] bg-[var(--color-surface-elevated)] px-5 py-4">
                            {cardConfig.show_quick_actions && !isInactive ? (
                                <ServerCardPowerButtons
                                    serverId={server.id}
                                    isRunning={isRunning}
                                    isStopped={isStopped}
                                    isPowerPending={isPowerPending}
                                    onPower={onPower}
                                    layout="compact"
                                />
                            ) : (
                                <span />
                            )}
                            <button
                                type="button"
                                onClick={() => {
                                    onClose();
                                    navigate(`/servers/${server.id}`);
                                }}
                                className="rounded-[var(--radius-md)] bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)] cursor-pointer transition-colors"
                            >
                                {t('servers.list.open', 'Open server')}
                                <span aria-hidden className="ml-2">→</span>
                            </button>
                        </div>
                    </m.aside>
                </>
            )}
        </AnimatePresence>
    );

    if (typeof document === 'undefined') return null;
    return createPortal(drawer, document.body);
}

function DrawerHeader({
    server,
    healthColor,
    stateLabel,
    isAlive,
    onClose,
}: {
    server: Server;
    healthColor: string;
    stateLabel: string;
    isAlive: boolean;
    onClose: () => void;
}) {
    const banner = server.egg?.banner_image ?? null;
    return (
        <div className="relative h-32 w-full overflow-hidden">
            {banner ? (
                <img src={banner} alt="" className="absolute inset-0 h-full w-full object-cover" aria-hidden />
            ) : (
                <div className="absolute inset-0" style={{ background: `linear-gradient(135deg, ${healthColor}66, var(--color-surface-elevated))` }} />
            )}
            <div className="absolute inset-0" style={{ background: 'linear-gradient(180deg, rgba(0,0,0,0.1), rgba(0,0,0,0.7))' }} />

            <button
                type="button"
                onClick={onClose}
                aria-label="Close"
                className="absolute top-3 right-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white/80 backdrop-blur-sm hover:bg-black/60 hover:text-white cursor-pointer transition-colors"
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div className="absolute inset-x-0 bottom-0 flex items-end gap-3 px-5 pb-4">
                <span
                    className={`mb-1 h-2.5 w-2.5 rounded-full ring-2 ring-black/40 ${isAlive ? 'animate-pulse' : ''}`}
                    style={{ background: healthColor }}
                    aria-hidden
                />
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-xl font-bold text-white drop-shadow">{server.name}</h2>
                    <span
                        className="mt-1 inline-block rounded-[var(--radius-full)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white"
                        style={{ background: `${healthColor}99` }}
                    >
                        {stateLabel}
                    </span>
                </div>
            </div>
        </div>
    );
}

function Section({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <section className="mt-5">
            <h3 className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">
                {label}
            </h3>
            {children}
        </section>
    );
}

function Metric({ label, value, pct, color }: { label: string; value: string; pct: number | null; color: string }) {
    return (
        <div className="rounded-[var(--radius-md)] border border-[var(--color-border)]/60 bg-[var(--color-background)]/40 px-3 py-2.5">
            <div className="flex items-baseline justify-between">
                <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-muted)]">{label}</span>
                <span className="font-mono text-sm tabular-nums text-[var(--color-text-primary)]">{value}</span>
            </div>
            {pct !== null && (
                <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-[var(--color-border)]/40">
                    <div className="h-full transition-all duration-500" style={{ width: `${pct}%`, background: color, opacity: 0.85 }} />
                </div>
            )}
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-xs text-[var(--color-text-muted)]">{label}</dt>
            <dd className="truncate text-sm text-[var(--color-text-primary)]">{value}</dd>
        </div>
    );
}
