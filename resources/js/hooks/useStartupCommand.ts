import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchStartupCommand, updateStartupCommand } from '@/services/serverApi';
import type { StartupCommandData } from '@/types/StartupCommand';

/**
 * Read + switch the server's startup command (egg-defined named commands).
 * Switching applies immediately (same behaviour as Pelican's client area)
 * with an optimistic update; the query is re-fetched afterwards to stay
 * truthful to Pelican's state.
 */
export function useStartupCommand(serverId: number) {
    const queryClient = useQueryClient();
    const queryKey = ['servers', serverId, 'startup-command'] as const;

    const query = useQuery({
        queryKey,
        queryFn: () => fetchStartupCommand(serverId),
        staleTime: 60_000,
        enabled: Number.isFinite(serverId) && serverId > 0,
    });

    const mutation = useMutation({
        mutationFn: (name: string) => updateStartupCommand(serverId, name),
        onMutate: async (name: string) => {
            await queryClient.cancelQueries({ queryKey });
            const previous = queryClient.getQueryData<StartupCommandData | null>(queryKey);
            if (previous) {
                const option = previous.options.find((o) => o.name === name);
                if (option) {
                    queryClient.setQueryData<StartupCommandData>(queryKey, {
                        ...previous,
                        current: option.command,
                        current_name: option.name,
                        is_custom: false,
                    });
                }
            }
            return { previous };
        },
        onError: (_error, _name, context) => {
            if (context?.previous !== undefined) {
                queryClient.setQueryData(queryKey, context.previous);
            }
        },
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey });
        },
    });

    return {
        data: query.data,
        isLoading: query.isLoading,
        switchCommand: mutation.mutate,
        isSwitching: mutation.isPending,
        switchFailed: mutation.isError,
        switchSucceeded: mutation.isSuccess,
    };
}
