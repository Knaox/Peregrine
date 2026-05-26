import { useQuery } from '@tanstack/react-query';
import { api, BASE } from './shared';
import type { ServerPlayers } from './types';

const POLL_MS = 30_000;

/**
 * Live player count for a server. We always fetch once (even when stopped) so
 * the card can hide itself for unsupported games / non-whitelisted eggs — that
 * verdict is computed server-side without any network query. `running` (the
 * host's live WS power state) is forwarded to the backend, which then reports
 * "offline" without firing the slow query when the server isn't running, and
 * also drives polling.
 */
export function useServerPlayers(serverId: number, running = true) {
    return useQuery({
        queryKey: ['pc-players', serverId, running],
        queryFn: () =>
            api<{ data: ServerPlayers }>(
                `${BASE}/servers/${serverId}/players?running=${running ? 1 : 0}`,
            ).then((r) => r.data),
        enabled: serverId > 0,
        refetchInterval: running ? POLL_MS : false,
        refetchIntervalInBackground: false,
        refetchOnWindowFocus: true,
        staleTime: 15_000,
    });
}
