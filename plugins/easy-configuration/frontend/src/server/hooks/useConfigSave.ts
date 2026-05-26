import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { backendFieldKey, fieldKeyOf } from '../../lib/fieldKey';
import { pickLabel, useT } from '../../lib/i18n';
import { validateValue } from '../../lib/validate';
import { P, type ApiError } from '../../shared';
import type { ConfigParam, ConfigTemplate } from '../../types';
import { useToast } from '../../ui/Toast';
import { useSaveConfig, type SaveFilePayload } from './useServerConfig';

/** Source id used to register with the host's unified save bar. */
const SAVE_SOURCE_ID = 'easy-configuration';

/**
 * Add keys present in `source` but missing from `target`, preserving every
 * existing entry (so unsaved edits survive a refetch). Returns the same
 * reference when nothing is added, to avoid a needless re-render.
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
 * Save orchestration for the config editor, extracted from ConfigEditor so the
 * component stays under the 300-line rule. Owns the values/original/invalid/
 * saved state, the dirty computation, validation-aware onChange/onReset, and
 * the save itself.
 *
 * Unified-save-bar integration: when the host exposes `registerSaveSource`
 * (`useHostBar`), this hook registers a save source so the host's single bar
 * flushes the config alongside the core's env-variable edits in one click — and
 * the component then suppresses its own FloatingSaveBar + Cmd/S listener. When
 * the host lacks the bridge (older shell), nothing registers and the component
 * falls back to its own bar. Pure feature-detection, no hard dependency.
 */
