import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { GlassCard } from '@/components/ui/GlassCard';
import { StatBar } from '@/components/ui/StatBar';
import { formatBytes, formatCpu } from '@/utils/format';
import type { ServerResourceCardsProps } from '@/components/server/ServerResourceCards.props';

function Skeleton() {
    return (
        <div className="animate-pulse space-y-3 p-4">
            <div className="h-4 w-20 rounded bg-[var(--color-surface-hover)]" />
            <div className="h-6 w-28 rounded bg-[var(--color-surface-hover)]" />
            <div className="h-2 w-full rounded bg-[var(--color-surface-hover)]" />
        </div>
    );
}

const staggerChildren = {
    animate: { transition: { staggerChildren: 0.08 } },
};

const fadeInUp = {
    initial: { opacity: 0, y: 16 },
    animate: { opacity: 1, y: 0, transition: { duration: 0.35, ease: 'easeOut' as const } },
};

export function ServerResourceCards({ resources, plan, isLoading }: ServerResourceCardsProps) {
    const { t } = useTranslation();

    if (isLoading) {
        return (
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <GlassCard key={i}><Skeleton /></GlassCard>
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
        <m.div
            variants={staggerChildren}
            initial="initial"
            animate="animate"
            className="grid grid-cols-2 gap-4 lg:grid-cols-4"
        >
            {/* CPU */}
            <m.div variants={fadeInUp}>
                <GlassCard className="p-4">
                    <div className="mb-2 flex items-center gap-2">
                        <svg className="h-5 w-5 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                        <span className="text-sm font-medium text-[var(--color-text-muted)]">{t('servers.resources.cpu')}</span>
                    </div>
                    <p className="mb-2 text-lg font-bold text-[var(--color-text-primary)]">{formatCpu(cpu)}</p>
                    <StatBar label="" value={cpu} max={100} formatted={formatCpu(cpu)} />
                </GlassCard>
            </m.div>

            {/* Memory */}
            <m.div variants={fadeInUp}>
                <GlassCard className="p-4">
                    <div className="mb-2 flex items-center gap-2">
                        <svg className="h-5 w-5 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span className="text-sm font-medium text-[var(--color-text-muted)]">{t('servers.resources.memory')}</span>
                    </div>
                    <p className="mb-2 text-lg font-bold text-[var(--color-text-primary)]">{formatBytes(memBytes)}</p>
                    {ramMax ? (
                        <StatBar label="" value={memBytes} max={ramMax} formatted={`${formatBytes(memBytes)} / ${formatBytes(ramMax)}`} />
                    ) : (
                        <StatBar label="" value={0} max={100} formatted={formatBytes(memBytes)} />
                    )}
                </GlassCard>
            </m.div>

            {/* Disk */}
            <m.div variants={fadeInUp}>
                <GlassCard className="p-4">
                    <div className="mb-2 flex items-center gap-2">
                        <svg className="h-5 w-5 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                        <span className="text-sm font-medium text-[var(--color-text-muted)]">{t('servers.resources.disk')}</span>
                    </div>
                    <p className="mb-2 text-lg font-bold text-[var(--color-text-primary)]">{formatBytes(diskBytes)}</p>
                    {diskMax ? (
                        <StatBar label="" value={diskBytes} max={diskMax} formatted={`${formatBytes(diskBytes)} / ${formatBytes(diskMax)}`} />
                    ) : (
                        <StatBar label="" value={0} max={100} formatted={formatBytes(diskBytes)} />
                    )}
                </GlassCard>
            </m.div>

            {/* Network */}
            <m.div variants={fadeInUp}>
                <GlassCard className="p-4">
                    <div className="mb-2 flex items-center gap-2">
                        <svg className="h-5 w-5 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0" />
                        </svg>
                        <span className="text-sm font-medium text-[var(--color-text-muted)]">{t('servers.resources.network')}</span>
                    </div>
                    <div className="space-y-1">
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            <span className="text-[var(--color-text-muted)]">{t('servers.resources.download')}:</span>{' '}
                            <span className="font-medium text-[var(--color-text-primary)]">{formatBytes(netRx)}</span>
                        </p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            <span className="text-[var(--color-text-muted)]">{t('servers.resources.upload')}:</span>{' '}
                            <span className="font-medium text-[var(--color-text-primary)]">{formatBytes(netTx)}</span>
                        </p>
                    </div>
                </GlassCard>
            </m.div>
        </m.div>
    );
}
