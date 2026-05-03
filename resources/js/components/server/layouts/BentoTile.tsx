import { memo, useCallback, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { copyToClipboard } from '@/utils/clipboard';
import { formatBytes, formatCpu, formatUptime } from '@/utils/format';
import { fetchServer } from '@/services/serverApi';
import { ServerCardPowerButtons } from '@/components/server/ServerCardPowerButtons';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import { BentoStatusRing } from '@/components/server/layouts/BentoStatusRing';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStats } from '@/types/ServerStats';

export type BentoTileSize = 'featured' | 'wide' | 'standard';

interface BentoTileProps {
    server: Server;
    stats: ServerStats | undefined;
    cardConfig: CardConfig;
    size: BentoTileSize;
    isSelectionMode: boolean;
    isSelected: boolean;
    onSelect: (id: number) => void;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
}

function BentoTileImpl({
    server, stats, cardConfig, size, isSelectionMode, isSelected, onSelect, onPower, isPowerPending,
}: BentoTileProps) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const queryClient = useQueryClient();
    const [copied, setCopied] = useState(false);

    const lifecycleStatus = (server.status === 'suspended' || server.status === 'provisioning' ||
        server.status === 'provisioning_failed' || server.status === 'terminated') ? server.status : null;
    const state = (lifecycleStatus ?? stats?.state ?? server.status) as string;
    const health = resolveHealthColor(state);
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const isInactive = state === 'suspended' || state === 'provisioning' || state === 'provisioning_failed';
    const banner = server.egg?.banner_image ?? null;

    const cpuPct = Math.min(100, stats?.cpu ?? 0);
    const ramLimitMb = server.plan?.ram ?? server.limits?.memory ?? 0;
    const ramUsedMb = stats ? stats.memory_bytes / (1024 * 1024) : 0;
    const ramPct = ramLimitMb > 0 ? Math.min(100, (ramUsedMb / ramLimitMb) * 100) : 0;
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const handlePrefetch = useCallback(() => {
        void queryClient.prefetchQuery({
            queryKey: ['servers', server.id], queryFn: () => fetchServer(server.id), staleTime: 120_000,
        });
    }, [queryClient, server.id]);
    const handleSelect = (e: React.MouseEvent) => { e.stopPropagation(); onSelect(server.id); };
    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!address) return;
        void copyToClipboard(address).then(() => { setCopied(true); setTimeout(() => setCopied(false), 1500); });
    };

    const span = sizeToSpan(size);
    const isFeatured = size === 'featured';
    const isWide = size === 'wide';
    const ringSize = isFeatured ? 44 : isWide ? 36 : 28;

    return (
        <div
            role="button" tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onKeyDown={(e) => { if (e.key === 'Enter') navigate(`/servers/${server.id}`); }}
            onMouseEnter={handlePrefetch}
            className={clsx(
                'group relative overflow-hidden rounded-[var(--radius-xl)] cursor-pointer outline-none',
                'transition-all duration-300 hover:-translate-y-0.5 hover:shadow-2xl',
                'focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                isSelected && 'ring-2 ring-[var(--color-primary)]',
                span,
            )}
            style={{
                background: banner ? '#0a0a0a'
                    : `linear-gradient(135deg, color-mix(in srgb, ${health.color} 22%, var(--color-surface-elevated)) 0%, var(--color-surface) 100%)`,
            }}
        >
            {banner && (
                <img src={banner} alt="" aria-hidden
                    className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                    style={{ opacity: isInactive ? 0.25 : 0.55 }}
                />
            )}
            <div className="absolute inset-0" style={{
                background: isFeatured
                    ? 'linear-gradient(180deg, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0) 35%, rgba(0,0,0,0.85) 100%)'
                    : 'linear-gradient(180deg, rgba(0,0,0,0.25) 0%, rgba(0,0,0,0.1) 30%, rgba(0,0,0,0.78) 100%)',
            }} />

            <div className="absolute top-2.5 left-2.5 z-10">
                <BentoStatusRing pct={cpuPct} color={health.color} size={ringSize} isAlive={isRunning} isInactive={isInactive} />
            </div>

            {(isFeatured || isWide) && stats && !isInactive && (
                <span className="absolute top-3 right-3 z-10 rounded-[var(--radius-full)] bg-black/45 px-2 py-0.5 font-mono text-[11px] tabular-nums text-white/95 backdrop-blur-sm">
                    {formatCpu(stats.cpu)}
                </span>
            )}
            {isInactive && (
                <span className="absolute top-3 right-3 z-10 rounded-[var(--radius-full)] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                    style={{ background: `${health.color}D9`, color: 'white' }}>
                    {t(`servers.status.${state}`, state)}
                </span>
            )}

            {isSelectionMode && (
                <button type="button" onClick={handleSelect} aria-label={isSelected ? 'Deselect' : 'Select'}
                    className={clsx('absolute z-20 flex h-6 w-6 items-center justify-center rounded border cursor-pointer',
                        isFeatured || isWide ? 'top-3 right-14' : 'top-2 right-2',
                        isSelected ? 'border-[var(--color-primary)] bg-[var(--color-primary)]' : 'border-white/40 bg-black/40 backdrop-blur-sm',
                    )}>
                    {isSelected && (
                        <svg className="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>
                    )}
                </button>
            )}

            <div className={clsx('relative z-10 flex h-full flex-col justify-end', isFeatured ? 'p-4 sm:p-5' : 'p-3')}>
                <div className={clsx('font-bold leading-tight tracking-tight text-white drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)]',
                    isFeatured ? 'text-2xl sm:text-3xl' : isWide ? 'text-base sm:text-lg' : 'text-sm')}>
                    <span className="line-clamp-2">{server.name}</span>
                </div>

                {(cardConfig.show_egg_name || cardConfig.show_plan_name) && (
                    <div className={clsx('mt-1 truncate font-medium uppercase tracking-wider text-white/70',
                        isFeatured ? 'text-xs' : 'text-[10px]')}>
                        {cardConfig.show_egg_name && server.egg?.name}
                        {cardConfig.show_egg_name && cardConfig.show_plan_name && server.plan && ' · '}
                        {cardConfig.show_plan_name && server.plan?.name}
                    </div>
                )}

                {(isFeatured || isWide) && cardConfig.show_stats_bars && stats && !isInactive && (
                    <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-white/85">
                        <Stat label="RAM" value={formatBytes(stats.memory_bytes)} />
                        <Stat label="DISK" value={formatBytes(stats.disk_bytes)} />
                        {cardConfig.show_uptime && stats.uptime > 0 && (
                            <Stat label="UP" value={formatUptime(stats.uptime)} />
                        )}
                    </div>
                )}

                {/* Featured tile only — inline action row at the bottom of
                    the info zone. Has plenty of horizontal room to fit
                    IP pill + power buttons without overflowing. */}
                {isFeatured && (
                    <div className="mt-3 flex flex-wrap items-center gap-2"
                        onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()} role="presentation">
                        {cardConfig.show_ip_port && address && (
                            <button type="button" onClick={handleCopy}
                                className="inline-flex items-center gap-1.5 rounded-[var(--radius-full)] bg-black/45 px-2.5 py-1 font-mono text-[10px] text-white/95 hover:bg-black/65 backdrop-blur-sm cursor-pointer">
                                <svg className="h-2.5 w-2.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <rect x="9" y="9" width="13" height="13" rx="2" />
                                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                                </svg>
                                {copied ? t('servers.list.copied') : address}
                            </button>
                        )}
                        {cardConfig.show_quick_actions && !isInactive && (
                            <ServerCardPowerButtons serverId={server.id} isRunning={isRunning} isStopped={isStopped}
                                isPowerPending={isPowerPending} onPower={onPower} layout="icon-only" />
                        )}
                    </div>
                )}
            </div>

            {/* Wide & standard tiles — power buttons floated absolute in
                the bottom-right corner so they don't reflow the layout
                or overflow the tile. IP pill is omitted on these sizes
                because the IP string ("123.45.67.89:25565") is too long
                to fit alongside the buttons in a 1×1 (170 px) tile. */}
            {!isFeatured && cardConfig.show_quick_actions && !isInactive && (
                <div className="absolute bottom-2 right-2 z-20 max-w-[calc(100%-1rem)] opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity"
                    onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()} role="presentation">
                    <div className="rounded-[var(--radius-full)] bg-black/55 px-1 py-1 backdrop-blur-md">
                        <ServerCardPowerButtons serverId={server.id} isRunning={isRunning} isStopped={isStopped}
                            isPowerPending={isPowerPending} onPower={onPower} layout="icon-only" />
                    </div>
                </div>
            )}

            {/* Wide tile — keep IP pill as a floating chip top-right when
                the admin enabled show_ip_port. Standard tiles stay clean
                (use the drawer / detail page to copy the IP). */}
            {isWide && cardConfig.show_ip_port && address && (
                <button type="button" onClick={handleCopy}
                    className="absolute top-2 right-2 z-20 inline-flex items-center gap-1 rounded-[var(--radius-full)] bg-black/55 px-2 py-0.5 font-mono text-[10px] text-white/95 hover:bg-black/75 backdrop-blur-md cursor-pointer max-w-[calc(50%-0.5rem)] truncate opacity-0 group-hover:opacity-100 transition-opacity">
                    {copied ? t('servers.list.copied') : address}
                </button>
            )}

            {stats && !isInactive && ramLimitMb > 0 && (
                <div className="absolute inset-x-0 bottom-0 h-[3px] z-10 bg-black/40">
                    <div className="h-full transition-all duration-500" style={{
                        width: `${ramPct}%`,
                        background: ramPct > 85 ? 'var(--color-danger)' : ramPct > 65 ? 'var(--color-warning)' : health.color,
                        boxShadow: `0 0 8px ${health.color}80`,
                    }} />
                </div>
            )}
        </div>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <span className="flex items-center gap-1.5 font-mono">
            <span className="text-[9px] font-semibold uppercase tracking-wider opacity-60">{label}</span>
            <span className="tabular-nums">{value}</span>
        </span>
    );
}

function sizeToSpan(size: BentoTileSize): string {
    switch (size) {
        case 'featured': return 'col-span-2 row-span-2';
        case 'wide': return 'col-span-2 row-span-1';
        case 'standard':
        default: return 'col-span-1 row-span-1';
    }
}

export const BentoTile = memo(BentoTileImpl);
