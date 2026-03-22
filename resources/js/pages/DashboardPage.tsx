import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '@/stores/authStore';
import { useServers } from '@/hooks/useServers';
import { useServerStats } from '@/hooks/useServerStats';
import type { PowerSignal } from '@/types/PowerSignal';
import { usePowerAction } from '@/hooks/usePowerAction';
import { Spinner } from '@/components/ui/Spinner';
import { ServerSearchBar } from '@/components/server/ServerSearchBar';
import { ServerCard } from '@/components/server/ServerCard';
import { ServerEmptyState } from '@/components/server/ServerEmptyState';

export function DashboardPage() {
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const { data, isLoading } = useServers();
    const { data: statsMap } = useServerStats();
    const { sendPower, isPending: isPowerPending } = usePowerAction();
    const handlePower = (serverId: number, signal: PowerSignal) => {
        sendPower({ serverId, signal });
    };
    const [search, setSearch] = useState('');

    const servers = data?.data ?? [];

    const filteredServers = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (term.length === 0) return servers;
        return servers.filter((s) => s.name.toLowerCase().includes(term));
    }, [servers, search]);

    const hasSearch = search.trim().length > 0;

    return (
        <div>
            {/* Welcome header */}
            <div className="mb-8">
                <h1 className="text-2xl font-bold">{t('nav.dashboard')}</h1>
                <p className="mt-1 text-slate-400">{user?.name}</p>
            </div>

            {/* Admin link */}
            {user?.is_admin && (
                <a
                    href="/admin"
                    className="mb-6 inline-flex items-center gap-2 rounded-lg border border-orange-500/30 bg-orange-500/10 px-4 py-2 text-sm font-medium text-orange-400 transition-colors hover:bg-orange-500/20"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                        />
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                    {t('nav.settings')}
                </a>
            )}

            {/* Servers section */}
            <div>
                <h2 className="mb-4 text-lg font-semibold">{t('servers.list.title')}</h2>

                {isLoading ? (
                    <div className="flex items-center justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : servers.length === 0 ? (
                    <ServerEmptyState />
                ) : (
                    <>
                        <div className="mb-4">
                            <ServerSearchBar value={search} onChange={setSearch} />
                        </div>

                        {filteredServers.length === 0 && hasSearch ? (
                            <div className="rounded-xl border border-slate-700 bg-slate-800 p-12 text-center">
                                <p className="text-slate-400">
                                    {t('servers.list.search_empty')}
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {filteredServers.map((server) => (
                                    <ServerCard
                                        key={server.id}
                                        server={server}
                                        stats={statsMap?.[server.id]}
                                        onPower={handlePower}
                                        isPowerPending={isPowerPending}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
