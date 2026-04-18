import { useState, useMemo, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useServers } from '@/hooks/useServers';
import { useServerStats } from '@/hooks/useServerStats';
import { usePowerAction } from '@/hooks/usePowerAction';
import { useDashboardLayout } from '@/hooks/useDashboardLayout';
import { usePointerDrag } from '@/hooks/usePointerDrag';
import { useServerSelection } from '@/hooks/useServerSelection';
import { useCardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';
import { ServerSearchBar } from '@/components/server/ServerSearchBar';
import { ServerCard } from '@/components/server/ServerCard';
import { ServerCardSkeleton } from '@/components/server/ServerCardSkeleton';
import { ServerEmptyState } from '@/components/server/ServerEmptyState';
import { ServerGroupHeader } from '@/components/server/ServerGroupHeader';
import { ServerBulkBar } from '@/components/server/ServerBulkBar';
import { DashboardHeader } from '@/components/server/DashboardHeader';
import { CategoryHeader } from '@/components/server/CategoryHeader';
import { DropZoneIndicator } from '@/components/server/DropZoneIndicator';
import { AddCategoryButton } from '@/components/server/AddCategoryButton';

function GripIcon(props: Record<string, unknown>) {
    return (
        <div {...props} className="flex flex-col gap-[3px] p-1 opacity-30 hover:opacity-80 transition-opacity">
            <div className="flex gap-[3px]">
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
            </div>
            <div className="flex gap-[3px]">
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
            </div>
            <div className="flex gap-[3px]">
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
                <span className="block h-[3px] w-[3px] rounded-[var(--radius-full)] bg-[var(--color-text-secondary)]" />
            </div>
        </div>
    );
}

function ServerGrid({
    servers, zoneId, search, statsMap, drag, selection,
    handlePower, isPowerPending, cardConfig, shouldAnimate, cardIndexRef,
}: ServerGridInternalProps) {
    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (term.length === 0) return servers;
        return servers.filter((s) => s.name.toLowerCase().includes(term) || (s.egg?.name ?? '').toLowerCase().includes(term));
    }, [servers, search]);

    return (
        <div
            ref={drag.getDropZoneRef(zoneId)}
            className="grid gap-3 grid-cols-1 min-h-[48px]"
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
                return (
                    <div key={server.id}>
                        <DropZoneIndicator
                            isVisible={drag.isDragging && drag.activeDropZoneId === zoneId && drag.insertIndex === i}
                        />
                        <m.div
                            initial={shouldAnimate ? { opacity: 0, y: 16 } : false}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, transition: { duration: 0.15 } }}
                            transition={shouldAnimate
                                ? { delay: cardIndex * 0.05, duration: 0.35, ease: 'easeOut' }
                                : { duration: 0 }
                            }
                        >
                            <div className="relative">
                                <div
                                    className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-full pr-2 z-20"
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
            <DropZoneIndicator
                isVisible={drag.isDragging && drag.activeDropZoneId === zoneId && drag.insertIndex === filtered.length}
            />
        </div>
    );
}

