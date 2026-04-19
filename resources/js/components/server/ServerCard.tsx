import { useCallback, useRef, useState } from 'react';
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

function AnimStat({ icon, value, formatter }: {
    icon: React.ReactNode; value: number; formatter: (v: number) => string;
}) {
    const animated = useCountUp(value);
    return (
        <span className="flex items-center gap-1 text-white/70 text-xs">
            {icon} {formatter(animated)}
        </span>
    );
}

const CpuIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>;
const RamIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>;
const DiskIcon = <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2zm16 7H4m13 2h.01" /></svg>;

export function ServerCard({
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

    const state = (stats?.state ?? server.status) as
        'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';
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
                'hover:border-[var(--color-border-hover)] hover:shadow-[0_8px_40px_rgba(0,0,0,0.4),var(--shadow-glow)]',
                isDragging && 'opacity-50',
                isSelected && 'ring-2 ring-[var(--color-primary)] ring-offset-1 ring-offset-[var(--color-background)]',
            )}
        >
            {/* Full background image */}
            {hasBanner && (
                <>
                    <img
                        src={server.egg?.banner_image ?? undefined}
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                    />
                    {/* Gradient overlay — dark from right to left so text is readable */}
                    <div className="absolute inset-0" style={{
                        background: 'linear-gradient(to right, rgba(12,10,20,0.55) 0%, rgba(12,10,20,0.75) 35%, rgba(12,10,20,0.92) 65%, rgba(12,10,20,0.97) 100%)',
                    }} />
                </>
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
                                background: copied ? 'rgba(var(--color-success-rgb), 0.2)' : 'rgba(255,255,255,0.08)',
                                color: copied ? 'var(--color-success)' : hasBanner ? 'rgba(255,255,255,0.7)' : 'var(--color-text-secondary)',
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

                    {cardConfig.show_stats_bars && stats && (
                        <div className="flex items-center gap-3">
                            <AnimStat icon={CpuIcon} value={stats.cpu} formatter={formatCpu} />
                            <AnimStat icon={RamIcon} value={stats.memory_bytes} formatter={formatBytes} />
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

                    {cardConfig.show_quick_actions && (
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
                            : 'border-white/30 bg-black/40 backdrop-blur-sm',
                    )}>
                    {isSelected && (
                        <svg className="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
                    )}
                </button>
            )}
        </m.div>
    );
}
