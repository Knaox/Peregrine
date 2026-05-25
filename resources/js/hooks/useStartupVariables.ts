import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchStartupVariables, updateStartupVariables, type StartupVariableUpdate } from '@/services/serverApi';

export function useStartupVariables(serverId: number) {
    const queryClient = useQueryClient();

    const { data: variables, isLoading } = useQuery({
        queryKey: ['servers', serverId, 'startup'],
        queryFn: () => fetchStartupVariables(serverId),
        staleTime: 60_000,
        enabled: serverId > 0,
    });

    const mutation = useMutation({
        mutationFn: (vars: StartupVariableUpdate[]) => updateStartupVariables(serverId, vars),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['servers', serverId, 'startup'] });
        },
    });

    return {
        variables: variables ?? [],
        isLoading,
        saveVariables: mutation.mutateAsync,
        isSaving: mutation.isPending,
    };
}
