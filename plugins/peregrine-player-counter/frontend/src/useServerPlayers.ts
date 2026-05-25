import { useQuery } from '@tanstack/react-query';
import { api, BASE } from './shared';
import type { ServerPlayers } from './types';

const POLL_MS = 30_000;

/**
 * Live player count for a server. `enabled` is driven by the host's live WS
 * power state — we only fetch (and poll) while the server is actually running,
 * so an offline/starting server never triggers the (slow) query and never
 * competes with the WebSocket handshake on page load.
 */
export function useServerPlayers(serverId: number, enabled = true) {
    return useQuery({
        queryKey: ['pc-players', serverId],
        queryFn: () =>
            api<{ data: ServerPlayers }>(`${BASE}/servers/${serverId}/players`).then((r) => r.data),
        enabled: enabled && serverId > 0,
        refetchInterval: enabled ? POLL_MS : false,
        refetchIntervalInBackground: false,
        refetchOnWindowFocus: true,
        staleTime: 15_000,
    });
}
