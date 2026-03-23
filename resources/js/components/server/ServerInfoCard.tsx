import { useTranslation } from 'react-i18next';
import { formatBytes, formatDate } from '@/utils/format';
import type { ServerInfoCardProps } from '@/components/server/ServerInfoCard.props';

function InfoTile({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div style={{
            background: 'rgba(255,255,255,0.04)',
            borderRadius: 10,
            padding: 14,
        }}>
            <div style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 4 }}>{label}</div>
            <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--color-text-primary)' }}>
                {children}
            </div>
        </div>
    );
}

export function ServerInfoCard({ server }: ServerInfoCardProps) {
    const { t } = useTranslation();

    const ramLabel = server.plan?.ram ? formatBytes(server.plan.ram * 1024 * 1024) : '-';
    const cpuLabel = server.plan?.cpu ? `${server.plan.cpu}%` : '-';
    const diskLabel = server.plan?.disk ? formatBytes(server.plan.disk * 1024 * 1024) : '-';

    return (
        <div style={{
            background: 'var(--color-surface)',
            border: '1px solid rgba(255,255,255,0.06)',
            borderRadius: 12,
            padding: 20,
        }}>
            <h3 style={{ fontSize: 16, fontWeight: 600, color: 'var(--color-text-primary)', marginBottom: 16 }}>
                {t('servers.detail.info')}
            </h3>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {/* Game */}
                {server.egg && (
                    <InfoTile label={t('servers.detail.game')}>
                        <div className="flex items-center gap-2">
                            {server.egg.banner_image && (
                                <img src={server.egg.banner_image} alt="" className="h-5 w-5 rounded object-cover" />
                            )}
                            {server.egg.name}
                        </div>
                    </InfoTile>
                )}

                {/* Plan */}
                {server.plan && (
                    <InfoTile label={t('servers.detail.plan')}>
                        {server.plan.name}
                    </InfoTile>
                )}

                {/* Resources — badges */}
                <InfoTile label={t('servers.detail.allocated')}>
                    <div className="flex flex-wrap gap-1.5">
                        <span className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                            style={{ background: 'rgba(var(--color-info-rgb), 0.12)', color: 'var(--color-info)' }}>
                            {ramLabel}
                        </span>
                        <span className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                            style={{ background: 'rgba(var(--color-primary-rgb), 0.12)', color: 'var(--color-primary-hover)' }}>
                            {cpuLabel}
                        </span>
                        <span className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                            style={{ background: 'rgba(var(--color-accent-rgb), 0.12)', color: 'var(--color-accent)' }}>
                            {diskLabel}
                        </span>
                    </div>
                </InfoTile>

                {/* Created */}
                <InfoTile label={t('servers.detail.created')}>
                    <span style={{ fontSize: 13, fontWeight: 400, opacity: 0.7 }}>
                        {formatDate(server.created_at)}
                    </span>
                </InfoTile>
            </div>
        </div>
    );
}
