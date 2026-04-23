import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';

/**
 * Bridges Wings's real-time `install completed` event with Peregrine's
 * cached server query.
 *
 * Wings emits `install completed` instantly when the egg install script
 * finishes, but Peregrine's local DB only knows about the new status once
 * Pelican fires `updated: Server` → its queue worker POSTs the webhook →
 * our worker mirrors it. That round-trip can take a few seconds.
 *
 * Without this hook the user would see "Installation completed" on the
 * overview but stay locked into the install-only navigation until they
 * manually refreshed. We poll the server query every 2s during that
 * window, then stop as soon as the status leaves `provisioning`.
 *
 * Call from any page that already mounts the Wings websocket — typically
 * the overview AND console pages, so whichever the user is on when the
 * install finishes triggers the refresh.
 */
export function useRefetchServerOnInstallComplete(
    serverId: number,
    installCompleted: boolean,
    currentStatus: string | undefined,
): void {
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!installCompleted) return;
        if (currentStatus !== 'provisioning' && currentStatus !== 'provisioning_failed') return;

        // Immediate refetch + 2s polling until the webhook lands. The
        // queryKey here MUST match useServer (`['servers', id]`) — keep
        // them in sync if useServer ever changes its key shape.
        void queryClient.invalidateQueries({ queryKey: ['servers', serverId] });

        const interval = setInterval(() => {
            void queryClient.invalidateQueries({ queryKey: ['servers', serverId] });
        }, 2000);

        return () => clearInterval(interval);
    }, [installCompleted, currentStatus, serverId, queryClient]);
}
