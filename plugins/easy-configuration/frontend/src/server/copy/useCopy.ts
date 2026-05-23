import { useMutation, useQuery } from '@tanstack/react-query';
import { api, BASE } from '../../shared';

export interface CopyTarget {
    id: number;
    identifier: string;
    name: string;
    running: boolean;
    egg: { id: number | null; name: string | null; banner_image: string | null };
}

export interface CopyFilePayload {
    id: string;
    params: { key: string; section: string | null }[];
}

export interface CopyLogRow {
    target_server_id: number;
    status: 'success' | 'failed';
    params_count: number;
    error: string | null;
}

export function useCopyTargets(serverId: number, enabled: boolean) {
    return useQuery({
        queryKey: ['ec-copy-targets', serverId],
        enabled,
        staleTime: 30_000,
        queryFn: () => api<{ data: CopyTarget[] }>(`${BASE}/servers/${serverId}/copy/targets`).then((response) => response.data),
    });
}

export function useStartCopy(serverId: number) {
    return useMutation({
        mutationFn: (payload: { targets: number[]; files: CopyFilePayload[]; copy_boosts: boolean; copy_env_vars: boolean; env_vars: string[] }) =>
            api<{ data: { batch_id: string; targets: number } }>(`${BASE}/servers/${serverId}/copy`, {
                method: 'POST',
                body: JSON.stringify(payload),
            }).then((response) => response.data),
    });
}

export function useCopyLog(serverId: number, batchId: string | null, expected: number) {
    return useQuery({
        queryKey: ['ec-copy-log', serverId, batchId],
        enabled: batchId !== null,
        refetchInterval: (query) => ((query.state.data?.length ?? 0) >= expected ? false : 1500),
        queryFn: () => api<{ data: CopyLogRow[] }>(`${BASE}/servers/${serverId}/copy/log?batch_id=${batchId ?? ''}`).then((response) => response.data),
    });
}
