import { memo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { formatBytes, formatCpu } from '@/utils/format';
import { fetchServer } from '@/services/serverApi';
import { ServerCardPowerButtons } from '@/components/server/ServerCardPowerButtons';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStats, ServerStatsMap } from '@/types/ServerStats';

type TileSize = 'featured' | 'wide' | 'standard';

interface BentoMosaicLayoutProps {
    servers: Server[];
    statsMap: ServerStatsMap | undefined;
    cardConfig: CardConfig;
    isSelectionMode: boolean;
    isSelected: (id: number) => boolean;
    onSelect: (id: number) => void;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
}

/**
 * Asymmetric magazine-style grid. Picks one "featured" tile (2×2) per
 * pack of ~6 servers, scatters wider 2×1 tiles to break the rhythm,
 * and fills the rest with 1×1 squares. Tiles use the egg banner as a
 * background when available — when absent we fall back to a status-
 * tinted gradient so the layout never looks empty.
 *
 * Categories from useDashboardLayout are intentionally ignored — the
 * point of Bento is a single rhythmic mosaic.
 */
function BentoMosaicLayoutImpl({
    servers,
    statsMap,
    cardConfig,
    isSelectionMode,
    isSelected,
    onSelect,
    onPower,
    isPowerPending,
}: BentoMosaicLayoutProps) {
    return (
        <div
            className="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 auto-rows-[150px]"
            style={{ gridAutoFlow: 'dense' }}
        >
            {servers.map((server, idx) => {
                const size = pickTileSize(idx, servers.length);
                return (
                    <BentoTile
                        key={server.id}
                        server={server}
                        stats={statsMap?.[server.id]}
                        cardConfig={cardConfig}
                        size={size}
                        isSelectionMode={isSelectionMode}
                        isSelected={isSelected(server.id)}
                        onSelect={onSelect}
                        onPower={onPower}
                        isPowerPending={isPowerPending}
                    />
                );
            })}
        </div>
    );
}

function pickTileSize(index: number, total: number): TileSize {
    // Featured slot: every 7 tiles. Skip for very small lists (≤3 servers
    // would otherwise look lopsided — keep all tiles equal-sized).
    if (total > 3 && index % 7 === 0) return 'featured';
    // Wide accent every 4th tile (excluding featured slots).
    if (index % 4 === 2) return 'wide';
    return 'standard';
}

interface BentoTileProps {
    server: Server;
    stats: ServerStats | undefined;
    cardConfig: CardConfig;
    size: TileSize;
    isSelectionMode: boolean;
    isSelected: boolean;
    onSelect: (id: number) => void;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
}

