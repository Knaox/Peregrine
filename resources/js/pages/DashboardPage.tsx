import { useState, useMemo, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useServers } from '@/hooks/useServers';
import { useServerStats } from '@/hooks/useServerStats';
import { usePowerAction } from '@/hooks/usePowerAction';
import { useServerOrder } from '@/hooks/useServerOrder';
import { useServerSelection } from '@/hooks/useServerSelection';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import { Spinner } from '@/components/ui/Spinner';
import { ServerSearchBar } from '@/components/server/ServerSearchBar';
import { ServerCard } from '@/components/server/ServerCard';
import { ServerEmptyState } from '@/components/server/ServerEmptyState';
import { ServerGroupHeader } from '@/components/server/ServerGroupHeader';
import { ServerBulkBar } from '@/components/server/ServerBulkBar';

interface ServerGroup {
    name: string;
    servers: Server[];
}

function groupByEgg(servers: Server[], uncategorizedLabel: string): ServerGroup[] {
    const groups = new Map<string, Server[]>();
    for (const server of servers) {
        const key = server.egg?.name ?? uncategorizedLabel;
        const list = groups.get(key);
        if (list) {
            list.push(server);
        } else {
            groups.set(key, [server]);
        }
    }
    return Array.from(groups.entries()).map(([name, srvs]) => ({ name, servers: srvs }));
}

