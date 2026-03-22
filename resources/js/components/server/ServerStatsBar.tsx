import { useTranslation } from 'react-i18next';
import { StatBar } from '@/components/ui/StatBar';
import { Spinner } from '@/components/ui/Spinner';
import { formatBytes, formatCpu } from '@/utils/format';
import type { ServerStatsBarProps } from '@/components/server/ServerStatsBar.props';

export function ServerStatsBar({ stats, isLoading }: ServerStatsBarProps) {
    const { t } = useTranslation();

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-3">
                <Spinner size="sm" />
            </div>
        );
    }

    if (!stats) {
        return (
            <p className="text-xs text-slate-500 py-2">
                {t('servers.list.stats_unavailable')}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            <StatBar
                label="CPU"
                value={stats.cpu}
                max={100}
                formatted={formatCpu(stats.cpu)}
            />
            <StatBar
                label="RAM"
                value={stats.memory_bytes}
                max={stats.memory_bytes * 2}
                formatted={formatBytes(stats.memory_bytes)}
            />
            <StatBar
                label="Disk"
                value={stats.disk_bytes}
                max={stats.disk_bytes * 2}
                formatted={formatBytes(stats.disk_bytes)}
            />
        </div>
    );
}
