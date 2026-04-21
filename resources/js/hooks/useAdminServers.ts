import { useQuery } from '@tanstack/react-query';
import { fetchAdminServers } from '@/services/adminApi';
import type { AdminServersFilters } from '@/types/AdminServer';

export function useAdminServers(filters: AdminServersFilters) {
    return useQuery({
        queryKey: ['admin-servers', filters],
        queryFn: () => fetchAdminServers(filters),
        staleTime: 30_000,
    });
}
