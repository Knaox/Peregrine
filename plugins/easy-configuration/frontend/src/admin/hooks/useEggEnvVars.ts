import { useQuery } from '@tanstack/react-query';
import { api, BASE } from '../../shared';

interface EggEnvVarOption {
    env_variable: string;
    name: string;
    default: string;
}

/** Env variable names for an egg, for the template editor's "link to env var" autocomplete. */
export function useEggEnvVars(eggId: number | null) {
    return useQuery({
        queryKey: ['ec-admin-egg-envvars', eggId],
        enabled: eggId !== null && eggId > 0,
        staleTime: 5 * 60_000,
        queryFn: () => api<{ data: EggEnvVarOption[] }>(`${BASE}/admin/eggs/${eggId ?? 0}/env-vars`).then((response) => response.data),
    });
}
