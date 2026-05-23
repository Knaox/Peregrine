import { useQuery } from '@tanstack/react-query';
import { api, BASE } from '../../shared';

interface EnvVarOption {
    env_variable: string;
    name: string;
    server_value: string | null;
}

/** Env variable names for a server's egg, for the "link to env var" autocomplete. */
export function useServerEnvVars(serverId: number | null) {
    return useQuery({
        queryKey: ['ec-admin-server-envvars', serverId],
        enabled: serverId !== null && serverId > 0,
        staleTime: 5 * 60_000,
        queryFn: () =>
            api<{ data: EnvVarOption[] }>(`${BASE}/admin/servers/${serverId ?? 0}/env-vars`).then((response) => response.data),
    });
}
