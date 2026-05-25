import { create } from 'zustand';

/**
 * A "save source" is any editor (core or plugin) that holds unsaved changes and
 * knows how to persist them. The unified save bar collects every registered
 * source so a single click flushes them all — without the bar (or the core)
 * knowing what any given source actually saves.
 */
export interface SaveSource {
    /** Number of unsaved fields this source currently holds (drives the bar's count). */
    dirtyCount: number;
    /** Persist this source's changes. MUST reject on failure so the bar surfaces an error. */
    save: () => Promise<void>;
}

interface SaveCoordinatorState {
    sources: Record<string, SaveSource>;
    registerSource: (id: string, source: SaveSource) => void;
    unregisterSource: (id: string) => void;
}

/**
 * Registry of save sources, shared by the core env-variable editor and any
 * opt-in plugin (e.g. easy-configuration via the `__PEREGRINE_PLUGINS__`
 * bridge). Deliberately holds no plugin-specific knowledge: it's a plain map of
 * `{ dirtyCount, save }` keyed by an arbitrary source id.
 */
export const useSaveCoordinatorStore = create<SaveCoordinatorState>((set) => ({
    sources: {},
    registerSource: (id, source) =>
        set((state) => ({ sources: { ...state.sources, [id]: source } })),
    unregisterSource: (id) =>
        set((state) => {
            if (!(id in state.sources)) {
                return state;
            }
            const next = { ...state.sources };
            delete next[id];
            return { sources: next };
        }),
}));

/** Total unsaved fields across all sources. Returns a primitive so subscribers re-render only when it changes. */
export function selectTotalDirty(state: SaveCoordinatorState): number {
    return Object.values(state.sources).reduce((sum, source) => sum + source.dirtyCount, 0);
}
