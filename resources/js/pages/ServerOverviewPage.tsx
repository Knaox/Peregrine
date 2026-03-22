import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useServer } from '@/hooks/useServer';
import { useServerResources } from '@/hooks/useServerResources';
import { Spinner } from '@/components/ui/Spinner';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { ServerResourceCards } from '@/components/server/ServerResourceCards';
import { ServerInfoCard } from '@/components/server/ServerInfoCard';

export function ServerOverviewPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading: serverLoading } = useServer(serverId);
    const { data: resources, isLoading: resourcesLoading } = useServerResources(serverId);
    const [copied, setCopied] = useState(false);

    if (serverLoading || !server) {
        return (
            <div className="flex min-h-[40vh] items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    const state = resources?.state ?? server.status;
    const statusColor = state === 'running' ? 'red' : state === 'stopped' ? 'gray' : state === 'offline' ? 'red' : 'orange';
    const statusLabel = t(`servers.status.${state}`);
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const handleCopy = () => {
        if (!address) return;
        void navigator.clipboard.writeText(address).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <div className="space-y-6">
            {/* Hero banner with egg image */}
            <div className="relative overflow-hidden rounded-[var(--radius)] border border-[var(--color-border)]">
                {/* Background image or gradient */}
                <div className="relative h-48 sm:h-56">
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    ) : (
                        <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-surface-hover)] to-[var(--color-background)]" />
                    )}
                    {/* Dark overlay for readability */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20" />

                    {/* Content over banner */}
                    <div className="relative flex h-full flex-col justify-end p-6">
                        {/* Status badge top-left */}
                        <div className="absolute left-6 top-4">
                            <Badge color={statusColor}>{statusLabel}</Badge>
                        </div>

                        {/* Server name */}
                        <h1 className="text-3xl font-bold text-white drop-shadow-lg">
                            {server.name}
                        </h1>

                        {/* IP + Egg + Power buttons */}
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            {address && (
                                <button
                                    type="button"
                                    onClick={handleCopy}
                                    className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-sm text-white backdrop-blur-sm transition-colors hover:bg-white/20"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
                                    </svg>
                                    {copied ? t('servers.list.copied') : address}
                                    <svg className="h-3.5 w-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                                    </svg>
                                </button>
                            )}
                            {server.egg && (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-sm text-white backdrop-blur-sm">
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <circle cx="12" cy="12" r="3" /><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
                                    </svg>
                                    {server.egg.name}
                                </span>
                            )}

                            {/* Power buttons */}
                            <div className="ml-auto">
                                <ServerPowerControls serverId={server.id} state={state} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Stats cards */}
            <section>
                <ServerResourceCards
                    resources={resources}
                    plan={server.plan}
                    isLoading={resourcesLoading}
                />
            </section>

            {/* Server info */}
            <section>
                <ServerInfoCard server={server} />
            </section>
        </div>
    );
}
