import type { Database } from '@/types/Database';
import { request } from '@/services/http';

export async function fetchDatabases(serverId: number): Promise<Database[]> {
    const { data } = await request<{ data: Database[] }>(`/api/servers/${serverId}/databases`);
    return data;
}

/**
 * Live fetch of a single database including its plaintext password (the list
 * endpoint never carries it). Backs the on-demand "Show password" reveal.
 */
export async function fetchDatabaseCredentials(serverId: number, databaseId: string): Promise<Database> {
    const { data } = await request<{ data: Database }>(`/api/servers/${serverId}/databases/${databaseId}/credentials`);
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
