import { useQuery } from '@tanstack/react-query';
import { fetchNodeStatus } from '@/services/serverApi';

/**
 * Node placement + Wings health for a server. The backend caches probes
 * for 30s, so the gentle 45s poll here never stampedes the daemon. Shared
 * via the query key by every consumer on the page (banner + info card =
 * one request).
 */
export function useNodeStatus(serverId: number) {
    return useQuery({
        queryKey: ['servers', serverId, 'node-status'],
        queryFn: () => fetchNodeStatus(serverId),
        staleTime: 30_000,
        // The interval poll is the single refresh source; focus-refetch on
        // top of it would just double the traffic.
        refetchInterval: 45_000,
        refetchOnWindowFocus: false,
        enabled: Number.isFinite(serverId) && serverId > 0,
    });
}
