import type { Database } from '@/types/Database';
import { request } from '@/services/http';

export async function fetchDatabases(serverId: number): Promise<Database[]> {
    const { data } = await request<{ data: Database[] }>(`/api/servers/${serverId}/databases`);
    return data;
}

export async function createDatabase(serverId: number, database: string, remote: string): Promise<Database> {
    const { data } = await request<{ data: Database }>(`/api/servers/${serverId}/databases`, {
        method: 'POST',
        body: JSON.stringify({ database, remote }),
    });
    return data;
}

export async function rotateDatabasePassword(serverId: number, databaseId: string): Promise<Database> {
    const { data } = await request<{ data: Database }>(`/api/servers/${serverId}/databases/${databaseId}/rotate-password`, {
        method: 'POST',
    });
    return data;
}

export async function deleteDatabase(serverId: number, databaseId: string): Promise<void> {
    await request(`/api/servers/${serverId}/databases/${databaseId}`, { method: 'DELETE' });
}
