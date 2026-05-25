import { useEffect, useRef } from 'react';
import { useSaveCoordinatorStore } from '@/stores/saveCoordinatorStore';

/**
 * Register an editor with the global save coordinator so the unified save bar
 * can flush it alongside every other dirty source in one click.
 *
 * `save` is captured in a ref and re-read on each invocation, so the source
 * stays registered under a stable callback identity (no re-register churn on
 * every render) while always running the latest closure. Only `dirtyCount`
 * changes trigger a re-register, to keep the bar's count in sync.
 *
 * Pass `enabled = false` (e.g. read-only / no write permission) to opt out
 * entirely — the source then contributes nothing to the bar.
 */
export function useSaveSource(
    id: string,
    dirtyCount: number,
    save: () => Promise<void>,
    enabled = true,
): void {
    const register = useSaveCoordinatorStore((s) => s.registerSource);
    const unregister = useSaveCoordinatorStore((s) => s.unregisterSource);

    const saveRef = useRef(save);
    saveRef.current = save;

    useEffect(() => {
        if (!enabled) {
            return;
        }
        register(id, { dirtyCount, save: () => saveRef.current() });
        return () => unregister(id);
    }, [id, dirtyCount, enabled, register, unregister]);
}
