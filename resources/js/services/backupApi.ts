import type { Backup } from '@/types/Backup';
import { request } from '@/services/http';

export async function fetchBackups(serverId: number): Promise<Backup[]> {
    const { data } = await request<{ data: Backup[] }>(`/api/servers/${serverId}/backups`);
    return data;
}

export async function createBackup(serverId: number, name?: string, ignored?: string, isLocked = false): Promise<Backup> {
    const { data } = await request<{ data: Backup }>(`/api/servers/${serverId}/backups`, {
        method: 'POST',
        body: JSON.stringify({ name, ignored, is_locked: isLocked }),
    });
    return data;
}

export async function getBackupDownloadUrl(serverId: number, backupId: string): Promise<string> {
    const { data } = await request<{ data: { url: string } }>(`/api/servers/${serverId}/backups/${backupId}/download`);
    return data.url;
}

export async function toggleBackupLock(serverId: number, backupId: string): Promise<Backup> {
    const { data } = await request<{ data: Backup }>(`/api/servers/${serverId}/backups/${backupId}/lock`, {
        method: 'POST',
    });
    return data;
}

export async function restoreBackup(serverId: number, backupId: string, truncate = false): Promise<void> {
    await request(`/api/servers/${serverId}/backups/${backupId}/restore`, {
        method: 'POST',
        body: JSON.stringify({ truncate }),
    });
}

export async function deleteBackup(serverId: number, backupId: string): Promise<void> {
    await request(`/api/servers/${serverId}/backups/${backupId}`, { method: 'DELETE' });
}
