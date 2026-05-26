import { useState, useCallback, useRef, useMemo, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { useServers } from '@/hooks/useServers';
import { useServersListLiveUpdates } from '@/hooks/useServersListLiveUpdates';
import { useServerStats } from '@/hooks/useServerStats';
import { usePowerAction } from '@/hooks/usePowerAction';
import { useDashboardLayout } from '@/hooks/useDashboardLayout';
import { usePointerDrag } from '@/hooks/usePointerDrag';
import { useServerSelection } from '@/hooks/useServerSelection';
import { useCardConfig } from '@/hooks/useCardConfig';
import { usePowerTransitionStore } from '@/stores/powerTransitionStore';
import type { PowerSignal } from '@/types/PowerSignal';
import type { ServerStats, ServerStatsMap } from '@/types/ServerStats';
import { ServerCardSkeleton } from '@/components/server/ServerCardSkeleton';
import { ServerEmptyState } from '@/components/server/ServerEmptyState';
import { ServerBulkBar } from '@/components/server/ServerBulkBar';
import { DashboardHeader } from '@/components/server/DashboardHeader';
import { DashboardToolbar } from '@/components/server/DashboardToolbar';
import { DashboardCategoryList } from '@/components/server/DashboardCategoryList';
import { CommandBarLayout } from '@/components/server/layouts/CommandBarLayout';
import { BentoMosaicLayout } from '@/components/server/layouts/BentoMosaicLayout';
import { PulseGridLayout } from '@/components/server/layouts/PulseGridLayout';
import { BiomeDashboardHeader } from '@/components/server/layouts/BiomeDashboardHeader';
import { useNamespace } from '@/i18n/useNamespace';

const TRANSITION_TIMEOUT = 120_000;
const EMPTY_STATS: ServerStats = { state: 'offline', cpu: 0, memory_bytes: 0, disk_bytes: 0, network_rx: 0, network_tx: 0, uptime: 0 };

/** True once the real polled state has reached an optimistic transition's target. */
function transitionReached(target: 'running' | 'stopped', realState: string | undefined): boolean {
    return target === 'running'
        ? realState === 'running' || realState === 'active'
        : realState === 'stopped' || realState === 'offline';
}

export function DashboardPage() {
    useNamespace(["server-overview"] as const);
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const { data, isLoading } = useServers();
    useServersListLiveUpdates({ userId: user?.id ?? null, isAdmin: Boolean(user?.is_admin) });
    const { data: statsMap } = useServerStats();
    const { sendPower, isPending: isPowerPending } = usePowerAction();
    const selection = useServerSelection();
    const cardConfig = useCardConfig();
    const [search, setSearch] = useState('');
    const hasAnimatedRef = useRef(false);
    const servers = data?.data ?? [];

    // Optimistic start/stop transitions merged into the polled stats so cards
    // show "starting…/stopping…" until the real state catches up.
    const transitions = usePowerTransitionStore((s) => s.transitions);
    const clearTransition = usePowerTransitionStore((s) => s.clear);
    const effectiveStatsMap = useMemo<ServerStatsMap | undefined>(() => {
        const ids = Object.keys(transitions);
        if (ids.length === 0) return statsMap;
        const merged: ServerStatsMap = { ...(statsMap ?? {}) };
        for (const idStr of ids) {
            const id = Number(idStr);
            const tr = transitions[id];
            if (!tr) continue;
            const real = statsMap?.[id]?.state;
            const settled = transitionReached(tr.target, real) || Date.now() - tr.since > TRANSITION_TIMEOUT;
            if (settled) continue;
            merged[id] = { ...(statsMap?.[id] ?? EMPTY_STATS), state: tr.display };
        }
        return merged;
    }, [statsMap, transitions]);

    useEffect(() => {
        for (const idStr of Object.keys(transitions)) {
            const id = Number(idStr);
            const tr = transitions[id];
            if (!tr) continue;
            if (transitionReached(tr.target, statsMap?.[id]?.state) || Date.now() - tr.since > TRANSITION_TIMEOUT) {
                clearTransition(id);
            }
        }
    }, [statsMap, transitions, clearTransition]);

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
    const variant = cardConfig.card_layout_variant;
    // `biome` renders through the classic category list (it shares ServerCard's
    // prop shape) so it keeps categories, drag-reorder and responsive columns.
    // Only the three standalone presentations bypass that system.
    const isAlternativeVariant = variant !== 'classic' && variant !== 'biome';

    const filteredServers = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (term.length === 0) return servers;
        return servers.filter(
            (s) => s.name.toLowerCase().includes(term) || (s.egg?.name ?? '').toLowerCase().includes(term),
        );
    }, [servers, search]);

    const gridProps = {
        search, statsMap: effectiveStatsMap, drag, selection, handlePower, isPowerPending, cardConfig,
        shouldAnimate, cardIndexRef,
    };

    return (
        <div className="relative pb-16">
            <div className="relative z-10">
                {variant === 'biome' ? (
                    <BiomeDashboardHeader userName={user?.name} isAdmin={user?.is_admin} servers={servers} statsMap={effectiveStatsMap} />
                ) : (
                    <DashboardHeader userName={user?.name} isAdmin={user?.is_admin} serverCount={servers.length} />
                )}

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

                        {hasSearch && filteredServers.length === 0 ? (
                            <div className="rounded-[var(--radius-lg)] p-6 sm:p-12 text-center glass-card-enhanced">
                                <p className="text-[var(--color-text-muted)]">{t('server-overview:list.search_empty')}</p>
                            </div>
                        ) : isAlternativeVariant ? (
                            <div className="pl-0 sm:pl-6">
                                {variant === 'command-bar' && (
                                    <CommandBarLayout
                                        servers={filteredServers}
                                        statsMap={effectiveStatsMap}
                                        cardConfig={cardConfig}
                                        isSelectionMode={selection.isSelectionMode}
                                        isSelected={selection.isSelected}
                                        onSelect={selection.toggleSelect}
                                        onPower={handlePower}
                                        isPowerPending={isPowerPending}
                                    />
                                )}
                                {variant === 'bento' && (
                                    <BentoMosaicLayout
                                        servers={filteredServers}
                                        statsMap={effectiveStatsMap}
                                        cardConfig={cardConfig}
                                        isSelectionMode={selection.isSelectionMode}
                                        isSelected={selection.isSelected}
                                        onSelect={selection.toggleSelect}
                                        onPower={handlePower}
                                        isPowerPending={isPowerPending}
                                    />
                                )}
                                {variant === 'pulse-grid' && (
                                    <PulseGridLayout
                                        servers={filteredServers}
                                        statsMap={effectiveStatsMap}
                                        cardConfig={cardConfig}
                                        isSelectionMode={selection.isSelectionMode}
                                        isSelected={selection.isSelected}
                                        onSelect={selection.toggleSelect}
                                        onPower={handlePower}
                                        isPowerPending={isPowerPending}
                                    />
                                )}
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
