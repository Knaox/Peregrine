import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, BASE } from '../../shared';
import type { ConfigPayload, ServerState } from '../../types';

export interface SaveFilePayload {
    id: string;
    values: { key: string; section: string | null; value: string; occurrence?: number }[];
}

export function useServerConfig(serverId: number) {
    return useQuery({
        queryKey: ['ec-config', serverId],
        staleTime: Infinity, // loaded once per mount; local edits own the state after that
        queryFn: () => api<{ data: ConfigPayload }>(`${BASE}/servers/${serverId}/config`).then((response) => response.data),
    });
}

/**
 * One-shot initial power state. Live transitions then arrive via the
 * `peregrine:server-power` window event (see useServerPowerState), re-broadcast
 * from the home page's Wings socket — so no polling is needed here.
 */
export function useServerStatus(serverId: number, enabled: boolean) {
    return useQuery({
        queryKey: ['ec-status', serverId],
        enabled,
        staleTime: 30_000,
        queryFn: () => api<{ data: { state: ServerState } }>(`${BASE}/servers/${serverId}/status`).then((response) => response.data),
    });
}

export function useSaveConfig(serverId: number) {
    return useMutation({
        mutationFn: (files: SaveFilePayload[]) =>
            api<{ data: { written: number } }>(`${BASE}/servers/${serverId}/config`, {
                method: 'PUT',
                body: JSON.stringify({ files }),
            }),
    });
}

/**
 * Add a parameter (key + value) to a config file — written into the given
 * section (or flat). `occurrence` targets which copy of a repeatable key to
 * write: passing the count of existing copies makes the backend APPEND a new
 * line (e.g. a 4th `ConfigOverrideItemMaxQuantity`) instead of overwriting the
 * first. On success we refetch so it comes back as an editable field.
 */
export function useAddParameter(serverId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: { fileId: string; key: string; section: string | null; value: string; occurrence: number }) =>
            api<{ data: { written: number } }>(`${BASE}/servers/${serverId}/config`, {
                method: 'PUT',
                body: JSON.stringify({
                    files: [{ id: payload.fileId, values: [{ key: payload.key, section: payload.section, value: payload.value, occurrence: payload.occurrence }] }],
                }),
            }),
        onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['ec-config', serverId] }),
    });
}

export function usePower(serverId: number) {
    return useMutation({
        mutationFn: (signal: 'start' | 'stop' | 'restart') =>
            api(`${BASE}/servers/${serverId}/power`, { method: 'POST', body: JSON.stringify({ signal }) }),
    });
}
