import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { useServer } from '@/hooks/useServer';
import { useServerResources } from '@/hooks/useServerResources';
import { Spinner } from '@/components/ui/Spinner';
import { StatusDot } from '@/components/ui/StatusDot';
import { Badge } from '@/components/ui/Badge';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { ServerResourceCards } from '@/components/server/ServerResourceCards';
import { ServerInfoCard } from '@/components/server/ServerInfoCard';

const CopyIcon = (
    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
    </svg>
);
const CheckIcon = (
    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
    </svg>
);
const GlobeIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
    </svg>
);
const EggIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <circle cx="12" cy="12" r="3" /><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
    </svg>
);

type ServerState = 'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';

const statusColorMap: Record<string, 'green' | 'gray' | 'red' | 'orange'> = {
    running: 'green',
    active: 'green',
    stopped: 'gray',
    offline: 'red',
    suspended: 'orange',
    terminated: 'red',
    starting: 'orange',
};

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

    const state = (resources?.state ?? server.status) as ServerState;
    const statusColor = statusColorMap[state] ?? 'gray';
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
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="space-y-6"
        >
            {/* Hero banner */}
            <div className="relative overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-glass-border)]">
                <div className="relative h-64">
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    ) : (
                        <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-surface-hover)] to-[var(--color-background)]" />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-[var(--color-background)]/60 to-transparent" />

                    <div className="relative flex h-full flex-col justify-between p-6">
                        {/* Status badge top-left */}
                        <div className="flex items-center gap-2">
                            <div className="inline-flex items-center gap-2 rounded-[var(--radius-full)] backdrop-blur-md bg-white/10 border border-white/10 px-3 py-1">
                                <StatusDot status={state} size="sm" />
                                <Badge color={statusColor} className="bg-transparent border-0 p-0">
                                    {statusLabel}
                                </Badge>
                            </div>
                        </div>

                        {/* Bottom: name + pills + power */}
                        <div className="space-y-3">
                            <h1 className="text-4xl font-extrabold text-white drop-shadow-lg">
                                {server.name}
                            </h1>

                            <div className="flex flex-wrap items-center gap-3">
                                {address && (
                                    <button
                                        type="button"
                                        onClick={handleCopy}
                                        className={clsx(
                                            'inline-flex items-center gap-2 rounded-[var(--radius-full)]',
                                            'backdrop-blur-md bg-white/10 border border-white/10',
                                            'px-3 py-1.5 text-sm text-white',
                                            'transition-all duration-[var(--transition-base)]',
                                            'hover:bg-white/20',
                                        )}
                                    >
                                        {GlobeIcon}
                                        <span>{copied ? t('servers.list.copied') : address}</span>
                                        <span className={clsx(
                                            'transition-opacity duration-200',
                                            copied ? 'opacity-0' : 'opacity-60',
                                        )}>
                                            {CopyIcon}
                                        </span>
                                        <span className={clsx(
                                            'absolute right-3 transition-opacity duration-200',
                                            copied ? 'opacity-100' : 'opacity-0',
                                        )}>
                                            {CheckIcon}
                                        </span>
                                    </button>
                                )}
                                {server.egg && (
                                    <span className={clsx(
                                        'inline-flex items-center gap-1.5 rounded-[var(--radius-full)]',
                                        'backdrop-blur-md bg-white/10 border border-white/10',
                                        'px-3 py-1.5 text-sm text-white',
                                    )}>
                                        {EggIcon}
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
        </m.div>
    );
}
