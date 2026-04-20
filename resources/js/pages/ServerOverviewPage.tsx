import { useState, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { copyToClipboard } from '@/utils/clipboard';
import { useServer } from '@/hooks/useServer';
import { useWingsWebSocket } from '@/hooks/useWingsWebSocket';
import { useSidebarConfig } from '@/hooks/useSidebarConfig';
import { usePluginStore } from '@/plugins/pluginStore';
import { SIDEBAR_ENTRY_PERMISSIONS } from '@/utils/serverPermissions';
import { Spinner } from '@/components/ui/Spinner';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { ServerResourceCards } from '@/components/server/ServerResourceCards';
import { ServerInfoCard } from '@/components/server/ServerInfoCard';
import { ServerVariables } from '@/components/server/ServerVariables';
import { OverviewQuickActions } from '@/components/server/OverviewQuickActions';
import { formatUptime } from '@/utils/format';
import type { SidebarEntry } from '@/hooks/useSidebarConfig';

type ServerState = 'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';

export function ServerOverviewPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const serverId = Number(id);
    const { data: server, isLoading: serverLoading } = useServer(serverId);
    const { resources, serverState: wsState } = useWingsWebSocket(serverId, { stats: true });
    const sidebarConfig = useSidebarConfig();
    const pluginManifests = usePluginStore((s) => s.manifests);
    const [copied, setCopied] = useState(false);

    if (serverLoading || !server) {
        return <div className="flex min-h-[40vh] items-center justify-center"><Spinner size="lg" /></div>;
    }

    const state = (wsState !== 'offline' ? wsState : server.status) as ServerState;
    const statusLabel = t(`servers.status.${state}`);
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;
    const isRunningState = state === 'running' || state === 'active';
    const uptime = resources?.uptime;

    // Permission helpers
    const perms = server.permissions ?? null;
    const isOwner = !server.role || server.role === 'owner' || perms === null;
    const hasPerm = (p: string) => isOwner || (perms?.includes(p) ?? false);

    const canStart = hasPerm('control.start');
    const canStop = hasPerm('control.stop');
    const canRestart = hasPerm('control.restart');
    const canPower = canStart || canStop || canRestart;
    const canViewStats = hasPerm('overview.stats');
    const canViewStartup = hasPerm('startup.read');
    const canEditStartup = hasPerm('startup.update');
    const canViewConfig = hasPerm('overview.server-info') || hasPerm('settings.rename');

    const filteredEntries = useMemo(() => {
        // Merge core + plugin sidebar entries
        const pluginEntries: SidebarEntry[] = [];
        for (const manifest of pluginManifests) {
            for (const se of manifest.server_sidebar_entries ?? []) {
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
    }, [sidebarConfig.entries, pluginManifests, isOwner, perms]);

    const handleCopy = () => {
        if (!address) return;
        void copyToClipboard(address).then(() => { setCopied(true); setTimeout(() => setCopied(false), 1500); });
    };

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.4, 0, 0.2, 1] }} className="space-y-6">

            {/* Hero banner */}
            <m.div initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.5, ease: [0.34, 1.56, 0.64, 1] }}
                className="relative overflow-hidden rounded-[var(--radius-xl)]"
                style={{ border: '1px solid var(--color-border)' }}>
                <div className="relative" style={{ minHeight: 200 }}>
                    {server.egg?.banner_image ? (
                        <m.img src={server.egg.banner_image} alt={server.egg.name}
                            className="absolute inset-0 h-full w-full object-cover"
                            initial={{ scale: 1.08 }} animate={{ scale: 1 }} transition={{ duration: 0.8, ease: 'easeOut' }} />
                    ) : (
                        <div className="absolute inset-0" style={{ background: 'linear-gradient(135deg, var(--color-surface-hover), var(--color-background))' }} />
                    )}
                    <div className="absolute inset-0" style={{
                        background: 'linear-gradient(to bottom, transparent 0%, var(--banner-overlay-soft) 40%, var(--banner-overlay) 70%, var(--color-background) 95%)',
                    }} />
                    <div className="absolute bottom-0 left-1/4 h-40 w-1/2 pointer-events-none"
                        style={{ background: 'radial-gradient(ellipse, rgba(var(--color-primary-rgb), 0.08) 0%, transparent 70%)', filter: 'blur(40px)' }} />

                    <div className="relative flex h-full flex-col justify-between p-4 sm:p-5 md:p-6" style={{ minHeight: 200 }}>
                        {/* Top: status + uptime */}
                        <m.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.2 }}
                            className="flex items-center gap-3 flex-wrap">
                            <span className="relative flex items-center gap-2 rounded-full text-sm font-medium px-3.5 py-1.5"
                                style={{
                                    background: isRunningState ? 'rgba(var(--color-success-rgb), 0.15)' : 'rgba(var(--color-text-secondary-rgb), 0.12)',
                                    color: isRunningState ? 'var(--color-success)' : 'var(--color-text-secondary)',
                                    backdropFilter: 'blur(12px)',
                                    border: `1px solid ${isRunningState ? 'rgba(var(--color-success-rgb), 0.2)' : 'rgba(255,255,255,0.06)'}`,
                                }}>
                                <span className="relative flex h-2.5 w-2.5">
                                    {isRunningState && <span className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" style={{ background: 'var(--color-success)' }} />}
                                    <span className="relative inline-flex h-2.5 w-2.5 rounded-full" style={{ background: isRunningState ? 'var(--color-success)' : 'var(--color-text-muted)' }} />
                                </span>
                                {statusLabel}
                            </span>
                            {isRunningState && uptime != null && uptime > 0 && (
                                <span className="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-mono"
                                    style={{ background: 'rgba(255,255,255,0.08)', backdropFilter: 'blur(8px)', color: 'var(--color-text-secondary)', border: '1px solid rgba(255,255,255,0.06)' }}>
                                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {formatUptime(uptime)}
                                </span>
                            )}
                        </m.div>

                        {/* Bottom: name + actions */}
                        <div className="space-y-3">
                            <m.h1 initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3, duration: 0.5 }}
                                className="text-xl sm:text-3xl md:text-4xl font-extrabold text-white" style={{ textShadow: '0 2px 30px rgba(0,0,0,0.6)' }}>
                                {server.name}
                            </m.h1>
                            <m.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.4 }}
                                className="flex flex-wrap items-center gap-2 sm:gap-3">
                                {address && (
                                    <button type="button" onClick={handleCopy}
                                        className="inline-flex items-center gap-2 rounded-full text-sm text-white cursor-pointer transition-all duration-200 hover:scale-[1.03]"
                                        style={{ background: 'rgba(255,255,255,0.1)', backdropFilter: 'blur(12px)', border: '1px solid rgba(255,255,255,0.15)', padding: '6px 14px' }}
                                        onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.18)'; }}
                                        onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.1)'; }}>
                                        <svg className="h-3.5 w-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" /></svg>
                                        <span>{copied ? t('servers.list.copied') : address}</span>
                                    </button>
                                )}
                                {server.egg && (
                                    <span className="inline-flex items-center gap-1.5 rounded-full text-sm text-white"
                                        style={{ background: 'rgba(255,255,255,0.1)', backdropFilter: 'blur(12px)', border: '1px solid rgba(255,255,255,0.15)', padding: '4px 14px' }}>
                                        {server.egg.banner_image && <img src={server.egg.banner_image} alt="" className="h-4 w-4 rounded object-cover" />}
                                        {server.egg.name}
                                    </span>
                                )}
                                {canPower && (
                                    <div className="sm:ml-auto"><ServerPowerControls serverId={server.id} state={state} canStart={canStart} canStop={canStop} canRestart={canRestart} /></div>
                                )}
                            </m.div>
                        </div>
                    </div>
                </div>
            </m.div>

            {/* Quick actions — filtered by permissions */}
            <OverviewQuickActions serverId={serverId} entries={filteredEntries} />

            {/* Stats — visible if overview.stats permission */}
            {canViewStats && (
                <section><ServerResourceCards resources={resources} plan={server.plan ?? (server.limits ? { ram: server.limits.memory, cpu: server.limits.cpu, disk: server.limits.disk } : null)} isLoading={!resources} /></section>
            )}

            {/* Variables — only if user has startup.read permission */}
            {canViewStartup && (
                <section><ServerVariables serverId={serverId} canEdit={canEditStartup} /></section>
            )}

            {/* Server info — only if owner or has settings permission */}
            {canViewConfig && (
                <section><ServerInfoCard server={server} /></section>
            )}
        </m.div>
    );
}
