import { useCallback, useEffect, useMemo, useState } from 'react';
import { useStartupVariables } from '@/hooks/useStartupVariables';
import { useSaveSource } from '@/hooks/useSaveSource';

/**
 * Add keys present in `source` but missing from `target`, preserving every
 * existing entry (so unsaved edits survive a refetch). Returns the same
 * reference when nothing is added, to avoid a needless re-render. Mirrors the
 * easy-configuration editor's merge behaviour.
 */
function mergeMissing(target: Record<string, string>, source: Record<string, string>): Record<string, string> {
    let merged: Record<string, string> | null = null;
    for (const [key, value] of Object.entries(source)) {
        if (!(key in target)) {
            merged ??= { ...target };
            merged[key] = value;
        }
    }
    return merged ?? target;
}

/**
 * Editing state machine for the server's startup variables, lifted out of the
 * component so the latter stays a thin view (300-line rule). Holds the current
 * vs. original values, computes the dirty set, and exposes a batch `save()`.
 *
 * The save registers with the global save coordinator (only while `canEdit`),
 * so the unified save bar flushes these edits alongside any plugin's in one
 * click. Partial failures (per-key) keep the failed keys dirty + flagged.
 */
export function useStartupVariablesEditor(serverId: number, canEdit: boolean) {
    const { variables, isLoading, saveVariables } = useStartupVariables(serverId);

    const initial = useMemo(() => {
        const map: Record<string, string> = {};
        for (const variable of variables) {
            map[variable.env_variable] = variable.server_value ?? variable.default_value ?? '';
        }
        return map;
    }, [variables]);

    const [values, setValues] = useState<Record<string, string>>(initial);
    const [original, setOriginal] = useState<Record<string, string>>(initial);
    const [invalidKeys, setInvalidKeys] = useState<Set<string>>(new Set());

    // A refetch (e.g. after a save invalidation) brings keys in `initial`; merge
    // them in without clobbering unsaved edits to existing fields.
    useEffect(() => {
        setValues((current) => mergeMissing(current, initial));
        setOriginal((current) => mergeMissing(current, initial));
    }, [initial]);

    const dirtyKeys = useMemo(
        () => Object.keys(values).filter((key) => values[key] !== original[key]),
        [values, original],
    );

    const onChange = useCallback((key: string, value: string) => {
        setValues((current) => ({ ...current, [key]: value }));
        setInvalidKeys((current) => {
            if (!current.has(key)) {
                return current;
            }
            const next = new Set(current);
            next.delete(key);
            return next;
        });
    }, []);

    const reset = useCallback(
        (key: string) => {
            setValues((current) => ({ ...current, [key]: original[key] ?? '' }));
        },
        [original],
    );

    const save = useCallback(async () => {
        const keys = Object.keys(values).filter((key) => values[key] !== original[key]);
        if (keys.length === 0) {
            return;
        }
        const result = await saveVariables(keys.map((key) => ({ key, value: values[key] ?? '' })));
        const failedKeys = Object.keys(result.errors ?? {});

        // Sync `original` for every key that DID save (so they leave the dirty
        // set); failed keys stay dirty + get flagged invalid for a retry.
        setOriginal((prev) => {
            const next = { ...prev };
            for (const key of keys) {
                if (!failedKeys.includes(key)) {
                    next[key] = values[key] ?? '';
                }
            }
            return next;
        });
        setInvalidKeys(new Set(failedKeys));

        if (!result.success) {
            throw new Error('partial_save_failed');
        }
    }, [values, original, saveVariables]);

    useSaveSource('startup-variables', dirtyKeys.length, save, canEdit);

    return { variables, isLoading, values, invalidKeys, dirtyKeys, onChange, reset };
}
