import { useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useServer } from '@/hooks/useServer';

/**
 * Operation type tag that travels through `location.state` to the overview
 * page, which uses it to pick the right success-message i18n key.
 */
export type OperationType = 'install' | 'unsuspend' | 'modpack' | 'modpack_uninstall';

/**
 * Payload pushed onto `location.state` by this hook when a long-running
 * operation completes. The overview page reads it once and clears the state.
 */
export interface CompletedOperation {
    operationCompleted: true;
    operationType: OperationType;
    operationName?: string | null;
}

/**
 * Internal — payload of the custom DOM events plugins fire to signal the
 * shell about non-server-status-driven operations (e.g. modpack install).
 */
interface OperationEventDetail {
    serverId: number;
    type: OperationType;
    name?: string | null;
}

/**
 * Cross-cutting hook mounted on the server detail page. Watches two signals
 * and, on completion, redirects to the server overview with a state flag
 * that the overview reads to render a one-shot success Alert.
 *
 *  1. `Server::status` transitions :
 *     - `provisioning|provisioning_failed` → anything else → "install" complete
 *     - `suspended` → anything else                       → "unsuspend" complete
 *
 *  2. Plugin-driven `peregrine:operation-complete` window events :
 *     plugins (modpack installer in particular) fire these via the
 *     `__PEREGRINE_PLUGINS__.notifyOperationComplete()` shim when they
 *     detect their own internal completion (polling their endpoint).
 *
 * A short cooldown after each redirect suppresses double-firing when both
 * signals race (modpack install also flips Server::status via the Pelican
 * webhook around the same time).
 */
export function useServerOperationLifecycle(serverId: number): void {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: server } = useServer(serverId);

    // Tracks the previous status so we can detect transitions. Initialised
    // lazy on first render; stays stable across renders.
    const prevStatusRef = useRef<string | null | undefined>(undefined);
    // Suppress duplicate redirects when both the server-status transition AND
    // a plugin completion event fire within the same window. Reset 2s after
    // each redirect.
    const cooldownRef = useRef<number>(0);

    const fireRedirect = (op: CompletedOperation): void => {
        const now = Date.now();
        if (now < cooldownRef.current) return;
        cooldownRef.current = now + 2000;

        // Force a refetch of the server query so cached install gates lift
        // immediately on the destination page even when Reverb isn't running.
        void queryClient.invalidateQueries({ queryKey: ['servers', serverId] });

        navigate(`/servers/${serverId}`, { state: op });
    };

    // ---------------------------------------------------------------------
    // Server::status-driven transitions
    // ---------------------------------------------------------------------
    useEffect(() => {
        const prev = prevStatusRef.current;
        const next = server?.status;
        // Skip the very first render — undefined → defined is just data load.
        if (prev === undefined) {
            prevStatusRef.current = next;
            return;
        }
        prevStatusRef.current = next;

        if (prev === next) return;

        // Install lifecycle complete : provisioning → idle.
        if ((prev === 'provisioning' || prev === 'provisioning_failed')
            && next !== 'provisioning' && next !== 'provisioning_failed') {
            fireRedirect({ operationCompleted: true, operationType: 'install' });
            return;
        }

        // Unsuspend.
        if (prev === 'suspended' && next !== 'suspended') {
            fireRedirect({ operationCompleted: true, operationType: 'unsuspend' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [server?.status, serverId, navigate]);

    // ---------------------------------------------------------------------
    // Plugin-driven completion events (modpack install/uninstall)
    // ---------------------------------------------------------------------
    useEffect(() => {
        const handler = (e: Event): void => {
            const detail = (e as CustomEvent<OperationEventDetail>).detail;
            if (!detail || detail.serverId !== serverId) return;
            fireRedirect({
                operationCompleted: true,
                operationType: detail.type,
                operationName: detail.name ?? null,
            });
        };
        window.addEventListener('peregrine:operation-complete', handler);
        return () => window.removeEventListener('peregrine:operation-complete', handler);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverId, navigate]);
}
