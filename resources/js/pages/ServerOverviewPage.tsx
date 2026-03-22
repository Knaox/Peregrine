import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useServer } from '@/hooks/useServer';
import { useServerResources } from '@/hooks/useServerResources';
import { Spinner } from '@/components/ui/Spinner';
import { ServerStatusBadge } from '@/components/server/ServerStatusBadge';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { ServerResourceCards } from '@/components/server/ServerResourceCards';
import { ServerInfoCard } from '@/components/server/ServerInfoCard';

export function ServerOverviewPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading: serverLoading } = useServer(serverId);
    const { data: resources, isLoading: resourcesLoading } = useServerResources(serverId);

    if (serverLoading || !server) {
        return (
            <div className="flex min-h-[40vh] items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-bold text-white">{server.name}</h1>
                    <ServerStatusBadge status={server.status} />
                </div>
                <ServerPowerControls
                    serverId={server.id}
                    state={resources?.state}
                />
            </div>

            {/* Stats */}
            <section>
                <h2 className="sr-only">{t('servers.detail.overview')}</h2>
                <ServerResourceCards
                    resources={resources}
                    plan={server.plan}
                    isLoading={resourcesLoading}
                />
            </section>

            {/* Info */}
            <section>
                <ServerInfoCard server={server} />
            </section>
        </div>
    );
}
