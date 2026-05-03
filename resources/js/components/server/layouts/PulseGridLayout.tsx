import { memo, useCallback, useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { fetchServer } from '@/services/serverApi';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import { PulseDetailDrawer } from '@/components/server/layouts/PulseDetailDrawer';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';

interface PulseGridLayoutProps {
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
 * Server-farm heatmap. Each server is a single 88-px square coloured by
 * runtime state — green for running (with live pulse animation), grey
 * for stopped, amber for suspended, red for failed/terminated. Designed
 * for hosters with hundreds of servers: shows the whole fleet on one
 * screen and surfaces failure clusters at a glance.
 *
 * Click a tile → opens a right-edge slide-in drawer with full stats
 * and quick actions, without losing your place in the grid.
 */
function PulseGridLayoutImpl({
    servers,
    statsMap,
    cardConfig,
    isSelectionMode,
    isSelected,
    onSelect,
    onPower,
    isPowerPending,
}: PulseGridLayoutProps) {
    const [activeServerId, setActiveServerId] = useState<number | null>(null);
    const activeServer = activeServerId !== null
        ? servers.find((s) => s.id === activeServerId) ?? null
        : null;

    useEffect(() => {
        if (activeServerId === null) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setActiveServerId(null);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [activeServerId]);

    return (
        <>
            <div
                className="grid gap-2"
                style={{
                    gridTemplateColumns: 'repeat(auto-fill, minmax(88px, 1fr))',
                }}
            >
                {servers.map((server) => (
                    <PulseTile
                        key={server.id}
                        server={server}
                        statsMap={statsMap}
                        isSelectionMode={isSelectionMode}
                        isSelected={isSelected(server.id)}
                        onSelect={onSelect}
                        onOpenDetail={(id) => setActiveServerId(id)}
                        isActive={server.id === activeServerId}
                    />
                ))}
            </div>

            <PulseDetailDrawer
                server={activeServer}
                stats={activeServer ? statsMap?.[activeServer.id] : undefined}
                cardConfig={cardConfig}
                onClose={() => setActiveServerId(null)}
                onPower={onPower}
                isPowerPending={isPowerPending}
            />
        </>
    );
}

interface PulseTileProps {
    server: Server;
    statsMap: ServerStatsMap | undefined;
    isSelectionMode: boolean;
    isSelected: boolean;
    onSelect: (id: number) => void;
    onOpenDetail: (id: number) => void;
    isActive: boolean;
}

function PulseTileImpl({
    server,
    statsMap,
    isSelectionMode,
    isSelected,
    onSelect,
    onOpenDetail,
    isActive,
}: PulseTileProps) {
    const queryClient = useQueryClient();
    const stats = statsMap?.[server.id];

    const lifecycleStatus =
        server.status === 'suspended' ||
        server.status === 'provisioning' ||
        server.status === 'provisioning_failed' ||
        server.status === 'terminated'
            ? server.status
            : null;
    const state = (lifecycleStatus ?? stats?.state ?? server.status) as string;
    const health = resolveHealthColor(state);

    const cpuPct = Math.min(100, stats?.cpu ?? 0);
    const banner = server.egg?.banner_image ?? null;
    // When a banner is present, keep it clean — the previous mix-blend
    // multiply tinted everything green and made the image unreadable.
    // We only darken inactive tiles so suspended / failed ones still
    // visibly stand out in the heatmap. State is now signalled by the
    // ring border + bottom dot instead of an aggressive colour wash.
    const dimAlpha = banner
        ? (state === 'suspended' || state === 'provisioning' || state === 'provisioning_failed' || state === 'terminated' ? 0.6 : 0)
        : 0;

    const handlePrefetch = useCallback(() => {
        void queryClient.prefetchQuery({
            queryKey: ['servers', server.id],
            queryFn: () => fetchServer(server.id),
            staleTime: 120_000,
        });
    }, [queryClient, server.id]);

    const handleClick = (e: React.MouseEvent) => {
        e.preventDefault();
        if (isSelectionMode) {
            onSelect(server.id);
            return;
        }
        onOpenDetail(server.id);
    };

    return (
        <button
            type="button"
            onClick={handleClick}
            onMouseEnter={handlePrefetch}
            title={`${server.name}${stats ? ` — ${cpuPct.toFixed(0)}% CPU` : ''}`}
            className={clsx(
                'pulse-tile group relative aspect-square overflow-hidden rounded-[var(--radius-md)] cursor-pointer outline-none',
                'transition-all duration-200',
                'hover:scale-[1.06] hover:z-10 hover:shadow-[0_0_24px_var(--color-primary-glow)]',
                'focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                isActive && 'ring-2 ring-[var(--color-primary)] z-10',
                isSelected && 'ring-2 ring-[var(--color-primary)]',
                health.isAlive && 'pulse-alive',
            )}
            style={{
                background: '#0a0a0a',
                // Coloured ring carries the state signal — replaces the old
                // mix-blend tint that turned every banner green/red.
                boxShadow: `inset 0 0 0 2px ${health.color}`,
            }}
        >
            {banner ? (
                <img
                    src={banner}
                    alt=""
                    aria-hidden
                    className="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                    style={{ filter: dimAlpha > 0 ? `brightness(${1 - dimAlpha})` : undefined }}
                />
            ) : (
                <div
                    aria-hidden
                    className="absolute inset-0"
                    style={{
                        background: `linear-gradient(135deg, ${health.color} 0%, color-mix(in srgb, ${health.color} 50%, transparent) 100%)`,
                    }}
                />
            )}

            {/* Bottom name pill with subtle status dot — keeps the banner
                visually clean while still surfacing what state the tile is in. */}
            <span className="absolute inset-x-0 bottom-0 flex items-center gap-1 px-1.5 py-1 text-[10px] font-medium leading-tight text-white text-left bg-gradient-to-t from-black/85 via-black/55 to-transparent">
                <span aria-hidden className="h-1.5 w-1.5 flex-shrink-0 rounded-full" style={{ background: health.color, boxShadow: `0 0 6px ${health.color}` }} />
                <span className="truncate">{server.name}</span>
            </span>

            {/* Top-right CPU pill — only shown when stats are streaming */}
            {stats && health.isAlive && (
                <span className="absolute top-1 right-1 z-10 rounded-[var(--radius-full)] bg-black/55 px-1.5 py-0.5 font-mono text-[9px] tabular-nums text-white/95 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity">
                    {cpuPct.toFixed(0)}%
                </span>
            )}

            {isSelected && (
                <span className="absolute top-1.5 right-1.5 z-10 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--color-primary)]">
                    <svg className="h-2.5 w-2.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                    </svg>
                </span>
            )}
        </button>
    );
}

const PulseTile = memo(PulseTileImpl);

export const PulseGridLayout = memo(PulseGridLayoutImpl);