export function DashboardPage() {
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const { data, isLoading } = useServers();
    const { data: statsMap } = useServerStats();
    const { sendPower, isPending: isPowerPending } = usePowerAction();
    const selection = useServerSelection();
    const cardConfig = useCardConfig();
    const [search, setSearch] = useState('');
    const hasAnimatedRef = useRef(false);
    const servers = data?.data ?? [];

    const layout = useDashboardLayout(servers);
    const drag = usePointerDrag({
        onDragEnd: (itemId, _sourceZone, targetZone, insertIndex) => {
            // Category drag: itemId starts with "cat-"
            if (itemId.startsWith('cat-') && targetZone === 'category-list') {
                const categoryId = itemId.slice(4);
                layout.moveCategory(categoryId, insertIndex);
            } else {
                // Server drag
                const serverId = Number(itemId);
                if (!Number.isNaN(serverId)) {
                    layout.moveServer(serverId, targetZone, insertIndex);
                }
            }
        },
    });

    const handlePower = useCallback(
        (serverId: number, signal: PowerSignal) => { sendPower({ serverId, signal }); },
        [sendPower],
    );
    const handleBulkPower = useCallback(
        (signal: PowerSignal) => { for (const id of selection.selectedIds) sendPower({ serverId: id, signal }); },
        [selection.selectedIds, sendPower],
    );

    const shouldAnimate = !hasAnimatedRef.current && servers.length > 0;
    if (shouldAnimate) requestAnimationFrame(() => { hasAnimatedRef.current = true; });

    const cardIndexRef = useRef(0);
    cardIndexRef.current = 0;

    const gridProps = {
        search, statsMap, drag, selection, handlePower, isPowerPending, cardConfig,
        shouldAnimate, cardIndexRef,
    };

    const hasSearch = search.trim().length > 0;

    return (
        <div className="relative pb-16">
            <div className="relative z-10">
                <DashboardHeader userName={user?.name} isAdmin={user?.is_admin} serverCount={servers.length} />

                {isLoading ? (
                    <m.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: 0.3 }}
                        className="grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 py-4">
                        <ServerCardSkeleton /><ServerCardSkeleton /><ServerCardSkeleton />
                    </m.div>
                ) : servers.length === 0 ? (
                    <ServerEmptyState />
                ) : (
                    <>
                        <DashboardToolbar
                            search={search} onSearchChange={setSearch}
                            isSelectionMode={selection.isSelectionMode}
                            onToggleSelection={selection.toggleSelectionMode}
                        />

                        {hasSearch && filteredCount(servers, search) === 0 ? (
                            <div className="rounded-[var(--radius-lg)] p-12 text-center glass-card-enhanced">
                                <p className="text-[var(--color-text-muted)]">{t('servers.list.search_empty')}</p>
                            </div>
                        ) : layout.hasCustomLayout ? (
                            <div className="flex flex-col gap-2 pl-6">
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
                                            <ServerGrid
                                                servers={layout.getServersForCategory(cat.id)}
                                                zoneId={cat.id}
                                                {...gridProps}
                                            />
                                        </div>
                                    ))}
                                </AnimatePresence>
                                <DropZoneIndicator isVisible={drag.isDragging && drag.activeDropZoneId === 'category-list' && drag.insertIndex === layout.categories.length} />
                                </div>

                                {/* Uncategorized section */}
                                {layout.uncategorizedServers.length > 0 && (
                                    <div>
                                        <ServerGroupHeader
                                            name={t('servers.list.uncategorized')}
                                            count={layout.uncategorizedServers.length}
                                        />
                                        <ServerGrid
                                            servers={layout.uncategorizedServers}
                                            zoneId="uncategorized"
                                            {...gridProps}
                                        />
                                    </div>
                                )}

                                <div className="mt-2">
                                    <AddCategoryButton onCreate={layout.createCategory} />
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-2 pl-6">
                                <ServerGrid
                                    servers={layout.uncategorizedServers}
                                    zoneId="uncategorized"
                                    {...gridProps}
                                />
                                <div className="mt-2">
                                    <AddCategoryButton onCreate={layout.createCategory} />
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            <ServerBulkBar
                selectedCount={selection.selectedIds.size}
                onBulkPower={handleBulkPower}
                onDeselectAll={selection.deselectAll}
                isPending={isPowerPending}
            />
        </div>
    );
}

function filteredCount(servers: Server[], search: string): number {
    const term = search.trim().toLowerCase();
    if (term.length === 0) return servers.length;
    return servers.filter((s) => s.name.toLowerCase().includes(term) || (s.egg?.name ?? '').toLowerCase().includes(term)).length;
}

interface ServerGridInternalProps {
    servers: Server[];
    zoneId: string;
    search: string;
    statsMap: ServerStatsMap | undefined;
    drag: ReturnType<typeof usePointerDrag>;
    selection: ReturnType<typeof useServerSelection>;
    handlePower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
    cardConfig: ReturnType<typeof useCardConfig>;
    shouldAnimate: boolean;
    cardIndexRef: React.MutableRefObject<number>;
}

function DashboardToolbar({ search, onSearchChange, isSelectionMode, onToggleSelection }: {
    search: string; onSearchChange: (v: string) => void;
    isSelectionMode: boolean; onToggleSelection: () => void;
}) {
    const { t } = useTranslation();
    return (
        <div className="mb-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 p-2 glass-card-enhanced rounded-[var(--radius-lg)]">
            <button type="button" onClick={onToggleSelection}
                className={clsx(
                    'flex items-center justify-center sm:justify-start gap-1.5 rounded-[var(--radius)] border px-3 py-2.5 sm:py-2 text-xs font-medium cursor-pointer',
                    'transition-all duration-200',
                    isSelectionMode
                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] shadow-[var(--shadow-glow)]'
                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:shadow-[var(--shadow-glow)]',
                )}>
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                {t('servers.list.select_mode')}
            </button>
            <div className="flex-1"><ServerSearchBar value={search} onChange={onSearchChange} /></div>
        </div>
    );
}
