import { create } from 'zustand';
import type { PowerSignal } from '@/types/PowerSignal';

type Display = 'starting' | 'stopping';

interface Transition {
    /** Transitional state shown on the card until real stats catch up. */
    display: Display;
    /** Real state we're waiting for before clearing the transition. */
    target: 'running' | 'stopped';
    /** ms epoch — used to expire a stuck transition. */
    since: number;
}

interface PowerTransitionState {
    transitions: Record<number, Transition>;
    /** Maps a power signal to an optimistic transitional state for a server. */
    setFromSignal: (serverId: number, signal: PowerSignal) => void;
    clear: (serverId: number) => void;
}

/**
 * Holds optimistic "starting…/stopping…" states for dashboard cards. The
 * dashboard stats list is poll-based (10s), so without this a Start click
 * shows nothing until the next poll. We surface a transitional state
 * immediately and clear it once the real polled state reaches the target
 * (or after a safety timeout). Lives in a store so any card — classic or
 * biome — reflects it via the merged stats map, no prop threading.
 */
export const usePowerTransitionStore = create<PowerTransitionState>((set) => ({
    transitions: {},
    setFromSignal: (serverId, signal) =>
        set((state) => {
            const display: Display = signal === 'stop' || signal === 'kill' ? 'stopping' : 'starting';
            const target = display === 'stopping' ? 'stopped' : 'running';
            return {
                transitions: { ...state.transitions, [serverId]: { display, target, since: Date.now() } },
            };
        }),
    clear: (serverId) =>
        set((state) => {
            if (!(serverId in state.transitions)) return state;
            const next = { ...state.transitions };
            delete next[serverId];
            return { transitions: next };
        }),
}));
