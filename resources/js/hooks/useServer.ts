import { useQuery } from '@tanstack/react-query';
import { fetchServer } from '@/services/serverApi';

export function useServer(id: number) {
    return useQuery({
        queryKey: ['servers', id],
        queryFn: () => fetchServer(id),
        staleTime: 120_000, // 2 minutes
        enabled: id > 0,
    });
}
