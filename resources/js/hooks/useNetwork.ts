import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchAllocations, addAllocation, updateAllocationNotes, setPrimaryAllocation, deleteAllocation } from '@/services/networkApi';

export function useNetwork(serverId: number) {
    const queryClient = useQueryClient();
    const queryKey = ['servers', serverId, 'network'];

    const list = useQuery({
        queryKey,
        queryFn: () => fetchAllocations(serverId),
        staleTime: 600_000,
        enabled: serverId > 0,
    });

    const updateNotes = useMutation({
        mutationFn: (data: { allocationId: number; notes: string }) =>
            updateAllocationNotes(serverId, data.allocationId, data.notes),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const setPrimary = useMutation({
        mutationFn: (allocationId: number) => setPrimaryAllocation(serverId, allocationId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const remove = useMutation({
        mutationFn: (allocationId: number) => deleteAllocation(serverId, allocationId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const add = useMutation({
        mutationFn: () => addAllocation(serverId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    return { ...list, add, updateNotes, setPrimary, remove };
}
