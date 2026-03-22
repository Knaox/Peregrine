import { Link } from 'react-router-dom';
import { Card } from '@/components/ui/Card';
import { ServerStatusBadge } from '@/components/server/ServerStatusBadge';
import { ServerStatsBar } from '@/components/server/ServerStatsBar';
import { ServerQuickActions } from '@/components/server/ServerQuickActions';
import type { ServerCardProps } from '@/components/server/ServerCard.props';

export function ServerCard({ server, stats, onPower, isPowerPending }: ServerCardProps) {
    return (
        <Card className="p-5">
            {/* Header: name + status */}
            <div className="mb-3 flex items-start justify-between gap-2">
                <Link
                    to={`/servers/${server.id}`}
                    className="truncate font-semibold text-white hover:text-orange-400 transition-colors"
                >
                    {server.name}
                </Link>
                <ServerStatusBadge status={server.status} />
            </div>

            {/* Subtitle: egg + plan */}
            <div className="mb-4">
                {server.egg && (
                    <p className="text-sm text-slate-400">{server.egg.name}</p>
                )}
                {server.plan && (
                    <p className="mt-0.5 text-xs text-slate-500">{server.plan.name}</p>
                )}
            </div>

            {/* Stats */}
            <div className="mb-3">
                <ServerStatsBar stats={stats} />
            </div>

            {/* Actions */}
            <div className="flex justify-end">
                <ServerQuickActions
                    serverId={server.id}
                    state={stats?.state}
                    onPower={onPower}
                    isPending={isPowerPending}
                />
            </div>
        </Card>
    );
}
