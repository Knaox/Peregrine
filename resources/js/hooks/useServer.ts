import { useQuery } from '@tanstack/react-query';
import { fetchServer } from '@/services/serverApi';

export function useServer(id: number) {
    return useQuery({
        queryKey: ['servers', id],
        queryFn: () => fetchServer(id),
        staleTime: 30_000,
        enabled: id > 0,
    });
}
