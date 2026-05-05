import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { getEcho } from '@/services/echo';

/**
 * Subscribe to the user-scoped (and optionally admin-scoped) mirror
 * channels and translate `mirror.changed` broadcasts into TanStack
 * Query invalidations on the server-list query keys.
 *
 * Backend fans out every `ServerMirrorChanged` on three channel
 * families :
 *   - `private-server.{id}` (detail pages, owned by useServerLiveUpdates)
 *   - `private-user.{userId}` (every access user of the changed server)
 *   - `private-admin-mirror` (all admins, regardless of ownership)
 *
 * /servers (DashboardPage) listens on `user.{currentUserId}` so cards
 * flip status in real-time when Pelican fires a webhook upstream
 * (suspended ↔ active, provisioning → active, deletion). /admin/servers
 * listens additionally on `admin-mirror` so the admin sees every
 * panel-wide change.
 *
 * Returns a connection status the caller can render as a small badge.
 * Never throws : a missing Echo instance (admin hasn't set Reverb up
 * yet) just degrades to the default TanStack staleTime — no crash.
 */

type ConnectionStatus = 'idle' | 'connected' | 'reconnecting' | 'unavailable';

export interface UseServersListLiveUpdatesResult {
    status: ConnectionStatus;
}

interface MirrorChangedPayload {
    resource: string;
    action: 'upsert' | 'delete';
    resource_id: number | null;
}

interface UseServersListLiveUpdatesOptions {
    userId: number | null;
    isAdmin?: boolean;
}

export function useServersListLiveUpdates({
    userId,
    isAdmin = false,
}: UseServersListLiveUpdatesOptions): UseServersListLiveUpdatesResult {
    const queryClient = useQueryClient();
    const [status, setStatus] = useState<ConnectionStatus>('idle');
    const handlerRef = useRef<((payload: MirrorChangedPayload) => void) | null>(null);

    useEffect(() => {
        if (userId === null && !isAdmin) {
            return;
        }

        const echo = getEcho();
        if (echo === null) {
            setStatus('unavailable');
            return;
        }

        const handler = (payload: MirrorChangedPayload): void => {
            // The list pages only care about `server` resource events ; the
            // detail-page hook already handles allocation / backup / etc.
            if (payload.resource !== 'server') {
                return;
            }
            queryClient.invalidateQueries({ queryKey: ['servers'] });
            if (isAdmin) {
                queryClient.invalidateQueries({ queryKey: ['admin-servers'] });
            }
        };
        handlerRef.current = handler;

        const subscriptions: Array<{ name: string; stop: () => void }> = [];

        if (userId !== null && Number.isFinite(userId)) {
            const channel = echo.private(`user.${userId}`);
            channel.listen('.mirror.changed', handler);
            subscriptions.push({
                name: `user.${userId}`,
                stop: () => {
                    channel.stopListening('.mirror.changed');
                    echo.leave(`user.${userId}`);
                },
            });
        }

        if (isAdmin) {
            const channel = echo.private('admin-mirror');
            channel.listen('.mirror.changed', handler);
            subscriptions.push({
                name: 'admin-mirror',
                stop: () => {
                    channel.stopListening('.mirror.changed');
                    echo.leave('admin-mirror');
                },
            });
        }

        const connector = (echo.connector as unknown as {
            pusher?: { connection?: { bind?: (event: string, cb: () => void) => void; state?: string } };
        }).pusher;
        const connection = connector?.connection;

        connection?.bind?.('connected', () => setStatus('connected'));
        connection?.bind?.('disconnected', () => setStatus('reconnecting'));
        connection?.bind?.('unavailable', () => setStatus('reconnecting'));
        connection?.bind?.('failed', () => setStatus('unavailable'));

        if (connection?.state === 'connected') {
            setStatus('connected');
        }

        return () => {
            for (const sub of subscriptions) {
                try {
                    sub.stop();
                } catch {
                    // leave on a half-broken channel can throw — safe to ignore.
                }
            }
            handlerRef.current = null;
        };
    }, [userId, isAdmin, queryClient]);

    return { status };
}
