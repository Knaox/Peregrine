import { useParams, Link, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useServer } from '@/hooks/useServer';
import { Spinner } from '@/components/ui/Spinner';
import { ServerSidebar } from '@/components/server/ServerSidebar';

export function ServerDetailPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading, isError } = useServer(serverId);

    if (isLoading) {
        return (
            <div className="flex min-h-[60vh] items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    if (isError || !server) {
        return (
            <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4">
                <p className="text-slate-400">{t('servers.detail.not_found')}</p>
                <Link
                    to="/dashboard"
                    className="text-sm text-orange-500 hover:text-orange-400"
                >
                    {t('servers.detail.back')}
                </Link>
            </div>
        );
    }

    return (
        <div className="flex min-h-[calc(100vh-4rem)]">
            <ServerSidebar server={server} />
            <main className="flex-1 p-6">
                <Outlet />
            </main>
        </div>
    );
}
