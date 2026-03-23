import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchDatabases, createDatabase, rotateDatabasePassword, deleteDatabase } from '@/services/databaseApi';

export function useDatabases(serverId: number) {
    const queryClient = useQueryClient();
    const queryKey = ['servers', serverId, 'databases'];

    const list = useQuery({
        queryKey,
        queryFn: () => fetchDatabases(serverId),
        staleTime: 120_000,
        enabled: serverId > 0,
    });

    const create = useMutation({
        mutationFn: (data: { database: string; remote: string }) => createDatabase(serverId, data.database, data.remote),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const rotate = useMutation({
        mutationFn: (databaseId: string) => rotateDatabasePassword(serverId, databaseId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const remove = useMutation({
        mutationFn: (databaseId: string) => deleteDatabase(serverId, databaseId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    return { ...list, create, rotate, remove };
}