function BentoTileImpl({
    server,
    stats,
    cardConfig,
    size,
    isSelectionMode,
    isSelected,
    onSelect,
    onPower,
    isPowerPending,
}: BentoTileProps) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const queryClient = useQueryClient();

    const lifecycleStatus =
        server.status === 'suspended' ||
        server.status === 'provisioning' ||
        server.status === 'provisioning_failed' ||
        server.status === 'terminated'
            ? server.status
            : null;
    const state = (lifecycleStatus ?? stats?.state ?? server.status) as string;
    const health = resolveHealthColor(state);
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const isInactive = state === 'suspended' || state === 'provisioning' || state === 'provisioning_failed';
    const banner = server.egg?.banner_image ?? null;

    const handlePrefetch = useCallback(() => {
        void queryClient.prefetchQuery({
            queryKey: ['servers', server.id],
            queryFn: () => fetchServer(server.id),
            staleTime: 120_000,
        });
    }, [queryClient, server.id]);

    const handleSelect = (e: React.MouseEvent) => {
        e.stopPropagation();
        onSelect(server.id);
    };

    const span = sizeToSpan(size);
    const isFeatured = size === 'featured';
    const isWide = size === 'wide';

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onKeyDown={(e) => {
                if (e.key === 'Enter') navigate(`/servers/${server.id}`);
            }}
            onMouseEnter={handlePrefetch}
            className={clsx(
                'group relative overflow-hidden rounded-[var(--radius-xl)] cursor-pointer outline-none',
                'transition-all duration-300 hover:-translate-y-0.5',
                'focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                span,
            )}
            style={{
                background: banner ? '#000' : `linear-gradient(135deg, ${health.color}33 0%, var(--color-surface) 100%)`,
            }}
        >
            {banner && (
                <img
                    src={banner}
                    alt=""
                    aria-hidden
                    className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                    style={{ opacity: isInactive ? 0.35 : 0.7 }}
                />
            )}

            <div
                className="absolute inset-0"
                style={{
                    background: isFeatured
                        ? 'linear-gradient(180deg, rgba(0,0,0,0) 30%, rgba(0,0,0,0.7) 100%)'
                        : 'linear-gradient(180deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.55) 100%)',
                }}
            />

            <span
                aria-hidden
                className={clsx(
                    'absolute top-3 left-3 h-2.5 w-2.5 rounded-full ring-2 ring-black/30',
                    isRunning && 'animate-pulse',
                )}
                style={{ background: health.color }}
            />

            {isSelectionMode && (
                <button
                    type="button"
                    onClick={handleSelect}
                    aria-label={isSelected ? 'Deselect' : 'Select'}
                    className={clsx(
                        'absolute top-3 right-3 z-20 flex h-6 w-6 items-center justify-center rounded border cursor-pointer',
                        isSelected
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]'
                            : 'border-white/40 bg-black/40 backdrop-blur-sm',
                    )}
                >
                    {isSelected && (
                        <svg className="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    )}
                </button>
            )}

            <div className="relative z-10 flex h-full flex-col justify-end p-3 sm:p-4">
                <div
                    className={clsx(
                        'font-bold leading-tight tracking-tight text-white drop-shadow',
                        isFeatured ? 'text-2xl sm:text-3xl' : isWide ? 'text-lg sm:text-xl' : 'text-sm sm:text-base',
                    )}
                >
                    <span className="line-clamp-2">{server.name}</span>
                </div>

                {(cardConfig.show_egg_name || cardConfig.show_plan_name) && (
                    <div
                        className={clsx(
                            'mt-1 truncate font-medium uppercase tracking-wider text-white/70',
                            isFeatured ? 'text-xs' : 'text-[10px]',
                        )}
                    >
                        {cardConfig.show_egg_name && server.egg?.name}
                        {cardConfig.show_egg_name && cardConfig.show_plan_name && server.plan && ' · '}
                        {cardConfig.show_plan_name && server.plan?.name}
                    </div>
                )}

                {(isFeatured || isWide) && cardConfig.show_stats_bars && stats && !isInactive && (
                    <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-white/80">
                        <span className="flex items-center gap-1.5 font-mono">
                            <span className="text-[10px] uppercase tracking-wider opacity-60">CPU</span>
                            {formatCpu(stats.cpu)}
                        </span>
                        <span className="flex items-center gap-1.5 font-mono">
                            <span className="text-[10px] uppercase tracking-wider opacity-60">RAM</span>
                            {formatBytes(stats.memory_bytes)}
                        </span>
                    </div>
                )}

                {isInactive && (
                    <span
                        className="mt-2 self-start rounded-[var(--radius-full)] px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider"
                        style={{ background: `${health.color}33`, color: health.color }}
                    >
                        {t(`servers.status.${state}`, state)}
                    </span>
                )}

                {cardConfig.show_quick_actions && !isInactive && (
                    <div
                        className="mt-2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity self-start"
                        onClick={(e) => e.stopPropagation()}
                        onKeyDown={(e) => e.stopPropagation()}
                        role="presentation"
                    >
                        <ServerCardPowerButtons
                            serverId={server.id}
                            isRunning={isRunning}
                            isStopped={isStopped}
                            isPowerPending={isPowerPending}
                            onPower={onPower}
                            layout="icon-only"
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

function sizeToSpan(size: TileSize): string {
    switch (size) {
        case 'featured':
            return 'col-span-2 row-span-2 sm:col-span-2 lg:col-span-2';
        case 'wide':
            return 'col-span-2 row-span-1';
        case 'standard':
        default:
            return 'col-span-1 row-span-1';
    }
}

const BentoTile = memo(BentoTileImpl);

export const BentoMosaicLayout = memo(BentoMosaicLayoutImpl);
