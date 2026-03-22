import { useTranslation } from 'react-i18next';
import { Card } from '@/components/ui/Card';
import { StatBar } from '@/components/ui/StatBar';
import { formatBytes, formatCpu } from '@/utils/format';
import type { ServerResourceCardsProps } from '@/components/server/ServerResourceCards.props';

function Skeleton() {
    return (
        <div className="animate-pulse space-y-3 p-4">
            <div className="h-4 w-20 rounded bg-slate-700" />
            <div className="h-6 w-28 rounded bg-slate-700" />
            <div className="h-2 w-full rounded bg-slate-700" />
        </div>
    );
}

export function ServerResourceCards({ resources, plan, isLoading }: ServerResourceCardsProps) {
    const { t } = useTranslation();

    if (isLoading) {
        return (
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i}><Skeleton /></Card>
                ))}
            </div>
        );
    }

    const cpu = resources?.cpu ?? 0;
    const memBytes = resources?.memory_bytes ?? 0;
    const diskBytes = resources?.disk_bytes ?? 0;
    const netRx = resources?.network_rx ?? 0;
    const netTx = resources?.network_tx ?? 0;

    const ramMax = plan?.ram ? plan.ram * 1024 * 1024 : undefined;
    const diskMax = plan?.disk ? plan.disk * 1024 * 1024 : undefined;

    return (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {/* CPU */}
            <Card className="p-4">
                <div className="mb-2 flex items-center gap-2">
                    <svg className="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                    </svg>
                    <span className="text-sm font-medium text-slate-400">{t('servers.resources.cpu')}</span>
                </div>
                <p className="mb-2 text-lg font-semibold text-white">{formatCpu(cpu)}</p>
                <StatBar label="" value={cpu} max={100} formatted={formatCpu(cpu)} />
            </Card>

            {/* Memory */}
            <Card className="p-4">
                <div className="mb-2 flex items-center gap-2">
                    <svg className="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span className="text-sm font-medium text-slate-400">{t('servers.resources.memory')}</span>
                </div>
                <p className="mb-2 text-lg font-semibold text-white">{formatBytes(memBytes)}</p>
                {ramMax ? (
                    <StatBar label="" value={memBytes} max={ramMax} formatted={`${formatBytes(memBytes)} / ${formatBytes(ramMax)}`} />
                ) : (
                    <StatBar label="" value={0} max={100} formatted={formatBytes(memBytes)} />
                )}
            </Card>

            {/* Disk */}
            <Card className="p-4">
                <div className="mb-2 flex items-center gap-2">
                    <svg className="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                    </svg>
                    <span className="text-sm font-medium text-slate-400">{t('servers.resources.disk')}</span>
                </div>
                <p className="mb-2 text-lg font-semibold text-white">{formatBytes(diskBytes)}</p>
                {diskMax ? (
                    <StatBar label="" value={diskBytes} max={diskMax} formatted={`${formatBytes(diskBytes)} / ${formatBytes(diskMax)}`} />
                ) : (
                    <StatBar label="" value={0} max={100} formatted={formatBytes(diskBytes)} />
                )}
            </Card>

            {/* Network */}
            <Card className="p-4">
                <div className="mb-2 flex items-center gap-2">
                    <svg className="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0" />
                    </svg>
                    <span className="text-sm font-medium text-slate-400">{t('servers.resources.network')}</span>
                </div>
                <div className="space-y-1">
                    <p className="text-sm text-slate-300">
                        <span className="text-slate-500">{t('servers.resources.download')}:</span>{' '}
                        <span className="font-medium text-white">{formatBytes(netRx)}</span>
                    </p>
                    <p className="text-sm text-slate-300">
                        <span className="text-slate-500">{t('servers.resources.upload')}:</span>{' '}
                        <span className="font-medium text-white">{formatBytes(netTx)}</span>
                    </p>
                </div>
            </Card>
        </div>
    );
}
