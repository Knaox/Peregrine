import { useQuery } from '@tanstack/react-query';
import { fetchServerStats } from '@/services/serverApi';

export function useServerStats() {
    return useQuery({
        queryKey: ['servers', 'stats'],
        queryFn: fetchServerStats,
        refetchInterval: 15_000,
        staleTime: 10_000,
    });
}
