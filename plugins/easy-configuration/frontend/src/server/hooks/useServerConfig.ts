import { useMutation, useQuery } from '@tanstack/react-query';
import { api, BASE } from '../../shared';
import type { ConfigPayload, ServerState } from '../../types';

export interface SaveFilePayload {
    id: string;
    values: { key: string; section: string | null; value: string }[];
}

export function useServerConfig(serverId: number) {
    return useQuery({
        queryKey: ['ec-config', serverId],
        staleTime: Infinity, // loaded once per mount; local edits own the state after that
        queryFn: () => api<{ data: ConfigPayload }>(`${BASE}/servers/${serverId}/config`).then((response) => response.data),
    });
}

export function useServerStatus(serverId: number, enabled: boolean) {
    return useQuery({
        queryKey: ['ec-status', serverId],
        enabled,
        refetchInterval: (query) => (query.state.data?.state === 'offline' ? false : 5000),
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

export function usePower(serverId: number) {
    return useMutation({
        mutationFn: (signal: 'start' | 'stop' | 'restart') =>
            api(`${BASE}/servers/${serverId}/power`, { method: 'POST', body: JSON.stringify({ signal }) }),
    });
}
