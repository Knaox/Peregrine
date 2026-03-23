import type { Allocation } from '@/types/Allocation';
import { request } from '@/services/http';

export async function fetchAllocations(serverId: number): Promise<Allocation[]> {
    const { data } = await request<{ data: Allocation[] }>(`/api/servers/${serverId}/network`);
    return data;
}

export async function updateAllocationNotes(serverId: number, allocationId: number, notes: string): Promise<Allocation> {
    const { data } = await request<{ data: Allocation }>(`/api/servers/${serverId}/network/${allocationId}/notes`, {
        method: 'POST',
        body: JSON.stringify({ notes }),
    });
    return data;
}

export async function setPrimaryAllocation(serverId: number, allocationId: number): Promise<Allocation> {
    const { data } = await request<{ data: Allocation }>(`/api/servers/${serverId}/network/${allocationId}/primary`, {
        method: 'POST',
    });
    return data;
}

export async function addAllocation(serverId: number): Promise<Allocation> {
    const { data } = await request<{ data: Allocation }>(`/api/servers/${serverId}/network`, { method: 'POST' });
    return data;
}

export async function deleteAllocation(serverId: number, allocationId: number): Promise<void> {
    await request(`/api/servers/${serverId}/network/${allocationId}`, { method: 'DELETE' });
}
