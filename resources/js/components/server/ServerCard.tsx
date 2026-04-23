import { memo, useCallback, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { copyToClipboard } from '@/utils/clipboard';
import { m } from 'motion/react';
import clsx from 'clsx';
import { StatusDot } from '@/components/ui/StatusDot';
import { formatBytes, formatCpu, formatUptime } from '@/utils/format';
import { useCardConfig } from '@/hooks/useCardConfig';
import { useCountUp } from '@/hooks/useCountUp';
import { fetchServer } from '@/services/serverApi';
import type { ServerCardProps } from '@/components/server/ServerCard.props';
import { ServerCardPowerButtons } from '@/components/server/ServerCardPowerButtons';

function AnimStat({ icon, value, formatter, animate }: {
    icon: React.ReactNode; value: number; formatter: (v: number) => string; animate: boolean;
}) {
    const animated = useCountUp(value, { enabled: animate });
    return (
        <span className="flex items-center gap-1 text-white/70 text-xs">
            {icon} {formatter(animated)}
        </span>
    );
}

const CpuIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>;
const RamIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>;
const DiskIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2zm16 7H4m13 2h.01" /></svg>;

function ServerCardImpl({
    server, stats, onPower, isPowerPending,
    isSelectable = false, isSelected = false, onSelect, isDragging = false,
}: ServerCardProps) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const queryClient = useQueryClient();
    const [copied, setCopied] = useState(false);
    const cardConfig = useCardConfig();
    const cardRef = useRef<HTMLDivElement>(null);
    const [spotlightPos, setSpotlightPos] = useState({ x: 0, y: 0 });
    const hasBanner = cardConfig.show_egg_icon && !!server.egg?.banner_image;

    const handlePrefetch = useCallback(() => {
        void queryClient.prefetchQuery({
            queryKey: ['servers', server.id],
            queryFn: () => fetchServer(server.id),
            staleTime: 120_000,
        });
    }, [queryClient, server.id]);

    // `server.status` from the DB always wins for terminal lifecycle states
    // (suspended / provisioning / terminated) — Wings stats may be stale or
    // missing entirely while the row is in those states. Only fall back to
    // the live Wings runtime state for running/stopped/offline.
    const lifecycleStatus = server.status === 'suspended' || server.status === 'provisioning' || server.status === 'provisioning_failed' || server.status === 'terminated'
        ? server.status
        : null;
    const state = (lifecycleStatus ?? stats?.state ?? server.status) as
        'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting' | 'provisioning' | 'provisioning_failed';
    const isSuspended = state === 'suspended';
    const isProvisioning = state === 'provisioning' || state === 'provisioning_failed';
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const handleMouseMove = useCallback((e: React.MouseEvent) => {
        if (!cardRef.current) return;
        const rect = cardRef.current.getBoundingClientRect();
        setSpotlightPos({ x: e.clientX - rect.left, y: e.clientY - rect.top });
    }, []);

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!address) return;
        void copyToClipboard(address).then(() => { setCopied(true); setTimeout(() => setCopied(false), 1500); });
    };

    const handleSelect = (e: React.MouseEvent) => { e.stopPropagation(); onSelect?.(server.id); };

    return (
        <m.div
            ref={cardRef}
            layout
            role="button" tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onMouseEnter={handlePrefetch}
            onKeyDown={(e) => { if (e.key === 'Enter') navigate(`/servers/${server.id}`); }}
            onMouseMove={handleMouseMove}
            className={clsx(
                'group relative min-h-[8rem] sm:h-36 cursor-pointer overflow-hidden border-glow rounded-[var(--radius-lg)]',
                !hasBanner && 'bg-[var(--color-surface)] border border-[var(--color-border)]',
                hasBanner && 'border border-transparent',
                'transition-[box-shadow,border-color] duration-300',
                'hover:border-[var(--color-border-hover)] hover:shadow-[var(--shadow-lg),var(--shadow-glow)]',
                isDragging && 'opacity-50',
                isSelected && 'ring-2 ring-[var(--color-primary)] ring-offset-1 ring-offset-[var(--color-background)]',
            )}
            style={
                isSuspended
                    ? { borderLeft: '3px solid var(--color-suspended)' }
                    : isProvisioning
                        ? { borderLeft: '3px solid var(--color-installing)' }
                        : undefined
            }
        >
            {/* Full background image */}
            {hasBanner && (
                <>
                    <img
                        src={server.egg?.banner_image ?? undefined}
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                    />
                    {/* Gradient overlay — base color follows the mode so text stays readable on both dark and light UIs. */}
                    <div className="absolute inset-0" style={{
                        background: 'linear-gradient(to right, var(--banner-overlay-soft) 0%, var(--banner-overlay) 65%, var(--banner-overlay) 100%)',
                    }} />
                </>
            )}

            {/* Discreet lifecycle pill — small, top-right corner. Theme-color
                driven so admins can match their brand. The card otherwise
                stays untouched (no grayscale, no big ring) — the user wants
                "subtle but visible". */}
            {isSuspended && (
                <div
                    className="absolute right-2 top-2 z-30 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider"
                    style={{
                        background: 'rgba(var(--color-suspended-rgb), 0.18)',
                        color: 'var(--color-suspended)',
                        border: '1px solid rgba(var(--color-suspended-rgb), 0.35)',
                        backdropFilter: 'blur(4px)',
                    }}
                    title={t('servers.status.suspended')}
                >
                    <svg className="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <rect x="5" y="11" width="14" height="10" rx="2" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 118 0v4" />
                    </svg>
                    {t('servers.status.suspended')}
                </div>
            )}
            {isProvisioning && (
                <div
                    className="absolute right-2 top-2 z-30 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider"
                    style={{
                        background: 'rgba(var(--color-installing-rgb), 0.18)',
                        color: 'var(--color-installing)',
                        border: '1px solid rgba(var(--color-installing-rgb), 0.35)',
                        backdropFilter: 'blur(4px)',
                    }}
                    title={t('servers.status.provisioning')}
                >
                    <svg className="h-2.5 w-2.5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                    </svg>
                    {t('servers.status.provisioning')}
                </div>
            )}

            {/* Spotlight */}
            <div className="card-spotlight" style={{
                background: `radial-gradient(circle 250px at ${spotlightPos.x}px ${spotlightPos.y}px, rgba(var(--color-primary-rgb), 0.06), transparent)`,
            }} />

            {/* Content — overlays the background image */}
            <div className="relative z-10 flex h-full min-w-0 flex-col justify-center gap-1 sm:gap-1.5 p-3 sm:p-4 md:p-5">
                {/* Row 1: status + name */}
                <div className="flex items-center gap-2">
                    {cardConfig.show_status_badge && <StatusDot status={state} size="sm" />}
                    <span className={clsx(
                        'truncate text-sm sm:text-base font-bold transition-colors duration-300',
                        hasBanner ? 'text-white group-hover:text-[var(--color-primary)]' : 'text-[var(--color-text-primary)] group-hover:text-[var(--color-primary)]',
                    )}>
                        {server.name}
                    </span>
                </div>

                {/* Row 2: egg + plan */}
                <div className="flex items-center gap-2 flex-wrap">
                    {cardConfig.show_egg_name && server.egg && (
                        <span className={clsx('text-xs', hasBanner ? 'text-white/50' : 'text-[var(--color-text-muted)]')}>{server.egg.name}</span>
                    )}
                    {cardConfig.show_plan_name && server.plan && (
                        <span className={clsx('text-xs', hasBanner ? 'text-white/50' : 'text-[var(--color-text-muted)]')}>
                            {cardConfig.show_egg_name && server.egg ? '·' : ''} {server.plan.name}
                        </span>
                    )}
                </div>

                {/* Row 3: address + stats + power */}
                <div className="flex items-center gap-2 sm:gap-3 flex-wrap mt-0.5">
                    {cardConfig.show_ip_port && address && (
                        <button type="button" onClick={handleCopy}
                            className="inline-flex items-center gap-1 rounded-[var(--radius-full)] px-2 py-0.5 text-[11px] font-mono cursor-pointer transition-all duration-200"
                            style={{
                                background: copied ? 'rgba(var(--color-success-rgb), 0.2)' : hasBanner ? 'rgba(255,255,255,0.15)' : 'var(--surface-overlay-soft)',
                                color: copied ? 'var(--color-success)' : hasBanner ? 'var(--text-on-banner)' : 'var(--color-text-secondary)',
                                backdropFilter: 'blur(8px)',
                            }}>
                            {copied ? (
                                <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>
                            ) : (
                                <svg className="h-3 w-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" /></svg>
                            )}
                            <span>{copied ? t('servers.list.copied') : address}</span>
                        </button>
                    )}

                    {/* Stats + power are hidden when the server is suspended
                        or provisioning : Wings reports nothing useful in those
                        states and any control would be rejected by the daemon. */}
                    {cardConfig.show_stats_bars && stats && !isSuspended && !isProvisioning && (
                        <div className="flex items-center gap-3">
                            <AnimStat icon={CpuIcon} value={stats.cpu} formatter={formatCpu} animate={false} />
                            <AnimStat icon={RamIcon} value={stats.memory_bytes} formatter={formatBytes} animate={false} />
                            <span className="hidden lg:flex items-center gap-1 text-white/70 text-xs">
                                {DiskIcon} {formatBytes(stats.disk_bytes)}
                            </span>
                            {cardConfig.show_uptime && stats.uptime > 0 && (
                                <span className="hidden xl:flex items-center gap-1 text-white/70 text-xs">
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {formatUptime(stats.uptime)}
                                </span>
                            )}
                        </div>
                    )}

                    {cardConfig.show_quick_actions && !isSuspended && !isProvisioning && (
                        /* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */
                        <div className="ml-auto flex-shrink-0" onClick={(e) => e.stopPropagation()}>
                            <ServerCardPowerButtons
                                serverId={server.id} isRunning={isRunning} isStopped={isStopped}
                                isPowerPending={isPowerPending} onPower={onPower}
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Selection checkbox */}
            {isSelectable && (
                <button type="button" onClick={handleSelect}
                    className={clsx(
                        'absolute right-3 top-3 z-20',
                        'flex h-6 w-6 items-center justify-center rounded',
                        'border cursor-pointer transition-all duration-200',
                        isSelected
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)] ring-2 ring-[var(--color-primary-glow)]'
                            : 'border-[var(--color-border-hover)] bg-[var(--modal-scrim)] backdrop-blur-sm',
                    )}>
                    {isSelected && (
                        <svg className="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
                    )}
                </button>
            )}
        </m.div>
    );
}

/**
 * Memoized export — large dashboards (50+ servers) re-render cards on every
 * stats poll, search keystroke or selection toggle otherwise. The shallow
 * compare here trusts that the parent passes :
 *  - `server` : stable reference per id (TanStack Query cache)
 *  - `stats`  : stable per-server reference (same statsMap object across polls)
 *  - `onPower` / `onSelect` : stable callbacks (`useCallback` in DashboardPage)
 *  - everything else : primitives
 */
export const ServerCard = memo(ServerCardImpl, (prev, next) => {
    return prev.server === next.server
        && prev.stats === next.stats
        && prev.isPowerPending === next.isPowerPending
        && prev.isSelectable === next.isSelectable
        && prev.isSelected === next.isSelected
        && prev.isDragging === next.isDragging
        && prev.onPower === next.onPower
        && prev.onSelect === next.onSelect;
});
