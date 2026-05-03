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
    // Color intensity bumps with CPU on a running tile — gives a heatmap
    // feel where high-load servers visibly glow brighter.
    const intensity = health.isAlive ? 0.45 + (cpuPct / 100) * 0.5 : 0.3;

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
                'hover:scale-[1.06] hover:z-10 hover:shadow-[0_0_20px_var(--color-primary-glow)]',
                'focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                isActive && 'ring-2 ring-[var(--color-primary)] z-10',
                isSelected && 'ring-2 ring-[var(--color-primary)]',
                health.isAlive && 'pulse-alive',
            )}
            style={{
                background: `linear-gradient(135deg, ${health.color} 0%, color-mix(in srgb, ${health.color} 60%, transparent) 100%)`,
                opacity: intensity,
            }}
        >
            <span className="absolute inset-x-0 bottom-0 px-1.5 py-1 text-[10px] font-medium leading-tight text-white/90 truncate text-left bg-black/30 backdrop-blur-sm">
                {server.name}
            </span>
            {isSelected && (
                <span className="absolute top-1.5 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--color-primary)]">
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
