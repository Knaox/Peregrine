import { useState, useMemo, useEffect } from 'react';
import { useParams, useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { copyToClipboard } from '@/utils/clipboard';
import { useServer } from '@/hooks/useServer';
import { useWingsWebSocket } from '@/hooks/useWingsWebSocket';
import { useRefetchServerOnInstallComplete } from '@/hooks/useRefetchServerOnInstallComplete';
import type { CompletedOperation } from '@/hooks/useServerOperationLifecycle';
import { useSidebarConfig } from '@/hooks/useSidebarConfig';
import { usePluginStore } from '@/plugins/pluginStore';
import { SIDEBAR_ENTRY_PERMISSIONS } from '@/utils/serverPermissions';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { ServerBootFixPrompts } from '@/components/console/ServerBootFixPrompts';
import { ServerResourceCards } from '@/components/server/ServerResourceCards';
import { ServerInfoCard } from '@/components/server/ServerInfoCard';
import { ServerSettingsActions } from '@/components/server/ServerSettingsActions';
import { ServerVariables } from '@/components/server/ServerVariables';
import { OverviewQuickActions } from '@/components/server/OverviewQuickActions';
import { ServerOverviewHero } from '@/components/server/overview/ServerOverviewHero';
import { InstallationOverview } from '@/components/server/InstallationOverview';
import { SuspendedOverview } from '@/components/server/SuspendedOverview';
import type { SidebarEntry } from '@/hooks/useSidebarConfig';
import { useNamespace } from '@/i18n/useNamespace';

type ServerState = 'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';

export function ServerOverviewPage() {
    useNamespace(["server-overview"] as const);
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const location = useLocation();
    const serverId = Number(id);
    const { data: server, isLoading: serverLoading } = useServer(serverId);

    // One-shot success Alert when a long-running operation just completed
    // (server install, unsuspend, modpack install/uninstall, …). The flag is
    // pushed onto location.state by `useServerOperationLifecycle` at the
    // ServerDetailPage level, then captured here in component state so a
    // refresh or re-render doesn't re-trigger the message.
    const [completedOp] = useState<CompletedOperation | null>(() => {
        const s = (location.state as Partial<CompletedOperation> | null) ?? null;
        return s?.operationCompleted ? (s as CompletedOperation) : null;
    });
    const [opAlertVisible, setOpAlertVisible] = useState<boolean>(completedOp !== null);

    // Clear the history state on mount so a browser refresh on /servers/{id}
    // doesn't re-show the toast. The captured `completedOp` keeps the data
    // alive in component state for the lifetime of this mount.
    useEffect(() => {
        if (completedOp) {
            navigate(location.pathname, { replace: true, state: null });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const completionMessage = useMemo((): string | null => {
        if (!completedOp) return null;
        switch (completedOp.operationType) {
            case 'modpack':
                return t('server-overview:operations.completion.modpack', {
                    name: completedOp.operationName ?? '',
                });
            case 'modpack_uninstall':
                return t('server-overview:operations.completion.modpack_uninstall');
            case 'unsuspend':
                return t('server-overview:operations.completion.unsuspend');
            case 'install':
            default:
                return t('server-overview:operations.completion.install');
        }
    }, [completedOp, t]);
    // Subscribe to console output too — when the server is installing we
    // show a live tail of `install output` messages on the hero card.
    const { resources, serverState: wsState, messages, installCompleted, isConnected, eulaRequired, javaIssue } = useWingsWebSocket(serverId, { stats: true, console: true });
    const sidebarConfig = useSidebarConfig();
    const pluginManifests = usePluginStore((s) => s.manifests);
    const pluginHomeSectionComponents = usePluginStore((s) => s.serverHomeSectionComponents);
    const [copied, setCopied] = useState(false);

    // Auto-refresh the server query as soon as Wings reports install
    // completion — Pelican's `updated: Server` webhook arrives a few
    // seconds later and flips status to `active`, which unlocks the
    // regular dashboard without the user having to F5.
    useRefetchServerOnInstallComplete(serverId, installCompleted, server?.status);

    // ---- HOOKS ABOVE EARLY RETURNS ----
    // Every hook in this component runs unconditionally before any of the
    // status-driven `return`s below. Otherwise React drops the call count
    // when status flips from active → provisioning mid-session and throws
    // minified error #300 ("Rendered fewer hooks than expected"). The
    // useMemos compute null-safe so they're cheap to run when `server` is
    // still loading.

    // Permission helpers — null-safe so the hook deps below stay stable
    // through the loading state.
    const perms = server?.permissions ?? null;
    const isOwner = !!server && (!server.role || server.role === 'owner' || perms === null);
    const hasPerm = (p: string) => isOwner || (perms?.includes(p) ?? false);

    // Plugin-contributed home sections, sorted by their declared `order` then
    // filtered by permissions and (optional) egg whitelist. Owners always see
    // them; subusers need the `required_permission` claim if the manifest
    // declares one. The `requires_egg_ids` filter mirrors the sidebar-entry
    // behaviour so plugins can dynamically opt out of mounting on servers
    // they have no data for (e.g. egg-config-editor sets it from DB).
    const pluginHomeSections = useMemo(() => {
        const out: { key: string; Component: React.ComponentType<{ serverId: number; serverState?: string }>; order: number; placement?: string }[] = [];
        if (!server) return out;
        const currentEggId = Number(server.egg?.id ?? 0);
        for (const manifest of pluginManifests) {
            for (const section of manifest.server_home_sections ?? []) {
                if (section.required_permission && !hasPerm(section.required_permission)) continue;
                if (Array.isArray(section.requires_egg_ids) && section.requires_egg_ids.length > 0
                    && !section.requires_egg_ids.includes(currentEggId)) {
                    continue;
                }
                const Component = pluginHomeSectionComponents[section.id];
                if (!Component) continue;
                out.push({ key: `${manifest.id}:${section.id}`, Component, order: section.order ?? 100, placement: section.placement });
            }
        }
        return out.sort((a, b) => a.order - b.order);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pluginManifests, pluginHomeSectionComponents, isOwner, perms, server?.egg?.id, server?.id]);

    const filteredEntries = useMemo(() => {
        // Merge core + plugin sidebar entries
        const pluginEntries: SidebarEntry[] = [];
        const currentEggId = Number(server?.egg?.id ?? 0);
        for (const manifest of pluginManifests) {
            for (const se of manifest.server_sidebar_entries ?? []) {
                // Optional egg whitelist — generic plugin-system feature.
                if (Array.isArray(se.requires_egg_ids) && se.requires_egg_ids.length > 0
                    && !se.requires_egg_ids.includes(currentEggId)) {
                    continue;
                }
                // Permission gate — owners see everything, subusers need the
                // declared grant (plugin ids aren't in SIDEBAR_ENTRY_PERMISSIONS).
                if (!isOwner && se.required_permission
                    && !(perms?.includes(se.required_permission) ?? false)) {
                    continue;
                }
                pluginEntries.push({ id: se.id, label_key: se.label_key, icon: se.icon, enabled: true, route_suffix: se.route_suffix, order: se.order ?? 100 });
            }
        }
        const all = [...sidebarConfig.entries, ...pluginEntries].sort((a, b) => a.order - b.order);

        if (isOwner) return all;
        return all.filter((e) => {
            const req = SIDEBAR_ENTRY_PERMISSIONS[e.id];
            if (!req) return true;
            return perms?.includes(req) ?? false;
        });
    }, [sidebarConfig.entries, pluginManifests, isOwner, perms, server?.egg?.id]);

    // ---- EARLY RETURNS (no further hooks below this point) ----

    if (serverLoading || !server) {
        return <div className="flex min-h-[40vh] items-center justify-center"><Spinner size="lg" /></div>;
    }

    // While Wings is installing the egg (`server.status === 'provisioning'`),
    // show a dedicated installation hero with the live install output. The
    // regular dashboard panes (stats, variables, settings) are useless until
    // the install completes — we hide them.
    if (server.status === 'provisioning') {
        return <InstallationOverview server={server} messages={messages} installCompleted={installCompleted} />;
    }

    // Server suspended (Pelican `suspended_at` set, billing paused). Wings
    // rejects every command so the regular dashboard would show stale data
    // and broken buttons — replace it with a clear suspension hero instead.
    if (server.status === 'suspended') {
        return <SuspendedOverview server={server} />;
    }

    // Fallback to DB status only before Wings WS reports anything. Once
    // connected, trust wsState even when it's 'offline' — otherwise we'd
    // mask a freshly stopped server with the admin status (`active`),
    // which `ServerPowerControls` doesn't treat as stopped, leaving the
    // "Force stop" button visible on a server that's already off.
    const state = (isConnected || wsState !== 'offline' ? wsState : server.status) as ServerState;
    const statusLabel = t(`server-overview:status.${state}`);
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;
    const isRunningState = state === 'running' || state === 'active';
    const uptime = resources?.uptime;

    const canStart = hasPerm('control.start');
    const canStop = hasPerm('control.stop');
    const canRestart = hasPerm('control.restart');
    const canPower = canStart || canStop || canRestart;
    const canViewStats = hasPerm('overview.stats');
    const canViewStartup = hasPerm('startup.read');
    const canEditStartup = hasPerm('startup.update');
    const canViewConfig = hasPerm('overview.server-info') || hasPerm('settings.rename');
    const canRename = hasPerm('settings.rename');
    const canReinstall = hasPerm('settings.reinstall');

    const handleCopy = () => {
        if (!address) return;
        void copyToClipboard(address).then(() => { setCopied(true); setTimeout(() => setCopied(false), 1500); });
    };

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.4, 0, 0.2, 1] }} className="space-y-6">

            {completedOp && opAlertVisible && completionMessage ? (
                <Alert variant="success">
                    <div className="flex w-full items-center justify-between gap-3">
                        <span>{completionMessage}</span>
                        <button
                            type="button"
                            onClick={() => setOpAlertVisible(false)}
                            className="text-xs opacity-70 transition-opacity hover:opacity-100"
                            aria-label={t('server-overview:operations.completion.dismiss')}
                        >
                            {t('server-overview:operations.completion.dismiss')}
                        </button>
                    </div>
                </Alert>
            ) : null}

            {/* Minecraft boot-failure quick-fixes — same detection as the
                console (this page also streams console output). */}
            <ServerBootFixPrompts
                serverId={serverId}
                eulaRequired={eulaRequired}
                javaIssue={javaIssue}
                canFixEula={canRestart}
                canFixJava={canEditStartup}
            />

            <ServerOverviewHero
                server={server}
                state={state}
                statusLabel={statusLabel}
                isRunningState={isRunningState}
                uptime={uptime}
                address={address}
                copied={copied}
                onCopy={handleCopy}
                canPower={canPower}
                canStart={canStart}
                canStop={canStop}
                canRestart={canRestart}
            />

            {/* Quick actions — filtered by permissions */}
            <OverviewQuickActions serverId={serverId} entries={filteredEntries} />

            {/* Plugin home sections that opt into `placement: "before_stats"`
                render above the stats; the rest render after the core sections. */}
            {pluginHomeSections.filter((s) => s.placement === 'before_stats').map(({ key, Component }) => (
                <section key={key}><Component serverId={serverId} serverState={state} /></section>
            ))}

            {/* Stats — visible if overview.stats permission */}
            {canViewStats && (
                <section><ServerResourceCards resources={resources} plan={server.plan ?? (server.limits ? { ram: server.limits.memory, cpu: server.limits.cpu, disk: server.limits.disk } : null)} isLoading={!resources} live={isRunningState} /></section>
            )}

            {/* Variables — only if user has startup.read permission */}
            {canViewStartup && (
                <section><ServerVariables serverId={serverId} canEdit={canEditStartup} /></section>
            )}

            {/* Remaining plugin home sections (default placement) — grouped at the end. */}
            {pluginHomeSections.filter((s) => s.placement !== 'before_stats').map(({ key, Component }) => (
                <section key={key}><Component serverId={serverId} serverState={state} /></section>
            ))}

            {/* Server info — only if owner or has settings permission */}
            {canViewConfig && (
                <section><ServerInfoCard server={server} /></section>
            )}

            {/* Rename + reinstall actions */}
            {(canRename || canReinstall) && (
                <section>
                    <ServerSettingsActions
                        server={server}
                        canRename={canRename}
                        canReinstall={canReinstall}
                    />
                </section>
            )}
        </m.div>
    );
}