export function useConfigSave(params: {
    serverId: number;
    templates: ConfigTemplate[];
    running: boolean;
    hardDisabled: boolean;
    canEdit: boolean;
}) {
    const { templates, running, hardDisabled, canEdit } = params;
    const { t, lang } = useT();
    const toast = useToast();
    const save = useSaveConfig(params.serverId);

    const useHostBar = typeof P.registerSaveSource === 'function';

    const { initial, index, lockedFiles } = useMemo(() => {
        const initialValues: Record<string, string> = {};
        const paramIndex = new Map<string, { param: ConfigParam; fileId: string }>();
        // A file is locked while the server runs only when its template requires
        // a shutdown to edit (the default). Templates with require_shutdown=false
        // stay editable on a running server.
        const locked = new Set<string>();
        for (const template of templates) {
            const requiresShutdown = template.require_shutdown !== false;
            for (const file of template.files) {
                if (running && requiresShutdown) {
                    locked.add(file.id);
                }
                for (const param of file.parameters) {
                    const key = fieldKeyOf(file.id, param);
                    initialValues[key] = param.value;
                    paramIndex.set(key, { param, fileId: file.id });
                }
            }
        }
        return { initial: initialValues, index: paramIndex, lockedFiles: locked };
    }, [templates, running]);

    const [values, setValues] = useState<Record<string, string>>(initial);
    const [original, setOriginal] = useState<Record<string, string>>(initial);
    const [invalid, setInvalid] = useState<Record<string, boolean>>({});
    const [savedKeys, setSavedKeys] = useState<Set<string>>(new Set());
    const [justSaved, setJustSaved] = useState(false);

    useEffect(() => {
        setValues((current) => mergeMissing(current, initial));
        setOriginal((current) => mergeMissing(current, initial));
    }, [initial]);

    const dirtyKeys = useMemo(() => Object.keys(values).filter((key) => values[key] !== original[key]), [values, original]);
    const isDirty = dirtyKeys.length > 0;
    const hasInvalid = Object.values(invalid).some(Boolean);

    const onChange = useCallback(
        (fieldKey: string, param: ConfigParam, value: string) => {
            if (hardDisabled) {
                return;
            }
            if (lockedFiles.has(index.get(fieldKey)?.fileId ?? '')) {
                toast.warning(t('overlay.edit_blocked'));
                return;
            }
            setJustSaved(false);
            setValues((current) => ({ ...current, [fieldKey]: value }));
            const reason = validateValue(param, value);
            setInvalid((current) => ({ ...current, [fieldKey]: reason !== null }));
            if (reason !== null) {
                toast.warning(t('validation.invalid_value', { param: pickLabel(param.label, lang, param.key), type: t(`validation.type.${reason}`) }));
            }
        },
        [toast, t, lang, hardDisabled, lockedFiles, index],
    );

    const onReset = useCallback(
        (fieldKey: string, param: ConfigParam) => {
            if (param.config.default !== undefined) {
                onChange(fieldKey, param, String(param.config.default));
            }
        },
        [onChange],
    );

    const doSaveAsync = useCallback(async () => {
        if (!isDirty || !canEdit || save.isPending) {
            return;
        }
        if (hasInvalid) {
            toast.error(t('save.fix_invalid'));
            throw new Error('invalid');
        }

        const byFile = new Map<string, SaveFilePayload['values']>();
        for (const key of dirtyKeys) {
            const entry = index.get(key);
            if (entry === undefined) {
                continue;
            }
            const list = byFile.get(entry.fileId) ?? [];
            list.push({ key: entry.param.key, section: entry.param.section, value: values[key] ?? '', occurrence: entry.param.occurrence ?? 0 });
            byFile.set(entry.fileId, list);
        }

        try {
            await save.mutateAsync([...byFile.entries()].map(([id, vals]) => ({ id, values: vals })));
            setOriginal({ ...values });
            setSavedKeys(new Set(dirtyKeys));
            setJustSaved(true);
            setInvalid({});
            window.setTimeout(() => {
                setJustSaved(false);
                setSavedKeys(new Set());
            }, 2000);
            toast.success(t('save.saved'));
        } catch (error) {
            const apiError = error as unknown as ApiError;
            if (apiError.status === 422 && apiError.fields) {
                const next: Record<string, boolean> = {};
                for (const [fileId, fields] of Object.entries(apiError.fields)) {
                    for (const composite of Object.keys(fields)) {
                        next[backendFieldKey(fileId, composite)] = true;
                    }
                }
                setInvalid(next);
            }
            toast.error(t('save.error'));
            throw error; // rethrow so the host save bar reflects the failure
        }
    }, [isDirty, canEdit, hasInvalid, dirtyKeys, index, values, save, toast, t]);

    // Fire-and-forget variant for the plugin's own bar / keyboard shortcut.
    const doSave = useCallback(() => {
        void doSaveAsync().catch(() => undefined);
    }, [doSaveAsync]);

    // Plugin-owned Cmd/Ctrl+S — only when NOT delegating to the host bar
    // (otherwise the host handles the shortcut and we'd double-save).
    useEffect(() => {
        if (useHostBar) {
            return;
        }
        const onKey = (event: KeyboardEvent): void => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                doSave();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [useHostBar, doSave]);

    // Register with the host's unified save bar (ref-stable callback; only the
    // dirty count re-registers). Unregisters on unmount or when editing is off.
    const saveRef = useRef(doSaveAsync);
    saveRef.current = doSaveAsync;
    useEffect(() => {
        if (!useHostBar || !canEdit) {
            return;
        }
        P.registerSaveSource?.(SAVE_SOURCE_ID, { dirtyCount: dirtyKeys.length, save: () => saveRef.current() });
        return () => P.unregisterSaveSource?.(SAVE_SOURCE_ID);
    }, [useHostBar, canEdit, dirtyKeys.length]);

    const getValue = useCallback((key: string) => values[key] ?? '', [values]);
    const isDirtyKey = useCallback((key: string) => values[key] !== original[key], [values, original]);
    const isSavedKey = useCallback((key: string) => savedKeys.has(key), [savedKeys]);
    const isInvalidKey = useCallback((key: string) => invalid[key] ?? false, [invalid]);

    return {
        isDirty,
        justSaved,
        saving: save.isPending,
        useHostBar,
        onChange,
        onReset,
        doSave,
        getValue,
        isDirtyKey,
        isSavedKey,
        isInvalidKey,
    };
}
