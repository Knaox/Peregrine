import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { getEcho } from '@/services/echo';

/**
 * Subscribe to `private-server.{serverId}` and translate `mirror.changed`
 * broadcasts into TanStack Query invalidations so the network / databases
 * / backups / sub-users pages refresh silently when Pelican fires a
 * webhook upstream. < 1s latency end-to-end on a healthy Reverb link.
 *
 * Returns a connection status the caller can render as a small badge —
 * "Live", "Reconnecting…", or omit entirely. Never throws : a missing
 * Echo instance (admin hasn't set Reverb up yet) just degrades to the
 * default TanStack staleTime — no crash.
 */

type ConnectionStatus = 'idle' | 'connected' | 'reconnecting' | 'unavailable';

export interface UseServerLiveUpdatesResult {
    status: ConnectionStatus;
}

interface MirrorChangedPayload {
    resource: string;
    action: 'upsert' | 'delete';
    resource_id: number | null;
}

/**
 * Each broadcast `resource` invalidates one or more TanStack queryKeys —
 * the host convention is `['servers', id, '<resource>']` for first-party
 * pages, but plugins (notably invitations) keep their own legacy keys
 * (`['subusers', id]`, `['invitations', id]`). We invalidate every known
 * variant ; TanStack Query is a no-op when the key isn't registered.
 */
const RESOURCE_TO_QUERY_KEYS: Record<string, ReadonlyArray<readonly (string | number)[]>> = {
    server: [['servers', 'ID', 'server']],
    allocation: [['servers', 'ID', 'network']],
    backup: [['servers', 'ID', 'backups']],
    database: [['servers', 'ID', 'databases']],
    subuser: [
        ['servers', 'ID', 'subusers'],
        ['subusers', 'ID'],
        ['invitations', 'ID'],
    ],
};

export function useServerLiveUpdates(serverId: number | null): UseServerLiveUpdatesResult {
    const queryClient = useQueryClient();
    const [status, setStatus] = useState<ConnectionStatus>('idle');
    const handlerRef = useRef<((payload: MirrorChangedPayload) => void) | null>(null);

    useEffect(() => {
        if (serverId === null || !Number.isFinite(serverId)) {
            return;
        }

        const echo = getEcho();
        if (echo === null) {
            setStatus('unavailable');
            return;
        }

        const handler = (payload: MirrorChangedPayload): void => {
            const templates = RESOURCE_TO_QUERY_KEYS[payload.resource];
            if (templates === undefined) {
                return;
            }
            for (const template of templates) {
                const key = template.map((part) => (part === 'ID' ? serverId : part));
                queryClient.invalidateQueries({ queryKey: key });
            }
            // Server-level changes ripple to allocations (primary badge).
            if (payload.resource === 'server') {
                queryClient.invalidateQueries({ queryKey: ['servers', serverId, 'network'] });
            }
        };
        handlerRef.current = handler;

        const channel = echo.private(`server.${serverId}`);
        channel.listen('.mirror.changed', handler);

        // Connection lifecycle hooks (Pusher protocol). Reverb emits
        // `connected` once the handshake completes, `disconnected` /
        // `unavailable` on transport errors. Echo's underlying pusher
        // client retries with its own backoff (250ms → ~10s capped).
        const connector = (echo.connector as unknown as {
            pusher?: { connection?: { bind?: (event: string, cb: () => void) => void } };
        }).pusher;
        const connection = connector?.connection;

        connection?.bind?.('connected', () => setStatus('connected'));
        connection?.bind?.('disconnected', () => setStatus('reconnecting'));
        connection?.bind?.('unavailable', () => setStatus('reconnecting'));
        connection?.bind?.('failed', () => setStatus('unavailable'));

        // If the connection is already open by the time we subscribe,
        // the `connected` event may have fired before we bound. Force a
        // best-guess initial status via the public state field.
        const state = (connection as { state?: string } | undefined)?.state;
        if (state === 'connected') {
            setStatus('connected');
        }

        return () => {
            try {
                channel.stopListening('.mirror.changed');
                echo.leave(`server.${serverId}`);
            } catch {
                // leave on a half-broken channel can throw — safe to ignore.
            }
            handlerRef.current = null;
        };
    }, [serverId, queryClient]);

    return { status };
}

