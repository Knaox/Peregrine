import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchBackups, createBackup, deleteBackup, toggleBackupLock, restoreBackup, getBackupDownloadUrl } from '@/services/backupApi';

export function useBackups(serverId: number) {
    const queryClient = useQueryClient();
    const queryKey = ['servers', serverId, 'backups'];

    const list = useQuery({
        queryKey,
        queryFn: () => fetchBackups(serverId),
        staleTime: 120_000,
        enabled: serverId > 0,
        // Pelican/Wings creates backups asynchronously: a fresh backup lands
        // with `completed_at = null` and is filled in seconds later. There's no
        // inbound webhook syncing that, so poll every 5s while ANY backup is
        // still in progress, then stop — flips the "Creating…" badge to done
        // without a manual refresh.
        refetchInterval: (query) => {
            const backups = query.state.data;
            const hasInProgress = Array.isArray(backups) && backups.some((b) => !b.completed_at);
            return hasInProgress ? 5_000 : false;
        },
    });

    const create = useMutation({
        mutationFn: (data: { name?: string; ignored?: string; isLocked?: boolean }) =>
            createBackup(serverId, data.name, data.ignored, data.isLocked),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const remove = useMutation({
        mutationFn: (backupId: string) => deleteBackup(serverId, backupId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const lock = useMutation({
        mutationFn: (backupId: string) => toggleBackupLock(serverId, backupId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const restore = useMutation({
        mutationFn: (data: { backupId: string; truncate?: boolean }) =>
            restoreBackup(serverId, data.backupId, data.truncate),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const download = async (backupId: string) => {
        const url = await getBackupDownloadUrl(serverId, backupId);
        window.open(url, '_blank');
    };

    return { ...list, create, remove, lock, restore, download };
}
