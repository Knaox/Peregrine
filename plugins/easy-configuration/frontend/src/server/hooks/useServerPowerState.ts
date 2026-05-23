import { useEffect, useState } from 'react';
import type { ServerState } from '../../types';
import { useServerStatus } from './useServerConfig';

interface PowerEventDetail {
    serverId?: number;
    state?: string;
}

/**
 * Live server power state for the editor lock. Initial value comes from a
 * one-shot `/status` fetch; live transitions then arrive instantly via the
 * `peregrine:server-power` window event, which the core re-broadcasts from the
 * SAME Wings socket already open on the home page (no second connection, no
 * polling). The editor locks the moment the server starts and unlocks on stop.
 */
export function useServerPowerState(serverId: number, enabled: boolean): { state: ServerState; running: boolean } {
    const initial = useServerStatus(serverId, enabled);
    const [liveState, setLiveState] = useState<string | null>(null);

    useEffect(() => {
        const onPower = (event: Event): void => {
            const detail = (event as CustomEvent).detail as PowerEventDetail | undefined;
            if (detail && detail.serverId === serverId && typeof detail.state === 'string') {
                setLiveState(detail.state);
            }
        };
        window.addEventListener('peregrine:server-power', onPower);

        return () => window.removeEventListener('peregrine:server-power', onPower);
    }, [serverId]);

    const state = (liveState ?? initial.data?.state ?? 'offline') as ServerState;
    const running = state !== 'offline' && state !== 'stopped';

    return { state, running };
}
