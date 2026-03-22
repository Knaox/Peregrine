import { useState, useMemo, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
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

    const handleDragStart = useCallback(
        (index: number) => {
            serverOrder.startDrag(index);
        },
        [serverOrder],
    );

    const handleDragOver = useCallback(
        (index: number) => {
            serverOrder.dragOver(index);
        },
        [serverOrder],
    );

    const handleDragEnd = useCallback(
        (fromIdx: number, toIdx: number) => {
            serverOrder.moveServer(fromIdx, toIdx, orderedServers);
            serverOrder.endDrag();
        },
        [serverOrder, orderedServers],
    );

    return (
        <div className="pb-16">
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
                    {t('nav.dashboard')}
                </h1>
                <p className="mt-1 text-[var(--color-text-secondary)]">{user?.name}</p>
            </div>

            {/* Admin link */}
            {user?.is_admin && (
                <a
                    href="/admin"
                    className="mb-6 inline-flex items-center gap-2 rounded-[var(--radius)] border border-orange-500/30 bg-orange-500/10 px-4 py-2 text-sm font-medium text-orange-400 transition-colors hover:bg-orange-500/20"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {t('nav.settings')}
                </a>
            )}

            {/* Servers section */}
            <div>
                <h2 className="mb-4 text-lg font-semibold text-[var(--color-text-primary)]">
                    {t('servers.list.title')}
                </h2>

                {isLoading ? (
                    <div className="flex items-center justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : servers.length === 0 ? (
                    <ServerEmptyState />
                ) : (
                    <>
                        {/* Toolbar: selection toggle + search */}
                        <div className="mb-4 flex items-center gap-3">
                            <button
                                type="button"
                                onClick={selection.toggleSelectionMode}
                                className={clsx(
                                    'flex items-center gap-1.5 rounded-[var(--radius)] border px-3 py-2 text-xs font-medium transition-colors',
                                    selection.isSelectionMode
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
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
                            <div className="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] p-12 text-center">
                                <p className="text-[var(--color-text-secondary)]">
                                    {t('servers.list.search_empty')}
                                </p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {groups.map((group) => (
                                    <div key={group.name}>
                                        <ServerGroupHeader name={group.name} count={group.servers.length} />
                                        <div className="flex flex-col gap-2">
                                            {group.servers.map((server) => {
                                                const globalIdx = orderedServers.indexOf(server);
                                                return (
                                                    <div
                                                        key={server.id}
                                                        draggable
                                                        onDragStart={() => handleDragStart(globalIdx)}
                                                        onDragOver={(e) => { e.preventDefault(); handleDragOver(globalIdx); }}
                                                        onDrop={() => {
                                                            if (serverOrder.dragIndex !== null) {
                                                                handleDragEnd(serverOrder.dragIndex, globalIdx);
                                                            }
                                                        }}
                                                        onDragEnd={() => serverOrder.endDrag()}
                                                        className={clsx(
                                                            'transition-opacity',
                                                            serverOrder.dragOverIndex === globalIdx && 'border-t-2 border-[var(--color-primary)]',
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
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
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
