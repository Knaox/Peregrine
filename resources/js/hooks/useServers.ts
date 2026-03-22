import { useQuery } from '@tanstack/react-query';
import { fetchServers } from '@/services/api';

export function useServers() {
    return useQuery({
        queryKey: ['servers'],
        queryFn: fetchServers,
        staleTime: 30 * 1000, // 30 seconds
    });
}
