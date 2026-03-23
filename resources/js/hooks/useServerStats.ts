import { useQuery } from '@tanstack/react-query';
import { fetchServerStats } from '@/services/serverApi';

/**
 * Polls batch server stats for the dashboard server list.
 * Uses HTTP polling (not WebSocket) because the dashboard shows ALL servers
 * and Wings WebSocket requires a separate JWT per server.
 * Individual server pages use useWingsWebSocket for real-time stats.
 */
export function useServerStats() {
    return useQuery({
        queryKey: ['servers', 'stats'],
        queryFn: fetchServerStats,
        refetchInterval: 10_000,
        staleTime: 8_000,
    });
}
