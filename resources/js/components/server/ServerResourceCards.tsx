import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { StatBar } from '@/components/ui/StatBar';
import { formatBytes, formatCpu } from '@/utils/format';
import type { ServerResourceCardsProps } from '@/components/server/ServerResourceCards.props';

function SkeletonCard() {
    return (
        <div style={{
            background: 'var(--color-surface)',
            border: '1px solid rgba(255,255,255,0.06)',
            borderRadius: 12,
            padding: 20,
        }}>
            <div className="space-y-3">
                <div className="h-4 w-20 rounded skeleton-shimmer" />
                <div className="h-7 w-28 rounded skeleton-shimmer" />
                <div className="h-1 w-full rounded skeleton-shimmer" />
            </div>
        </div>
    );
}

/* Icon in colored circle */
function IconCircle({ children, color }: { children: React.ReactNode; color: string }) {
    return (
        <div style={{
            background: `${color}1a`,
            borderRadius: 10,
            padding: 8,
            display: 'inline-flex',
            color,
        }}>
            {children}
        </div>
    );
}

const stagger = { animate: { transition: { staggerChildren: 0.08 } } };
const fadeUp = {
    initial: { opacity: 0, y: 16 },
    animate: { opacity: 1, y: 0, transition: { duration: 0.35, ease: 'easeOut' as const } },
};

export function ServerResourceCards({ resources, plan, isLoading }: ServerResourceCardsProps) {
    const { t } = useTranslation();

    if (isLoading) {
        return (
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => <SkeletonCard key={i} />)}
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

    const cardStyle: React.CSSProperties = {
        background: 'var(--color-surface)',
        border: '1px solid rgba(255,255,255,0.06)',
        borderRadius: 12,
        padding: 20,
        transition: 'all 150ms ease',
    };

    return (
        <m.div variants={stagger} initial="initial" animate="animate" className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {/* CPU */}
            <m.div variants={fadeUp} style={cardStyle} className="hover:-translate-y-0.5 hover:shadow-lg hover:border-white/[0.12]">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-primary)">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                    </IconCircle>
                    <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--color-text-muted)' }}>{t('servers.resources.cpu')}</span>
                </div>
                <p style={{ fontSize: 28, fontWeight: 700, letterSpacing: '-0.5px', color: 'var(--color-text-primary)' }} className="mb-3">
                    {formatCpu(cpu)}
                </p>
                <StatBar label="" value={cpu} max={100} formatted="" />
            </m.div>

            {/* Memory */}
            <m.div variants={fadeUp} style={cardStyle} className="hover:-translate-y-0.5 hover:shadow-lg hover:border-white/[0.12]">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-info)">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </IconCircle>
                    <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--color-text-muted)' }}>{t('servers.resources.memory')}</span>
                </div>
                <p style={{ fontSize: 28, fontWeight: 700, letterSpacing: '-0.5px', color: 'var(--color-text-primary)' }} className="mb-3">
                    {formatBytes(memBytes)}
                </p>
                {ramMax ? (
                    <StatBar label="" value={memBytes} max={ramMax} formatted={`/ ${formatBytes(ramMax)}`} />
                ) : (
                    <StatBar label="" value={0} max={100} formatted="" />
                )}
            </m.div>

            {/* Disk */}
            <m.div variants={fadeUp} style={cardStyle} className="hover:-translate-y-0.5 hover:shadow-lg hover:border-white/[0.12]">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-accent)">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </IconCircle>
                    <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--color-text-muted)' }}>{t('servers.resources.disk')}</span>
                </div>
                <p style={{ fontSize: 28, fontWeight: 700, letterSpacing: '-0.5px', color: 'var(--color-text-primary)' }} className="mb-3">
                    {formatBytes(diskBytes)}
                </p>
                {diskMax ? (
                    <StatBar label="" value={diskBytes} max={diskMax} formatted={`/ ${formatBytes(diskMax)}`} />
                ) : (
                    <StatBar label="" value={0} max={100} formatted="" />
                )}
            </m.div>

            {/* Network — dual bars RX/TX */}
            <m.div variants={fadeUp} style={cardStyle} className="hover:-translate-y-0.5 hover:shadow-lg hover:border-white/[0.12]">
                <div className="mb-3 flex items-center gap-3">
                    <IconCircle color="var(--color-info)">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0" />
                        </svg>
                    </IconCircle>
                    <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--color-text-muted)' }}>{t('servers.resources.network')}</span>
                </div>
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <span className="flex items-center gap-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                            {t('servers.resources.download')}
                        </span>
                        <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text-primary)' }}>{formatBytes(netRx)}</span>
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="flex items-center gap-1.5 text-xs" style={{ color: 'var(--color-text-muted)' }}>
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            {t('servers.resources.upload')}
                        </span>
                        <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text-primary)' }}>{formatBytes(netTx)}</span>
                    </div>
                </div>
            </m.div>
        </m.div>
    );
}
