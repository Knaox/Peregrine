import { useTranslation } from 'react-i18next';
import { Card } from '@/components/ui/Card';
import { formatBytes, formatDate } from '@/utils/format';
import type { ServerInfoCardProps } from '@/components/server/ServerInfoCard.props';

interface InfoRowProps {
    label: string;
    value: string;
}

function InfoRow({ label, value }: InfoRowProps) {
    return (
        <div className="flex items-center justify-between py-2">
            <span className="text-sm text-slate-400">{label}</span>
            <span className="text-sm font-medium text-white">{value}</span>
        </div>
    );
}

export function ServerInfoCard({ server }: ServerInfoCardProps) {
    const { t } = useTranslation();

    const ramLabel = server.plan?.ram
        ? formatBytes(server.plan.ram * 1024 * 1024)
        : '-';
    const cpuLabel = server.plan?.cpu
        ? `${server.plan.cpu}%`
        : '-';
    const diskLabel = server.plan?.disk
        ? formatBytes(server.plan.disk * 1024 * 1024)
        : '-';

    return (
        <Card className="p-5">
            <h3 className="mb-3 text-base font-semibold text-white">
                {t('servers.detail.info')}
            </h3>
            <div className="divide-y divide-slate-700">
                {server.egg && (
                    <InfoRow label={t('servers.detail.game')} value={server.egg.name} />
                )}
                {server.plan && (
                    <InfoRow label={t('servers.detail.plan')} value={server.plan.name} />
                )}
                <InfoRow
                    label={t('servers.detail.allocated')}
                    value={`${ramLabel} RAM / ${cpuLabel} CPU / ${diskLabel} Disk`}
                />
                <InfoRow
                    label={t('servers.detail.created')}
                    value={formatDate(server.created_at)}
                />
            </div>
        </Card>
    );
}
