import { useState, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
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
import { ServerCardSkeleton } from '@/components/server/ServerCardSkeleton';
import { ServerEmptyState } from '@/components/server/ServerEmptyState';
import { ServerBulkBar } from '@/components/server/ServerBulkBar';
import { DashboardHeader } from '@/components/server/DashboardHeader';
import { DashboardToolbar } from '@/components/server/DashboardToolbar';
import { DashboardCategoryList } from '@/components/server/DashboardCategoryList';

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

    const hasSearch = search.trim().length > 0;

    const gridProps = {
        search, statsMap, drag, selection, handlePower, isPowerPending, cardConfig,
        shouldAnimate, cardIndexRef,
    };

    return (
        <div className="relative pb-16">
            <div className="relative z-10">
                <DashboardHeader userName={user?.name} isAdmin={user?.is_admin} serverCount={servers.length} />

                {isLoading ? (
                    <m.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: 0.3 }}
                        className="grid gap-2 sm:gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 py-4">
                        <ServerCardSkeleton /><ServerCardSkeleton /><ServerCardSkeleton />
                    </m.div>
                ) : servers.length === 0 ? (
                    <ServerEmptyState />
                ) : (
                    <>
                        <DashboardToolbar
                            search={search}
                            onSearchChange={setSearch}
                            isSelectionMode={selection.isSelectionMode}
                            onToggleSelection={selection.toggleSelectionMode}
                        />

                        {hasSearch && filteredCount(servers, search) === 0 ? (
                            <div className="rounded-[var(--radius-lg)] p-6 sm:p-12 text-center glass-card-enhanced">
                                <p className="text-[var(--color-text-muted)]">{t('servers.list.search_empty')}</p>
                            </div>
                        ) : (
                            <DashboardCategoryList layout={layout} {...gridProps} />
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
