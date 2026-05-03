import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { formatBytes, formatCpu } from '@/utils/format';
import { useCountUp } from '@/hooks/useCountUp';
import type { ServerResourceCardsProps } from '@/components/server/ServerResourceCards.props';
import { CircularGauge } from '@/components/server/CircularGauge';

function SkeletonCard() {
    return (
        <div className="rounded-[var(--radius-lg)] p-5" style={{
            background: 'var(--color-surface)', border: '1px solid var(--color-border)',
        }}>
            <div className="space-y-3">
                <div className="h-4 w-20 rounded skeleton-shimmer" />
                <div className="mx-auto h-20 w-20 rounded-full skeleton-shimmer" />
                <div className="h-4 w-28 mx-auto rounded skeleton-shimmer" />
            </div>
        </div>
    );
}

const stagger = { animate: { transition: { staggerChildren: 0.1 } } };
const fadeUp = {
    initial: { opacity: 0, y: 20, scale: 0.95 },
    animate: { opacity: 1, y: 0, scale: 1, transition: { duration: 0.4, ease: [0.34, 1.56, 0.64, 1] as [number, number, number, number] } },
};

export function ServerResourceCards({ resources, plan, isLoading }: ServerResourceCardsProps) {
    const { t } = useTranslation();

    if (isLoading) {
        return (
            <div className="grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 lg:gap-4 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => <SkeletonCard key={i} />)}
            </div>
        );
    }

    const cpu = resources?.cpu ?? 0;
    const memBytes = resources?.memory_bytes ?? 0;
    const diskBytes = resources?.disk_bytes ?? 0;
    const netRx = resources?.network_rx ?? 0;
    const netTx = resources?.network_tx ?? 0;
    const cpuMax = plan?.cpu ?? 100; // Pelican: 100 = 1 core, 500 = 5 cores
    const ramMax = plan?.ram ? plan.ram * 1024 * 1024 : undefined;
    const diskMax = plan?.disk ? plan.disk * 1024 * 1024 : undefined;
    const memPercent = ramMax ? (memBytes / ramMax) * 100 : 0;
    const diskPercent = diskMax ? (diskBytes / diskMax) * 100 : 0;

    return (
        <m.div variants={stagger} initial="initial" animate="animate" className="grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 lg:gap-4 lg:grid-cols-4">
            {/* CPU */}
            <m.div variants={fadeUp} className="hover-lift rounded-[var(--radius-lg)] p-3 sm:p-5 glass-card-enhanced">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-primary)">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </IconCircle>
                    <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>{t('servers.resources.cpu')}</span>
                </div>
                <div className="flex items-center justify-center py-2">
                    <CircularGauge value={cpu} max={cpuMax} color="var(--color-primary)" label={formatCpu(cpu)} sublabel={`/ ${cpuMax}%`} />
                </div>
            </m.div>

            {/* Memory */}
            <m.div variants={fadeUp} className="hover-lift rounded-[var(--radius-lg)] p-3 sm:p-5 glass-card-enhanced">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-info)">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </IconCircle>
                    <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>{t('servers.resources.memory')}</span>
                </div>
                <div className="flex items-center justify-center py-2">
                    <CircularGauge value={memPercent} max={100} color="var(--color-info)" label={formatBytes(memBytes)} sublabel={ramMax ? `/ ${formatBytes(ramMax)}` : undefined} />
                </div>
            </m.div>

            {/* Disk */}
            <m.div variants={fadeUp} className="hover-lift rounded-[var(--radius-lg)] p-3 sm:p-5 glass-card-enhanced">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-accent)">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </IconCircle>
                    <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>{t('servers.resources.disk')}</span>
                </div>
                <div className="flex items-center justify-center py-2">
                    <CircularGauge value={diskPercent} max={100} color="var(--color-accent)" label={formatBytes(diskBytes)} sublabel={diskMax ? `/ ${formatBytes(diskMax)}` : undefined} />
                </div>
            </m.div>

            {/* Network */}
            <m.div variants={fadeUp} className="hover-lift rounded-[var(--radius-lg)] p-3 sm:p-5 glass-card-enhanced">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-success)">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0" />
                        </svg>
                    </IconCircle>
                    <span className="text-xs font-medium" style={{ color: 'var(--color-text-muted)' }}>{t('servers.resources.network')}</span>
                </div>
                <div className="space-y-3 pt-2">
                    <NetworkRow direction="down" label={t('servers.resources.download')} value={netRx} />
                    <NetworkRow direction="up" label={t('servers.resources.upload')} value={netTx} />
                </div>
            </m.div>
        </m.div>
    );
}

function IconCircle({ children, color }: { children: React.ReactNode; color: string }) {
    return (
        <div style={{ background: `${color}1a`, borderRadius: 10, padding: 8, display: 'inline-flex', color }}>
            {children}
        </div>
    );
}

function NetworkRow({ direction, label, value }: { direction: 'up' | 'down'; label: string; value: number }) {
    const animated = useCountUp(value);
    return (
        <div className="flex items-center justify-between">
            <span className="flex items-center gap-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                {direction === 'down' ? (
                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                ) : (
                    <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                )}
                {label}
            </span>
            <span className="text-sm font-bold" style={{ color: 'var(--color-text-primary)' }}>{formatBytes(animated)}</span>
        </div>
    );
}
