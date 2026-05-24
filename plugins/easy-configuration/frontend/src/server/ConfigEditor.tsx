import { Copy, Search, SlidersHorizontal } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { backendFieldKey, fieldKeyOf } from '../lib/fieldKey';
import { pickLabel, useT } from '../lib/i18n';
import { validateValue } from '../lib/validate';
import type { ApiError } from '../shared';
import type { ConfigParam, ConfigPermissions, ConfigTemplate, ServerState } from '../types';
import { Button } from '../ui/Button';
import { Input, Toggle } from '../ui/inputs';
import { Card, EmptyState } from '../ui/surfaces';
import { useToast } from '../ui/Toast';
import { BoostSection } from './boost/BoostSection';
import { useBoosts } from './boost/useBoosts';
import { useBoostSelection } from './boost/useBoostSelection';
import type { EditorController } from './controller';
import { CopyDialog } from './copy/CopyDialog';
import { FileCard } from './FileCard';
import { FloatingSaveBar } from './FloatingSaveBar';
import { useSaveConfig, type SaveFilePayload } from './hooks/useServerConfig';
import { RunningOverlay } from './RunningOverlay';

/**
 * Add keys present in `source` but missing from `target`, preserving every
 * existing entry (so unsaved edits survive). Returns the same reference when
 * nothing is added, to avoid a needless re-render.
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

export function ConfigEditor({
    serverId,
    templates,
    disabled,
    permissions,
    state,
    stopping,
    onStop,
}: {
    serverId: number;
    templates: ConfigTemplate[];
    /** Server is running → the config area is collapsed + overlaid (edit offline only). */
    disabled: boolean;
    permissions?: ConfigPermissions;
    state: ServerState;
    stopping: boolean;
    onStop: () => void;
}) {
    const { t, lang } = useT();
    const toast = useToast();
    const save = useSaveConfig(serverId);

    // No payload `permissions` → owner/admin (full access). Subusers get the
    // explicit flags from the backend so we can render read-only / hide actions.
    const canWrite = permissions?.write ?? true;
    const canCopy = permissions?.copy ?? true;
    const canBoost = permissions?.boost ?? true;
    // Controls are frozen when the server is running (overlay) or the caller only
    // has read access. The overlay + section collapse track running only.
    const readOnly = disabled || !canWrite;

    const { initial, index } = useMemo(() => {
        const initialValues: Record<string, string> = {};
        const paramIndex = new Map<string, { param: ConfigParam; fileId: string }>();
        for (const template of templates) {
            for (const file of template.files) {
                for (const param of file.parameters) {
                    const key = fieldKeyOf(file.id, param);
                    initialValues[key] = param.value;
                    paramIndex.set(key, { param, fileId: file.id });
                }
            }
        }

        return { initial: initialValues, index: paramIndex };
    }, [templates]);

    const [values, setValues] = useState<Record<string, string>>(initial);
    const [original, setOriginal] = useState<Record<string, string>>(initial);
    const [invalid, setInvalid] = useState<Record<string, boolean>>({});
    const [savedKeys, setSavedKeys] = useState<Set<string>>(new Set());
    const [justSaved, setJustSaved] = useState(false);
    const [search, setSearchState] = useState<string>(() => {
        try {
            return localStorage.getItem(`ec:search:${serverId}`) ?? '';
        } catch {
            return '';
        }
    });
    const setSearch = (value: string): void => {
        setSearchState(value);
        try {
            localStorage.setItem(`ec:search:${serverId}`, value);
        } catch {
            /* localStorage unavailable */
        }
    };
    const [copyOpen, setCopyOpen] = useState(false);
    const boosts = useBoosts(serverId);
    const boost = useBoostSelection(templates, boosts.data, lang, canBoost);

    // A refetch (e.g. after adding a parameter) brings new keys in `initial`.
    // Merge their values into local state so they render with their value —
    // without clobbering unsaved edits to existing fields. `original` gets them
    // too, so a freshly added parameter isn't flagged dirty.
    useEffect(() => {
        setValues((current) => mergeMissing(current, initial));
        setOriginal((current) => mergeMissing(current, initial));
    }, [initial]);

    const dirtyKeys = useMemo(() => Object.keys(values).filter((key) => values[key] !== original[key]), [values, original]);
    const isDirty = dirtyKeys.length > 0;
    const hasInvalid = Object.values(invalid).some(Boolean);

    const onChange = useCallback(
        (fieldKey: string, param: ConfigParam, value: string) => {
            setJustSaved(false);
            setValues((current) => ({ ...current, [fieldKey]: value }));
            const reason = validateValue(param, value);
            setInvalid((current) => ({ ...current, [fieldKey]: reason !== null }));
            if (reason !== null) {
                toast.warning(t('validation.invalid_value', { param: pickLabel(param.label, lang, param.key), type: t(`validation.type.${reason}`) }));
            }
        },
        [toast, t, lang],
    );

    const onReset = useCallback(
        (fieldKey: string, param: ConfigParam) => {
            if (param.config.default !== undefined) {
                onChange(fieldKey, param, String(param.config.default));
            }
        },
        [onChange],
    );

    const doSave = useCallback(() => {
        if (!isDirty || readOnly || save.isPending) {
            return;
        }
        if (hasInvalid) {
            toast.error(t('save.fix_invalid'));

            return;
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

        save.mutate([...byFile.entries()].map(([id, vals]) => ({ id, values: vals })), {
            onSuccess: () => {
                setOriginal({ ...values });
                setSavedKeys(new Set(dirtyKeys));
                setJustSaved(true);
                setInvalid({});
                window.setTimeout(() => {
                    setJustSaved(false);
                    setSavedKeys(new Set());
                }, 2000);
                toast.success(t('save.saved'));
            },
            onError: (error) => {
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
            },
        });
    }, [isDirty, readOnly, hasInvalid, dirtyKeys, index, values, save, toast, t]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent): void => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                doSave();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [doSave]);

    const controller: EditorController = {
        getValue: (key) => values[key] ?? '',
        isDirty: (key) => values[key] !== original[key],
        isSaved: (key) => savedKeys.has(key),
        isInvalid: (key) => invalid[key] ?? false,
        disabled: readOnly,
        search,
        onChange,
        onReset,
        boostMode: boost.boostMode,
        isBoostable: boost.isBoostable,
        isBoostSelected: boost.isBoostSelected,
        isBoostLocked: boost.isBoostLocked,
        toggleBoost: boost.toggleBoost,
        isBoostDivide: boost.isBoostDivide,
        toggleDivide: boost.toggleDivide,
        canManageTemplate: permissions?.manage_templates ?? false,
    };

    // Files absent on the server aren't shown at all (a config file only exists
    // once the server has generated it — typically after its first boot).
    const fileCards = templates.flatMap((template) =>
        template.files
            .filter((file) => file.exists !== false)
            .map((file) => ({ key: `${template.id}:${file.id}`, file, columns: template.columns, templateId: template.id })),
    );

    return (
        <div className="ec-stack">
            <div className="ec-between">
                <div className="ec-row">
                    <span className="ec-icon-box">
                        <SlidersHorizontal size={18} />
                    </span>
                    <div>
                        <h2 className="ec-title">{t('section.title')}</h2>
                        <p className="ec-subtitle">{t('section.subtitle')}</p>
                    </div>
                    {!canWrite && <span className="ec-badge ec-badge-muted">{t('section.read_only')}</span>}
                </div>
                <div className="ec-row">
                    {boost.boostEnabled && (
                        <label className="ec-row" style={{ cursor: 'pointer' }}>
                            <span className="ec-field-desc ec-secondary">{t('boost.mode')}</span>
                            <Toggle checked={boost.boostMode} onChange={boost.setMode} label={t('boost.mode')} />
                        </label>
                    )}
                    {canCopy && (
                        <Button variant="secondary" onClick={() => setCopyOpen(true)}>
                            <Copy size={15} /> {t('copy.button')}
                        </Button>
                    )}
                </div>
            </div>

            {/* Boost management stays accessible even while the server runs. */}
            {boost.boostEnabled && (
                <BoostSection
                    serverId={serverId}
                    boosts={boosts.data ?? []}
                    selectedParams={boost.selectedBoostParams}
                    selectedCount={boost.selectedBoostParams.length}
                />
            )}

            {/* Config area: locked (collapsed + overlay) while the server runs. */}
            <div className="ec-relative">
                <div className="ec-stack">
                    <div className="ec-search">
                        <span className="ec-search-icon">
                            <Search size={14} />
                        </span>
                        <Input value={search} placeholder={t('section.search')} onChange={(event) => setSearch(event.target.value)} />
                    </div>

                    {fileCards.length === 0 ? (
                        <Card>
                            <EmptyState>{t('section.no_files_yet')}</EmptyState>
                        </Card>
                    ) : (
                        fileCards.map(({ key, file, columns, templateId }) => (
                            <FileCard key={key} file={file} controller={controller} serverId={serverId} templateId={templateId} forceCollapsed={disabled} columns={columns} />
                        ))
                    )}
                </div>
                {disabled && <RunningOverlay state={state} stopping={stopping} onStop={onStop} />}
            </div>

            {(isDirty || justSaved) && !readOnly && <FloatingSaveBar saving={save.isPending} saved={justSaved} onSave={doSave} />}

            <CopyDialog open={copyOpen} onClose={() => setCopyOpen(false)} serverId={serverId} templates={templates} />
        </div>
    );
}
