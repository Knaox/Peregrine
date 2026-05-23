import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, BASE } from '../../shared';

export interface BoostParamRef {
    file_id: string;
    section: string | null;
    key: string;
    max_cap?: number | null;
    /** Divide by the multiplier instead of multiplying (per-parameter deboost). */
    invert?: boolean;
    original_value?: string;
    boosted_value?: string;
}

export type Recurrence = 'daily' | 'weekly' | 'monthly';

export interface Boost {
    id: number;
    template_id: string;
    multiplier: number;
    start_at: string;
    end_at: string;
    recurrence: Recurrence | null;
    recurrence_until: string | null;
    /** 'cancelling' is a transient state while the async stop/restore/start runs. */
    status: 'pending' | 'active' | 'cancelling';
    parameters: BoostParamRef[];
}

export interface BoostHistoryRow {
    id: number;
    multiplier: number;
    start_at: string;
    end_at: string;
    final_status: string;
    parameters: BoostParamRef[];
}

export interface CreateBoostPayload {
    template_id: string;
    multiplier: number;
    start_at: string;
    end_at: string;
    recurrence: Recurrence | null;
    recurrence_until: string | null;
    parameters: { file_id: string; section: string | null; key: string; max_cap: number | null; invert: boolean }[];
}

export function useBoosts(serverId: number) {
    return useQuery({
        queryKey: ['ec-boosts', serverId],
        queryFn: () => api<{ data: Boost[] }>(`${BASE}/servers/${serverId}/boosts`).then((response) => response.data),
        // Poll briefly ONLY while a cancellation is in flight (transient): the row
        // then drops by itself once the async stop/restore/start job deletes the
        // boost, so the UI no longer freezes on "cancelling" until a manual refresh.
        refetchInterval: (query) => ((query.state.data ?? []).some((boost) => boost.status === 'cancelling') ? 3000 : false),
    });
}

export function useBoostHistory(serverId: number, enabled: boolean) {
    return useQuery({
        queryKey: ['ec-boost-history', serverId],
        enabled,
        queryFn: () => api<{ data: BoostHistoryRow[] }>(`${BASE}/servers/${serverId}/boosts/history`).then((response) => response.data),
    });
}

export function useCreateBoost(serverId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: CreateBoostPayload) =>
            api<{ data: Boost }>(`${BASE}/servers/${serverId}/boosts`, { method: 'POST', body: JSON.stringify(payload) }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['ec-boosts', serverId] });
            void queryClient.invalidateQueries({ queryKey: ['ec-config', serverId] });
        },
    });
}

export function useUpdateBoost(serverId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ boostId, payload }: { boostId: number; payload: CreateBoostPayload }) =>
            api<{ data: Boost }>(`${BASE}/servers/${serverId}/boosts/${boostId}`, { method: 'PUT', body: JSON.stringify(payload) }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['ec-boosts', serverId] });
            void queryClient.invalidateQueries({ queryKey: ['ec-config', serverId] });
        },
    });
}

export function useCancelBoost(serverId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (boostId: number) => api(`${BASE}/servers/${serverId}/boosts/${boostId}`, { method: 'DELETE' }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['ec-boosts', serverId] });
            void queryClient.invalidateQueries({ queryKey: ['ec-config', serverId] });
        },
    });
}
