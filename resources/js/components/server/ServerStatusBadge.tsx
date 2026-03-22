import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/Badge';
import type { BadgeProps } from '@/components/ui/Badge.props';
import type { Server } from '@/types/Server';
import type { ServerStatusBadgeProps } from '@/components/server/ServerStatusBadge.props';

const statusColorMap: Record<Server['status'], NonNullable<BadgeProps['color']>> = {
    running: 'green',
    active: 'green',
    stopped: 'gray',
    offline: 'red',
    suspended: 'yellow',
    terminated: 'red',
};

export function ServerStatusBadge({ status }: ServerStatusBadgeProps) {
    const { t } = useTranslation();

    return (
        <Badge color={statusColorMap[status]}>
            {t(`servers.status.${status}`)}
        </Badge>
    );
}
