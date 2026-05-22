import { Copy, Search, SlidersHorizontal } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { backendFieldKey, fieldKeyOf } from '../lib/fieldKey';
import { pickLabel, useT } from '../lib/i18n';
import { validateValue } from '../lib/validate';
import type { ApiError } from '../shared';
import type { ConfigParam, ConfigTemplate } from '../types';
import { Button } from '../ui/Button';
import { Input } from '../ui/inputs';
import { useToast } from '../ui/Toast';
import type { EditorController } from './controller';
import { CopyDialog } from './copy/CopyDialog';
import { FileCard } from './FileCard';
import { FloatingSaveBar } from './FloatingSaveBar';
import { useSaveConfig, type SaveFilePayload } from './hooks/useServerConfig';

export function ConfigEditor({ serverId, templates, disabled }: { serverId: number; templates: ConfigTemplate[]; disabled: boolean }) {
    const { t, lang } = useT();
    const toast = useToast();
    const save = useSaveConfig(serverId);

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
    const [search, setSearch] = useState('');
    const [copyOpen, setCopyOpen] = useState(false);

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
        if (!isDirty || disabled || save.isPending) {
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
            list.push({ key: entry.param.key, section: entry.param.section, value: values[key] ?? '' });
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
    }, [isDirty, disabled, hasInvalid, dirtyKeys, index, values, save, toast, t]);

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
        disabled,
        search,
        onChange,
        onReset,
    };

    const fileCards = templates.flatMap((template) => template.files.map((file) => ({ key: `${template.id}:${file.id}`, file })));

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
                </div>
                <Button variant="secondary" onClick={() => setCopyOpen(true)}>
                    <Copy size={15} /> {t('copy.button')}
                </Button>
            </div>

            <div className="ec-search">
                <span className="ec-search-icon">
                    <Search size={14} />
                </span>
                <Input value={search} placeholder={t('section.search')} onChange={(event) => setSearch(event.target.value)} />
            </div>

            {fileCards.map(({ key, file }) => (
                <FileCard key={key} file={file} controller={controller} serverId={serverId} />
            ))}

            {(isDirty || justSaved) && !disabled && <FloatingSaveBar saving={save.isPending} saved={justSaved} onSave={doSave} />}

            <CopyDialog open={copyOpen} onClose={() => setCopyOpen(false)} serverId={serverId} templates={templates} />
        </div>
    );
}
