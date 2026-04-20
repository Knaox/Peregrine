import { useMemo, useCallback } from 'react';
import { useParams, Link, Routes, Route, Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useServer } from '@/hooks/useServer';
import { useSidebarConfig } from '@/hooks/useSidebarConfig';
import { usePluginStore } from '@/plugins/pluginStore';
import { SIDEBAR_ENTRY_PERMISSIONS } from '@/utils/serverPermissions';
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

/** Map sidebar entry IDs to page components (core pages only) */
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

function buildRoutes(
    entries: SidebarEntry[],
    pluginPageResolver: (id: string) => React.ComponentType | undefined,
) {
    const routes: { path: string; element: React.ReactNode; index: boolean }[] = [];
    let hasIndex = false;

    for (const entry of entries) {
        // Try core pages first, then plugin pages
        const Component = PAGE_COMPONENTS[entry.id] ?? pluginPageResolver(entry.id);
        if (!Component) continue;

        // Guard against malformed entries (plugins or hand-edited JSON) with a missing route_suffix.
        const suffix = (entry.route_suffix ?? '').replace(/^\//, '');

        if (!suffix || suffix === '') {
            routes.push({ path: '', element: <Component />, index: true });
            hasIndex = true;
        } else {
            routes.push({ path: suffix, element: <Component />, index: false });
        }
    }

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
    const pluginManifests = usePluginStore((s) => s.manifests);
    const serverPageComponents = usePluginStore((s) => s.serverPageComponents);
    const isTopLayout = sidebarConfig.position === 'top';
    const isDockLayout = sidebarConfig.position === 'dock';

    const userPermissions = server?.permissions ?? null;
    const isOwner = !server?.role || server.role === 'owner' || userPermissions === null;

    // Merge core sidebar entries with plugin-provided entries, filtered by permissions
    const mergedEntries = useMemo(() => {
        const pluginEntries: SidebarEntry[] = [];

        for (const manifest of pluginManifests) {
            for (const se of manifest.server_sidebar_entries ?? []) {
                pluginEntries.push({
                    id: se.id,
                    label_key: se.label_key,
                    icon: se.icon,
                    enabled: true,
                    route_suffix: se.route_suffix,
                    order: se.order ?? 100,
                });
            }
        }

        const all = [...sidebarConfig.entries, ...pluginEntries].sort((a, b) => a.order - b.order);

        // If owner, show everything. If subuser, filter by permissions.
        if (isOwner) return all;

        return all.filter((entry) => {
            const requiredPerm = SIDEBAR_ENTRY_PERMISSIONS[entry.id];
            if (!requiredPerm) return true; // overview = always visible
            return userPermissions?.includes(requiredPerm) ?? false;
        });
    }, [sidebarConfig.entries, pluginManifests, isOwner, userPermissions]);

    const mergedConfig = useMemo(
        () => ({ ...sidebarConfig, entries: mergedEntries }),
        [sidebarConfig, mergedEntries],
    );

    const pluginPageResolver = useCallback(
        (pageId: string) => serverPageComponents[pageId],
        [serverPageComponents],
    );

    const dynamicRoutes = useMemo(
        () => buildRoutes(mergedEntries, pluginPageResolver),
        [mergedEntries, pluginPageResolver],
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

    const wrapperClass = isTopLayout
        ? 'flex flex-col h-[100dvh] overflow-hidden'
        : isDockLayout
            ? 'relative h-[100dvh] overflow-hidden'
            : 'flex h-[100dvh] overflow-hidden';

    // Dock floats on top of the content; keep extra bottom padding so the
    // last scroll item is not hidden behind it on small screens.
    const contentPaddingClass = isDockLayout
        ? 'relative z-10 p-3 pt-14 pb-28 sm:p-4 sm:pt-14 sm:pb-28 md:p-6 md:pt-6 md:pb-32 flex flex-col min-h-full'
        : 'relative z-10 p-3 pt-14 sm:p-4 sm:pt-14 md:p-6 md:pt-6 flex flex-col min-h-full';

    return (
        <div className={wrapperClass}>
            <ServerSidebar server={server} sidebarConfig={mergedConfig} />
            <m.main
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3 }}
                className="relative flex-1 overflow-y-auto"
            >
                <EggBackground imageUrl={server.egg?.banner_image} />
                <div className={contentPaddingClass}>
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
