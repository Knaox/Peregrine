import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { useServer } from '@/hooks/useServer';
import { useWingsWebSocket } from '@/hooks/useWingsWebSocket';
import { Spinner } from '@/components/ui/Spinner';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { ServerResourceCards } from '@/components/server/ServerResourceCards';
import { ServerInfoCard } from '@/components/server/ServerInfoCard';
import { ServerVariables } from '@/components/server/ServerVariables';

type ServerState = 'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';

export function ServerOverviewPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading: serverLoading } = useServer(serverId);
    const { resources, serverState: wsState } = useWingsWebSocket(serverId, { stats: true });
    const [copied, setCopied] = useState(false);

    if (serverLoading || !server) {
        return (
            <div className="flex min-h-[40vh] items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    const state = (wsState !== 'offline' ? wsState : server.status) as ServerState;
    const statusLabel = t(`servers.status.${state}`);
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;
    const isRunningState = state === 'running' || state === 'active';

    const handleCopy = () => {
        if (!address) return;
        void navigator.clipboard.writeText(address).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="space-y-6"
        >
            {/* Hero banner — taller, smoother blend */}
            <div
                className="relative overflow-hidden"
                style={{ borderRadius: 'var(--radius-lg)', border: '1px solid rgba(255,255,255,0.06)' }}
            >
                <div style={{ minHeight: 280 }} className="relative">
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    ) : (
                        <div
                            className="absolute inset-0"
                            style={{ background: 'linear-gradient(135deg, var(--color-surface-hover), var(--color-background))' }}
                        />
                    )}
                    {/* Smooth gradient overlay — no hard cutoff */}
                    <div
                        className="absolute inset-0"
                        style={{
                            background: 'linear-gradient(to bottom, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.4) 50%, rgba(12,15,26,0.95) 85%, var(--color-background) 100%)',
                        }}
                    />

                    <div className="relative flex h-full flex-col justify-between p-6" style={{ minHeight: 280 }}>
                        {/* Status badge — animated pulse dot */}
                        <div className="flex items-center gap-2">
                            <span
                                className="relative flex items-center gap-2 rounded-full text-sm font-medium px-3 py-1"
                                style={{
                                    background: isRunningState ? 'rgba(var(--color-success-rgb), 0.15)' : 'rgba(var(--color-text-secondary-rgb), 0.15)',
                                    color: isRunningState ? 'var(--color-success)' : 'var(--color-text-secondary)',
                                    backdropFilter: 'blur(8px)',
                                }}
                            >
                                <span className="relative flex h-2.5 w-2.5">
                                    {isRunningState && (
                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                                            style={{ background: 'var(--color-success)' }}
                                        />
                                    )}
                                    <span className="relative inline-flex h-2.5 w-2.5 rounded-full"
                                        style={{ background: isRunningState ? 'var(--color-success)' : 'var(--color-text-muted)' }}
                                    />
                                </span>
                                {statusLabel}
                            </span>
                        </div>

                        {/* Bottom: name + pills + power */}
                        <div className="space-y-3">
                            <h1 className="text-4xl font-extrabold text-white" style={{ textShadow: '0 2px 20px rgba(0,0,0,0.5)' }}>
                                {server.name}
                            </h1>

                            <div className="flex flex-wrap items-center gap-3">
                                {/* Address — copy button */}
                                {address && (
                                    <button
                                        type="button"
                                        onClick={handleCopy}
                                        className="inline-flex items-center gap-2 rounded-full text-sm text-white transition-all duration-150"
                                        style={{
                                            background: 'rgba(255,255,255,0.1)',
                                            backdropFilter: 'blur(12px)',
                                            border: '1px solid rgba(255,255,255,0.15)',
                                            padding: '6px 16px',
                                        }}
                                        onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.18)'; }}
                                        onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.1)'; }}
                                    >
                                        <svg className="h-3.5 w-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
                                        </svg>
                                        <span>{copied ? t('servers.list.copied') : address}</span>
                                    </button>
                                )}

                                {/* Egg badge — glass style */}
                                {server.egg && (
                                    <span
                                        className="inline-flex items-center gap-1.5 rounded-full text-sm text-white"
                                        style={{
                                            background: 'rgba(255,255,255,0.1)',
                                            backdropFilter: 'blur(12px)',
                                            border: '1px solid rgba(255,255,255,0.15)',
                                            padding: '4px 14px',
                                        }}
                                    >
                                        {server.egg.banner_image && (
                                            <img src={server.egg.banner_image} alt="" className="h-4 w-4 rounded object-cover" />
                                        )}
                                        {server.egg.name}
                                    </span>
                                )}

                                <div className="ml-auto">
                                    <ServerPowerControls serverId={server.id} state={state} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Stats cards */}
            <section>
                <ServerResourceCards resources={resources} plan={server.plan} isLoading={!resources} />
            </section>

            {/* Server info */}
            <section>
                <ServerInfoCard server={server} />
            </section>

            {/* Startup variables */}
            <section>
                <ServerVariables serverId={serverId} />
            </section>
        </m.div>
    );
}
