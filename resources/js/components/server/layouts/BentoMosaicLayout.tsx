import { memo, useEffect, useState } from 'react';
import { BentoTile, type BentoTileSize } from '@/components/server/layouts/BentoTile';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';

// Featured (2×2) on mobile = full width × 320px = scroll-killer. Force every
// tile to "standard" (1×1) below the sm breakpoint. SSR-safe initial value.
function useIsMobile(): boolean {
    const [isMobile, setIsMobile] = useState<boolean>(
        () => typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches,
    );
    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) return;
        const mq = window.matchMedia('(max-width: 639px)');
        const handler = (e: MediaQueryListEvent) => setIsMobile(e.matches);
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, []);
    return isMobile;
}

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
 * Asymmetric magazine-style grid. Every tile, regardless of size, surfaces
 * the server vitals: a CPU progress ring around the status dot (top-left)
 * and a thin RAM bar across the bottom edge. Featured (2×2) and wide (2×1)
 * tiles add full stats + bigger name typography; standard (1×1) tiles stay
 * informative thanks to the ring + bar duo without crowding.
 *
 * The actual tile renderer lives in `BentoTile.tsx` to keep both files
 * under the project's 300-line cap.
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
    const isMobile = useIsMobile();
    return (
        <div
            className="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 auto-rows-[160px]"
            style={{ gridAutoFlow: 'dense' }}
        >
            {servers.map((server, idx) => (
                <BentoTile
                    key={server.id}
                    server={server}
                    stats={statsMap?.[server.id]}
                    cardConfig={cardConfig}
                    size={pickTileSize(idx, servers.length, isMobile)}
                    isSelectionMode={isSelectionMode}
                    isSelected={isSelected(server.id)}
                    onSelect={onSelect}
                    onPower={onPower}
                    isPowerPending={isPowerPending}
                />
            ))}
        </div>
    );
}

/**
 * Tile size rotation: 1 featured (2×2) every 7 servers + 1 wide (2×1)
 * every 4. Tiny lists (≤3 servers) stay uniform — featuring one tile in
 * a 3-server dashboard looks lopsided. The picker is deterministic by
 * index so reordering doesn't reshuffle every visible tile.
 *
 * On mobile (<sm) every tile is forced to "standard" — a 2×2 featured
 * tile would be ~100vw × 320px and turn the dashboard into endless scroll.
 */
function pickTileSize(index: number, total: number, isMobile: boolean): BentoTileSize {
    if (isMobile) return 'standard';
    if (total > 3 && index % 7 === 0) return 'featured';
    if (index % 4 === 2) return 'wide';
    return 'standard';
}

export const BentoMosaicLayout = memo(BentoMosaicLayoutImpl);
