import { useMemo, useCallback } from 'react';
import { useParams, Link, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useServer } from '@/hooks/useServer';
import { useServerLiveUpdates } from '@/hooks/useServerLiveUpdates';
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
    const location = useLocation();
    const serverId = Number(id);
    const { data: server, isLoading, isError } = useServer(serverId);
    // Subscribe to Pelican-driven mirror updates while the user is viewing
    // this server. Refreshes /network /databases /backups /sub-users in
    // real time without polling — degrades gracefully (returns 'unavailable')
    // when Reverb isn't configured on the install.
    useServerLiveUpdates(Number.isFinite(serverId) ? serverId : null);
    // Overview already shows the banner in its own hero — disable the shared
    // EggBackground there to avoid painting the same image twice.
    const isOverviewRoute = location.pathname === `/servers/${serverId}`;
    const sidebarConfig = useSidebarConfig();
    const pluginManifests = usePluginStore((s) => s.manifests);
    const serverPageComponents = usePluginStore((s) => s.serverPageComponents);
    const isTopLayout = sidebarConfig.position === 'top';
    const isDockLayout = sidebarConfig.position === 'dock';

    const userPermissions = server?.permissions ?? null;
    const isOwner = !server?.role || server.role === 'owner' || userPermissions === null;

    // While the server is still installing (Wings egg install script running),
    // only the overview and console make sense. The other panels either query
    // endpoints that 409 during install, or show placeholder data that would
    // confuse the customer. We lock the navigation down.
    const isProvisioning = server?.status === 'provisioning' || server?.status === 'provisioning_failed';

    // While the server is suspended, every Wings command is rejected. We
    // narrow the navigation to read-only panels (overview + files + backups
    // for data recovery) and hide the live ones (console, network, sftp,
    // databases) — they'd just show errors or stale state.
    const isSuspended = server?.status === 'suspended';

    // Merge core sidebar entries with plugin-provided entries, filtered by permissions
    const mergedEntries = useMemo(() => {
        const pluginEntries: SidebarEntry[] = [];
        const currentEggId = Number(server?.egg?.id ?? 0);

        for (const manifest of pluginManifests) {
            for (const se of manifest.server_sidebar_entries ?? []) {
                // Optional egg whitelist — generic plugin-system feature.
                if (Array.isArray(se.requires_egg_ids) && se.requires_egg_ids.length > 0
                    && !se.requires_egg_ids.includes(currentEggId)) {
                    continue;
                }
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

        // Install gate : only overview + console during provisioning.
        const applyInstallGate = (entries: SidebarEntry[]) =>
            isProvisioning ? entries.filter((e) => e.id === 'overview' || e.id === 'console') : entries;

        // Suspended gate : only the overview is visible. Wings rejects every
        // command and the customer-facing message belongs on the home tab.
        const applySuspendedGate = (entries: SidebarEntry[]) =>
            isSuspended
                ? entries.filter((e) => e.id === 'overview')
                : entries;

        // If owner, show everything (minus the install gate). If subuser, filter by permissions.
        if (isOwner) return applySuspendedGate(applyInstallGate(all));

        return applySuspendedGate(applyInstallGate(
            all.filter((entry) => {
                const requiredPerm = SIDEBAR_ENTRY_PERMISSIONS[entry.id];
                if (!requiredPerm) return true; // overview = always visible
                return userPermissions?.includes(requiredPerm) ?? false;
            }),
        ));
    }, [sidebarConfig.entries, pluginManifests, isOwner, userPermissions, isProvisioning, isSuspended, server?.egg?.id]);

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

    // Dock keeps a flex column wrapper too — the main element relies on
    // `flex-1` to claim the full viewport height and own the scroll box.
    // Without it, `overflow-y-auto` has nothing to overflow and the page
    // becomes unscrollable.
    const wrapperClass = isTopLayout
        ? 'relative flex flex-col h-[100dvh] overflow-hidden'
        : isDockLayout
            ? 'relative flex flex-col h-[100dvh] overflow-hidden'
            : 'relative flex h-[100dvh] overflow-hidden';

    // pt-14 is reserved space for the mobile hamburger toggle (top-left) —
    // only Classic / Rail render a hamburger, so skip it for Dock and Top
    // layouts. This lets the hero banner reach the viewport top in those
    // presets. Dock keeps extra bottom padding so scrolled content isn't
    // hidden behind the floating dock + top-left context pill.
    const hasMobileHamburger = !isTopLayout && !isDockLayout;
    const contentPaddingClass = [
        'server-page-content relative z-10 flex flex-col min-h-full p-3 sm:p-4 md:p-6',
        hasMobileHamburger && 'pt-14 sm:pt-6 md:pt-6',
        isDockLayout && 'pt-6 sm:pt-6 md:pt-8 pb-24 sm:pb-28 md:pb-32',
    ].filter(Boolean).join(' ');

    // Tag the wrapper with the current sub-route so per-page CSS overrides
    // (data-page-console-fullwidth etc.) can target it without each page
    // having to wire useLayoutIntent itself.
    const subRoute = (() => {
        const path = location.pathname;
        if (path.includes('/console')) return 'console';
        if (path.includes('/files')) return 'files';
        if (path.includes('/databases')) return 'databases';
        if (path.includes('/backups')) return 'backups';
        if (path.includes('/network')) return 'network';
        if (path.includes('/schedules')) return 'schedules';
        if (path.includes('/sftp')) return 'sftp';
        return 'overview';
    })();

    return (
        <div className={wrapperClass}>
            {/* Viewport-anchored background — sits BEHIND the scrollable main
                so the egg banner stays in its hero-area band regardless of
                content scroll. Moving it inside <main> made it scroll with
                the content and revealed the mask cutoff as a hard line. */}
            <EggBackground imageUrl={server.egg?.banner_image} disabled={isOverviewRoute} />
            <ServerSidebar server={server} sidebarConfig={mergedConfig} />
            <m.main
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3 }}
                className="relative flex-1 overflow-y-auto z-10"
            >
                <div className={contentPaddingClass} data-route={subRoute}>
                    <Routes>
                        {dynamicRoutes.map((route) =>
                            route.index
                                ? <Route key="__index" index element={route.element} />
                                : <Route key={route.path} path={route.path} element={route.element} />,
                        )}
                        {/* Unmatched suffix → bounce to the overview. Useful
                            when the install gate strips routes the user has
                            bookmarked (e.g. /files). */}
                        <Route path="*" element={<Navigate to="" replace />} />
                    </Routes>
                </div>
            </m.main>
        </div>
    );
}
