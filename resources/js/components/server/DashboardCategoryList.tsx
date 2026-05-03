import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';
import type { useDashboardLayout } from '@/hooks/useDashboardLayout';
import type { usePointerDrag } from '@/hooks/usePointerDrag';
import type { useServerSelection } from '@/hooks/useServerSelection';
import type { useCardConfig } from '@/hooks/useCardConfig';
import { ServerCard } from '@/components/server/ServerCard';
import { ServerGroupHeader } from '@/components/server/ServerGroupHeader';
import { CategoryHeader } from '@/components/server/CategoryHeader';
import { DropZoneIndicator } from '@/components/server/DropZoneIndicator';
import { AddCategoryButton } from '@/components/server/AddCategoryButton';
import { GripIcon } from '@/components/server/GripIcon';

type Layout = ReturnType<typeof useDashboardLayout>;
type Drag = ReturnType<typeof usePointerDrag>;
type Selection = ReturnType<typeof useServerSelection>;
type CardConfig = ReturnType<typeof useCardConfig>;

interface GridProps {
    search: string;
    statsMap: ServerStatsMap | undefined;
    drag: Drag;
    selection: Selection;
    handlePower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
    cardConfig: CardConfig;
    shouldAnimate: boolean;
    cardIndexRef: React.MutableRefObject<number>;
}

interface DashboardCategoryListProps extends GridProps {
    layout: Layout;
}

export function DashboardCategoryList({ layout, ...gridProps }: DashboardCategoryListProps) {
    const { t } = useTranslation();
    const { drag } = gridProps;

    if (layout.hasCustomLayout) {
        return (
            <div className="flex flex-col gap-2 pl-0 sm:pl-6">
                <div ref={drag.getDropZoneRef('category-list')} className="flex flex-col gap-2">
                    <AnimatePresence mode="popLayout">
                        {layout.categories.map((cat, catIdx) => (
                            <div key={cat.id} data-drag-id={`cat-${cat.id}`}>
                                <DropZoneIndicator isVisible={drag.isDragging && drag.activeDropZoneId === 'category-list' && drag.insertIndex === catIdx} />
                                <CategoryHeader
                                    categoryId={cat.id}
                                    name={cat.name}
                                    count={cat.serverIds.length}
                                    dragHandleProps={drag.getDragHandleProps(`cat-${cat.id}`, 'category-list')}
                                    onRename={(name) => layout.renameCategory(cat.id, name)}
                                    onDelete={() => layout.deleteCategory(cat.id)}
                                />
                                <ServerGrid servers={layout.getServersForCategory(cat.id)} zoneId={cat.id} {...gridProps} />
                            </div>
                        ))}
                    </AnimatePresence>
                    <DropZoneIndicator isVisible={drag.isDragging && drag.activeDropZoneId === 'category-list' && drag.insertIndex === layout.categories.length} />
                </div>

                {layout.uncategorizedServers.length > 0 && (
                    <div>
                        <ServerGroupHeader name={t('servers.list.uncategorized')} count={layout.uncategorizedServers.length} />
                        <ServerGrid servers={layout.uncategorizedServers} zoneId="uncategorized" {...gridProps} />
                    </div>
                )}

                <div className="mt-2">
                    <AddCategoryButton onCreate={layout.createCategory} />
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-2 pl-0 sm:pl-6">
            <ServerGrid servers={layout.uncategorizedServers} zoneId="uncategorized" {...gridProps} />
            <div className="mt-2">
                <AddCategoryButton onCreate={layout.createCategory} />
            </div>
        </div>
    );
}

interface ServerGridProps extends GridProps {
    servers: Server[];
    zoneId: string;
}

/**
 * Drop-zone + grid of server cards. Extracted so it can be used for both
 * categories and the uncategorized bucket without duplicating the search
 * filtering + animation stagger logic.
 */
function ServerGrid({
    servers, zoneId, search, statsMap, drag, selection,
    handlePower, isPowerPending, cardConfig, shouldAnimate, cardIndexRef,
}: ServerGridProps) {
    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (term.length === 0) return servers;
        return servers.filter((s) => s.name.toLowerCase().includes(term) || (s.egg?.name ?? '').toLowerCase().includes(term));
    }, [servers, search]);

    // Stagger fan-in is delightful for ≤30 cards, painful beyond — the last
    // card of a 50-server dashboard would otherwise wait 2.5s before
    // appearing. Skip the stagger entirely past 30 items (instant fade-in).
    const useStagger = shouldAnimate && filtered.length <= 30;

    return (
        <div
            ref={drag.getDropZoneRef(zoneId)}
            className="dashboard-cards-grid grid gap-2 sm:gap-3 grid-cols-1 min-h-[48px]"
            style={{
                '--cols-tablet': cardConfig.columns.tablet,
                '--cols-desktop': cardConfig.columns.desktop,
            } as React.CSSProperties}
        >
            <style>{`
                @media (min-width: 640px) { [style*="--cols-tablet"] { grid-template-columns: repeat(var(--cols-tablet), 1fr) !important; } }
                @media (min-width: 1024px) { [style*="--cols-desktop"] { grid-template-columns: repeat(var(--cols-desktop), 1fr) !important; } }
            `}</style>
            {filtered.map((server, i) => {
                const cardIndex = cardIndexRef.current++;
                const staggerDelay = useStagger ? Math.min(cardIndex * 0.04, 0.6) : 0;
                return (
                    <div key={server.id}>
                        <DropZoneIndicator isVisible={drag.isDragging && drag.activeDropZoneId === zoneId && drag.insertIndex === i} />
                        <m.div
                            initial={shouldAnimate ? { opacity: 0, y: 16 } : false}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, transition: { duration: 0.15 } }}
                            transition={shouldAnimate
                                ? { delay: staggerDelay, duration: useStagger ? 0.35 : 0.18, ease: 'easeOut' }
                                : { duration: 0 }
                            }
                        >
                            <div className="relative">
                                <div
                                    className="hidden sm:block absolute left-0 top-1/2 -translate-y-1/2 -translate-x-full pr-2 z-20"
                                    {...drag.getDragHandleProps(String(server.id), zoneId)}
                                >
                                    <GripIcon />
                                </div>
                                <ServerCard
                                    server={server}
                                    stats={statsMap?.[server.id]}
                                    onPower={handlePower}
                                    isPowerPending={isPowerPending}
                                    isSelectable={selection.isSelectionMode}
                                    isSelected={selection.isSelected(server.id)}
                                    onSelect={selection.toggleSelect}
                                    isDragging={drag.isDragging && drag.draggedItemId === String(server.id)}
                                />
                            </div>
                        </m.div>
                    </div>
                );
            })}
            <DropZoneIndicator isVisible={drag.isDragging && drag.activeDropZoneId === zoneId && drag.insertIndex === filtered.length} />
        </div>
    );
}