export function DashboardPage() {
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const { data, isLoading } = useServers();
    const { data: statsMap } = useServerStats();
    const { sendPower, isPending: isPowerPending } = usePowerAction();
    const serverOrder = useServerOrder();
    const selection = useServerSelection();
    const [search, setSearch] = useState('');

    const hasAnimatedRef = useRef(false);
    const servers = data?.data ?? [];

    const handlePower = useCallback(
        (serverId: number, signal: PowerSignal) => {
            sendPower({ serverId, signal });
        },
        [sendPower],
    );

    const handleBulkPower = useCallback(
        (signal: PowerSignal) => {
            for (const id of selection.selectedIds) {
                sendPower({ serverId: id, signal });
            }
        },
        [selection.selectedIds, sendPower],
    );

    const filteredServers = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (term.length === 0) return servers;
        return servers.filter(
            (s) =>
                s.name.toLowerCase().includes(term) ||
                (s.egg?.name ?? '').toLowerCase().includes(term),
        );
    }, [servers, search]);

    const orderedServers = useMemo(
        () => serverOrder.getOrderedServers(filteredServers),
        [serverOrder, filteredServers],
    );

    const groups = useMemo(
        () => groupByEgg(orderedServers, t('servers.list.uncategorized')),
        [orderedServers, t],
    );

    const hasSearch = search.trim().length > 0;

    const shouldAnimate = !hasAnimatedRef.current && servers.length > 0;
    if (shouldAnimate) {
        requestAnimationFrame(() => { hasAnimatedRef.current = true; });
    }

    const handleDragStart = useCallback(
        (index: number) => { serverOrder.startDrag(index); },
        [serverOrder],
    );

    const handleDragOver = useCallback(
        (index: number) => { serverOrder.dragOver(index); },
        [serverOrder],
    );

    const handleDragEnd = useCallback(
        (fromIdx: number, toIdx: number) => {
            serverOrder.moveServer(fromIdx, toIdx, orderedServers);
            serverOrder.endDrag();
        },
        [serverOrder, orderedServers],
    );

    let globalCardIndex = 0;

    return (
        <div className="pb-16">
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">
                    {t('nav.dashboard')}
                </h1>
                <p className="mt-1 text-[var(--color-text-secondary)]">{user?.name}</p>
            </div>

            {/* Admin link */}
            {user?.is_admin && (
                <m.a
                    href="/admin"
                    whileHover={{ scale: 1.03 }}
                    whileTap={{ scale: 0.97 }}
                    className={clsx(
                        'mb-6 inline-flex items-center gap-2',
                        'rounded-[var(--radius-full)] px-5 py-2.5 text-sm font-medium',
                        'backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-primary)]/30',
                        'text-[var(--color-primary)]',
                        'transition-all duration-[var(--transition-base)]',
                        'hover:border-[var(--color-primary)]/50 hover:shadow-[var(--shadow-glow)]',
                    )}
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {t('nav.settings')}
                </m.a>
            )}

            {/* Servers section */}
            <div>
                <h2 className="mb-4 text-lg font-semibold text-[var(--color-text-primary)]">
                    {t('servers.list.title')}
                </h2>

                {isLoading ? (
                    <m.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ duration: 0.3 }}
                        className="flex items-center justify-center py-12"
                    >
                        <Spinner size="lg" />
                    </m.div>
                ) : servers.length === 0 ? (
                    <ServerEmptyState />
                ) : (
                    <>
                        {/* Toolbar */}
                        <div className={clsx(
                            'mb-4 flex items-center gap-3 p-2',
                            'backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-glass-border)]',
                            'rounded-[var(--radius-lg)]',
                        )}>
                            <button
                                type="button"
                                onClick={selection.toggleSelectionMode}
                                className={clsx(
                                    'flex items-center gap-1.5 rounded-[var(--radius)] border px-3 py-2 text-xs font-medium',
                                    'transition-all duration-[var(--transition-base)]',
                                    selection.isSelectionMode
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] shadow-[var(--shadow-glow)]'
                                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:shadow-[var(--shadow-glow)]',
                                )}
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                {t('servers.list.select_mode')}
                            </button>
                            <div className="flex-1">
                                <ServerSearchBar value={search} onChange={setSearch} />
                            </div>
                        </div>

                        {filteredServers.length === 0 && hasSearch ? (
                            <div className={clsx(
                                'rounded-[var(--radius-lg)] p-12 text-center',
                                'backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-glass-border)]',
                            )}>
                                <p className="text-[var(--color-text-muted)]">
                                    {t('servers.list.search_empty')}
                                </p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-2">
                                <AnimatePresence mode="popLayout">
                                    {groups.map((group) => (
                                        <div key={group.name}>
                                            <ServerGroupHeader name={group.name} count={group.servers.length} />
                                            <div className="flex flex-col gap-2">
                                                {group.servers.map((server) => {
                                                    const globalIdx = orderedServers.indexOf(server);
                                                    const cardIndex = globalCardIndex++;
                                                    return (
                                                        <m.div
                                                            key={server.id}
                                                            initial={shouldAnimate ? { opacity: 0, y: 15 } : false}
                                                            animate={{ opacity: 1, y: 0 }}
                                                            exit={{ opacity: 0, y: -10, transition: { duration: 0.2 } }}
                                                            transition={
                                                                shouldAnimate
                                                                    ? { delay: cardIndex * 0.05, duration: 0.35, ease: 'easeOut' }
                                                                    : { duration: 0 }
                                                            }
                                                            draggable
                                                            onDragStart={() => handleDragStart(globalIdx)}
                                                            onDragOver={(e: React.DragEvent) => { e.preventDefault(); handleDragOver(globalIdx); }}
                                                            onDrop={() => {
                                                                if (serverOrder.dragIndex !== null) {
                                                                    handleDragEnd(serverOrder.dragIndex, globalIdx);
                                                                }
                                                            }}
                                                            onDragEnd={() => serverOrder.endDrag()}
                                                            className={clsx(
                                                                serverOrder.dragOverIndex === globalIdx && [
                                                                    'border-t-2 border-transparent',
                                                                    'bg-gradient-to-b from-[var(--color-primary)]/20 to-transparent',
                                                                    '[border-image:linear-gradient(to_right,transparent,var(--color-primary),transparent)_1]',
                                                                ],
                                                            )}
                                                        >
                                                            <ServerCard
                                                                server={server}
                                                                stats={statsMap?.[server.id]}
                                                                onPower={handlePower}
                                                                isPowerPending={isPowerPending}
                                                                isSelectable={selection.isSelectionMode}
                                                                isSelected={selection.isSelected(server.id)}
                                                                onSelect={selection.toggleSelect}
                                                                isDragging={serverOrder.dragIndex === globalIdx}
                                                            />
                                                        </m.div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                </AnimatePresence>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Bulk action bar */}
            <ServerBulkBar
                selectedCount={selection.selectedIds.size}
                onBulkPower={handleBulkPower}
                onDeselectAll={selection.deselectAll}
                isPending={isPowerPending}
            />
        </div>
    );
}
