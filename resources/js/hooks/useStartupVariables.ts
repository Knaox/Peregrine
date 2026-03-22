import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchStartupVariables, updateStartupVariable } from '@/services/serverApi';

export function useStartupVariables(serverId: number) {
    const queryClient = useQueryClient();

    const { data: variables, isLoading } = useQuery({
        queryKey: ['servers', serverId, 'startup'],
        queryFn: () => fetchStartupVariables(serverId),
        staleTime: 60_000,
        enabled: serverId > 0,
    });

    const mutation = useMutation({
        mutationFn: ({ key, value }: { key: string; value: string }) =>
            updateStartupVariable(serverId, key, value),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['servers', serverId, 'startup'] });
        },
    });

    return {
        variables: variables ?? [],
        isLoading,
        updateVariable: mutation.mutate,
        isUpdating: mutation.isPending,
    };
}
