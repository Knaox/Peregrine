import { useCallback, useEffect, useMemo, useState } from 'react';
import { useStartupVariables } from '@/hooks/useStartupVariables';
import { useSaveSource } from '@/hooks/useSaveSource';
import { parseRules, validateVariable } from '@/services/variableRules';

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
    // Keys rejected by the LAST save round-trip (Pelican-side failures).
    const [failedKeys, setFailedKeys] = useState<Set<string>>(new Set());

    // Live client-side validation against each variable's Pelican rules —
    // the same checks the panel will enforce, surfaced before saving.
    const invalidKeys = useMemo(() => {
        const invalid = new Set<string>(failedKeys);
        for (const variable of variables) {
            const error = validateVariable(parseRules(variable.rules ?? ''), values[variable.env_variable] ?? '');
            if (error !== null) {
                invalid.add(variable.env_variable);
            }
        }
        return invalid;
    }, [variables, values, failedKeys]);

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
        // A fresh edit clears the key's backend failure flag; the live rule
        // validation above re-evaluates on its own.
        setFailedKeys((current) => {
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
        // Don't ship values the rules already reject — Pelican would 422 them
        // one by one; the cards are flagged, the save bar reports the failure.
        if (keys.some((key) => invalidKeys.has(key))) {
            throw new Error('invalid_values');
        }
        const result = await saveVariables(keys.map((key) => ({ key, value: values[key] ?? '' })));
        const rejected = Object.keys(result.errors ?? {});

        // Sync `original` for every key that DID save (so they leave the dirty
        // set); failed keys stay dirty + get flagged invalid for a retry.
        setOriginal((prev) => {
            const next = { ...prev };
            for (const key of keys) {
                if (!rejected.includes(key)) {
                    next[key] = values[key] ?? '';
                }
            }
            return next;
        });
        setFailedKeys(new Set(rejected));

        if (!result.success) {
            throw new Error('partial_save_failed');
        }
    }, [values, original, invalidKeys, saveVariables]);

    useSaveSource('startup-variables', dirtyKeys.length, save, canEdit);

    return { variables, isLoading, values, invalidKeys, dirtyKeys, onChange, reset };
}
