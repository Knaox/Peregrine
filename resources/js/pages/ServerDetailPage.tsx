import { useMemo } from 'react';
import { useParams, Link, Routes, Route, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useServer } from '@/hooks/useServer';
import { useSidebarConfig } from '@/hooks/useSidebarConfig';
import { Spinner } from '@/components/ui/Spinner';
import { GlassCard } from '@/components/ui/GlassCard';
import { ServerSidebar } from '@/components/server/ServerSidebar';
import { EggBackground } from '@/components/EggBackground';
import { ServerOverviewPage } from '@/pages/ServerOverviewPage';
import { ServerConsolePage } from '@/pages/ServerConsolePage';
import { ServerFilesPage } from '@/pages/ServerFilesPage';
import { ServerSftpPage } from '@/pages/ServerSftpPage';
import { ServerDatabasesPage } from '@/pages/ServerDatabasesPage';
import { ServerBackupsPage } from '@/pages/ServerBackupsPage';
import { ServerSchedulesPage } from '@/pages/ServerSchedulesPage';
import { ServerNetworkPage } from '@/pages/ServerNetworkPage';
import type { SidebarEntry } from '@/hooks/useSidebarConfig';

/** Map sidebar entry IDs to page components */
const PAGE_COMPONENTS: Record<string, React.ComponentType> = {
    overview: ServerOverviewPage,
    console: ServerConsolePage,
    files: ServerFilesPage,
    sftp: ServerSftpPage,
    databases: ServerDatabasesPage,
    backups: ServerBackupsPage,
    schedules: ServerSchedulesPage,
    network: ServerNetworkPage,
};

function buildRoutes(entries: SidebarEntry[]) {
    const routes: { path: string; element: React.ReactNode; index: boolean }[] = [];
    let hasIndex = false;

    for (const entry of entries) {
        const Component = PAGE_COMPONENTS[entry.id];
        if (!Component) continue;

        const suffix = entry.route_suffix.replace(/^\//, '');

        if (!suffix || suffix === '') {
            routes.push({ path: '', element: <Component />, index: true });
            hasIndex = true;
        } else {
            routes.push({ path: suffix, element: <Component />, index: false });
        }
    }

    // Fallback: if no index route, redirect to first entry
    if (!hasIndex && routes.length > 0) {
        const firstPath = routes[0]?.path ?? '';
        routes.unshift({ path: '', element: <Navigate to={firstPath} replace />, index: true });
    }

    return routes;
}

export function ServerDetailPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading, isError } = useServer(serverId);
    const sidebarConfig = useSidebarConfig();
    const isTopLayout = sidebarConfig.position === 'top';

    const dynamicRoutes = useMemo(
        () => buildRoutes(sidebarConfig.entries),
        [sidebarConfig.entries],
    );

    if (isLoading) {
        return (
            <div className="flex h-[100dvh] items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    if (isError || !server) {
        return (
            <div className="flex h-[100dvh] flex-col items-center justify-center gap-4">
                <GlassCard className="px-8 py-6 text-center">
                    <p className="text-[var(--color-text-secondary)]">
                        {t('servers.detail.not_found')}
                    </p>
                    <Link
                        to="/dashboard"
                        className="mt-3 inline-block text-sm text-[var(--color-primary)] transition-colors duration-[var(--transition-fast)] hover:text-[var(--color-primary-hover)]"
                    >
                        {t('servers.detail.back')}
                    </Link>
                </GlassCard>
            </div>
        );
    }

    return (
        <div className={isTopLayout ? 'flex flex-col h-[100dvh] overflow-hidden' : 'flex h-[100dvh] overflow-hidden'}>
            <ServerSidebar server={server} />
            <m.main
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3 }}
                className="relative flex-1 overflow-y-auto"
            >
                <EggBackground imageUrl={server.egg?.banner_image} />
                <div className="relative z-10 p-3 pt-14 sm:p-4 sm:pt-14 md:p-6 md:pt-6 flex flex-col min-h-full">
                    <Routes>
                        {dynamicRoutes.map((route) =>
                            route.index
                                ? <Route key="__index" index element={route.element} />
                                : <Route key={route.path} path={route.path} element={route.element} />,
                        )}
                    </Routes>
                </div>
            </m.main>
        </div>
    );
}
