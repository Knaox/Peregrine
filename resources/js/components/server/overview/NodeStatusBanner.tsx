import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { useNodeStatus } from '@/hooks/useNodeStatus';
import { useNamespace } from '@/i18n/useNamespace';
import type { NodeHealthStatusValue } from '@/types/NodeStatus';
import type { NodeStatusBannerProps } from '@/components/server/overview/NodeStatusBanner.props';

/**
 * Friendly banner shown at the top of the server overview ONLY when the
 * node hosting the server is in trouble (unreachable / slow / server
 * unreachable on it / failing operations / maintenance). Healthy nodes
 * render nothing.
 *
 * Purely internal problems (auth_failed, unknown) stay admin-side — a
 * player can't act on them and the banner would cry wolf.
 */
const BANNER_STATUSES: ReadonlySet<NodeHealthStatusValue> = new Set([
    'unreachable',
    'degraded',
    'server_unreachable',
    'server_errors',
    'maintenance',
]);

export function NodeStatusBanner({ serverId }: NodeStatusBannerProps) {
    useNamespace(['server-overview'] as const);
    const { t } = useTranslation();
    const { data } = useNodeStatus(serverId);

    if (!data?.node || !BANNER_STATUSES.has(data.health.status)) return null;

    const { status, severity, latency_ms } = data.health;
    const variant = severity === 'critical' ? 'error' : 'warning';

    return (
        <Alert variant={variant}>
            <div className='flex flex-col gap-0.5'>
                <span className='font-semibold'>
                    {t(`server-overview:node.banner.${status}_title`)}
                </span>
                <span className='text-[13px]' style={{ color: 'var(--color-text-primary)', opacity: 0.85 }}>
                    {t(`server-overview:node.banner.${status}_body`, {
                        node: data.node.name,
                        latency: latency_ms ?? '—',
                    })}
                </span>
            </div>
        </Alert>
    );
}
