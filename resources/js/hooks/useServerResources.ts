import { useQuery } from '@tanstack/react-query';
import { fetchServerResources } from '@/services/serverApi';

export function useServerResources(id: number) {
    return useQuery({
        queryKey: ['servers', id, 'resources'],
        queryFn: () => fetchServerResources(id),
        refetchInterval: 5_000,
        staleTime: 3_000,
        enabled: id > 0,
    });
}
