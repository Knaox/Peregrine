import { useQuery } from '@tanstack/react-query';
import { api, BASE } from '../../shared';
import type { ServerFileEntry } from '../../types';

/**
 * Lists a server's directory contents for the import file browser (admin-gated
 * endpoint). Disabled until a server is picked. Short stale time mirrors the
 * core file manager so a freshly written file shows up on re-open.
 */
export function useServerFiles(serverId: number | null, directory: string) {
    return useQuery({
        queryKey: ['ec-admin-server-files', serverId, directory],
        enabled: serverId !== null && serverId > 0,
        staleTime: 15_000,
        queryFn: () =>
            api<{ data: ServerFileEntry[] }>(
                `${BASE}/admin/servers/${serverId ?? 0}/files?directory=${encodeURIComponent(directory)}`,
            ).then((response) => response.data),
    });
}
