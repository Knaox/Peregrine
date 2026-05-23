import { ArrowLeft, DownloadCloud, Save, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useT } from '../../lib/i18n';
import { ADMIN_PATH, type ApiError } from '../../shared';
import { Button } from '../../ui/Button';
import { Input, Select, Textarea, Toggle } from '../../ui/inputs';
import { Card, Tabs } from '../../ui/surfaces';
import { useToast } from '../../ui/Toast';
import { useEggCatalog, useSaveTemplate } from '../hooks/useTemplates';
import { EggSelector } from './EggSelector';
import { ImportFromServerDialog } from './ImportFromServerDialog';
import { TemplatePreview } from './TemplatePreview';
import { VisualEditorPanel } from './visual/VisualEditorPanel';

export interface Draft {
    id: string;
    version: string;
    nameEn: string;
    nameFr: string;
    descEn: string;
    descFr: string;
    author: string;
    targetEggs: number[];
    boostEnabled: boolean;
    blacklist: string;
    columns: number;
    filesJson: string;
}

function label(en: string, fr: string): Record<string, string> {
    const out: Record<string, string> = {};
    if (en.trim() !== '') {
        out.en = en.trim();
    }
    if (fr.trim() !== '') {
        out.fr = fr.trim();
    }

    return out;
}

function safeParse(json: string): unknown {
    try {
        return JSON.parse(json);
    } catch {
        return null;
    }
}

export function EditorForm({ initial, isNew }: { initial: Draft; isNew: boolean }) {
    const { t, lang } = useT();
    const navigate = useNavigate();
    const toast = useToast();
    const eggs = useEggCatalog();
    const save = useSaveTemplate();

    const [draft, setDraft] = useState<Draft>(initial);
    const [tab, setTab] = useState<'edit' | 'visual' | 'preview'>('edit');
    const [errors, setErrors] = useState<string[]>([]);
    const [importOpen, setImportOpen] = useState(false);

    const patch = (partial: Partial<Draft>): void => setDraft((current) => ({ ...current, ...partial }));

    // Append a scaffolded file (imported from a server) to the files array.
    // MUST read the latest state inside the functional updater: a batch import
    // calls this several times in a row, so reading `draft.filesJson` from the
    // render closure would make each append clobber the previous one (only the
    // last file would survive). User feedback (the batch toast) is owned by the
    // import dialog.
    const onImported = (file: Record<string, unknown>): void => {
        setDraft((current) => {
            const parsed = safeParse(current.filesJson);
            const files = Array.isArray(parsed) ? parsed : [];

            return { ...current, filesJson: JSON.stringify([...files, file], null, 2) };
        });
    };

    const onSave = (): void => {
        const files = safeParse(draft.filesJson);
        if (files === null) {
            setErrors([t('admin.editor.invalid_files_json')]);

            return;
        }

        const template: Record<string, unknown> = {
            id: draft.id,
            version: draft.version === '' ? '1.0.0' : draft.version,
            name: label(draft.nameEn, draft.nameFr),
            description: label(draft.descEn, draft.descFr),
            author: draft.author === '' ? null : draft.author,
            target_eggs: draft.targetEggs,
            columns: draft.columns,
            boost: {
                enabled: draft.boostEnabled,
                parameter_blacklist: draft.blacklist.split(',').map((item) => item.trim()).filter((item) => item !== ''),
            },
            files,
        };

        setErrors([]);
        save.mutate(
            { id: isNew ? null : draft.id, template },
            {
                onSuccess: () => {
                    toast.success(t('admin.editor.saved'));
                    navigate(ADMIN_PATH);
                },
                onError: (error) => {
                    const apiError = error as unknown as ApiError;
                    setErrors(apiError.messages ?? [apiError.message ?? t('errors.generic')]);
                    toast.error(t('admin.editor.save_failed'));
                },
            },
        );
    };

    return (
        <div className="ec-page">
            <div className="ec-between">
                <div className="ec-row">
                    <Button variant="ghost" onClick={() => navigate(ADMIN_PATH)}>
                        <ArrowLeft size={15} /> {t('common.back')}
                    </Button>
                    <h1 className="ec-title">{isNew ? t('admin.editor.title_new') : draft.id}</h1>
                </div>
                <Button loading={save.isPending} onClick={onSave}>
                    <Save size={15} /> {t('common.save')}
                </Button>
            </div>

            {errors.length > 0 && (
                <Card>
                    <ul className="ec-error-list">
                        {errors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </Card>
            )}

            <Tabs
                active={tab}
                onChange={(id) => setTab(id as 'edit' | 'visual' | 'preview')}
                tabs={[
                    { id: 'edit', label: t('admin.editor.tab_edit') },
                    { id: 'visual', label: t('admin.editor.tab_visual') },
                    { id: 'preview', label: t('admin.editor.tab_preview') },
                ]}
            />

            {tab === 'edit' ? (
                <div className="ec-stack">
                    <Card>
                        <div className="ec-stack">
                            <div className="ec-field-group">
                                <label>{t('admin.editor.id')}</label>
                                <Input value={draft.id} disabled={!isNew} placeholder="minecraft-vanilla" onChange={(e) => patch({ id: e.target.value })} />
                            </div>
                            <div className="ec-cols-2">
                                <div className="ec-field-group">
                                    <label>{t('admin.editor.name_en')}</label>
                                    <Input value={draft.nameEn} onChange={(e) => patch({ nameEn: e.target.value })} />
                                </div>
                                <div className="ec-field-group">
                                    <label>{t('admin.editor.name_fr')}</label>
                                    <Input value={draft.nameFr} onChange={(e) => patch({ nameFr: e.target.value })} />
                                </div>
                            </div>
                            <div className="ec-cols-2">
                                <div className="ec-field-group">
                                    <label>{t('admin.editor.desc_en')}</label>
                                    <Input value={draft.descEn} onChange={(e) => patch({ descEn: e.target.value })} />
                                </div>
                                <div className="ec-field-group">
                                    <label>{t('admin.editor.desc_fr')}</label>
                                    <Input value={draft.descFr} onChange={(e) => patch({ descFr: e.target.value })} />
                                </div>
                            </div>
                            <div className="ec-field-group">
                                <label>{t('admin.editor.author')}</label>
                                <Input value={draft.author} onChange={(e) => patch({ author: e.target.value })} />
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <EggSelector value={draft.targetEggs} onChange={(ids) => patch({ targetEggs: ids })} eggs={eggs.data ?? []} loading={eggs.isLoading} />
                    </Card>

                    <Card>
                        <div className="ec-field-group">
                            <label>{t('admin.editor.columns')}</label>
                            <Select value={String(draft.columns)} onChange={(value) => patch({ columns: Number(value) })}>
                                <option value="1">{t('admin.editor.columns_1')}</option>
                                <option value="2">{t('admin.editor.columns_2')}</option>
                                <option value="3">{t('admin.editor.columns_3')}</option>
                            </Select>
                            <span className="ec-field-desc ec-muted">{t('admin.editor.columns_hint')}</span>
                        </div>
                    </Card>

                    <Card>
                        <div className="ec-stack">
                            <div className="ec-between">
                                <span className="ec-field-label">{t('admin.editor.boost_enabled')}</span>
                                <Toggle checked={draft.boostEnabled} onChange={(on) => patch({ boostEnabled: on })} label={t('admin.editor.boost_enabled')} />
                            </div>
                            {draft.boostEnabled && (
                                <div className="ec-field-group">
                                    <label>{t('admin.editor.boost_blacklist')}</label>
                                    <Input value={draft.blacklist} placeholder="server-port, rcon.port" onChange={(e) => patch({ blacklist: e.target.value })} />
                                    <span className="ec-field-desc ec-muted">{t('admin.editor.boost_blacklist_hint')}</span>
                                </div>
                            )}
                        </div>
                    </Card>

                    <Card>
                        <div className="ec-stack">
                            <div className="ec-between">
                                <span className="ec-field-label">{t('admin.editor.files_json')}</span>
                                <div className="ec-row">
                                    <Button variant="secondary" size="sm" onClick={() => setTab('visual')}>
                                        <SlidersHorizontal size={15} /> {t('admin.editor.open_visual')}
                                    </Button>
                                    <Button variant="secondary" size="sm" onClick={() => setImportOpen(true)}>
                                        <DownloadCloud size={15} /> {t('admin.editor.import_button')}
                                    </Button>
                                </div>
                            </div>
                            <Textarea className="ec-mono" value={draft.filesJson} spellCheck={false} onChange={(e) => patch({ filesJson: e.target.value })} />
                        </div>
                    </Card>
                </div>
            ) : tab === 'visual' ? (
                <VisualEditorPanel filesJson={draft.filesJson} lang={lang} onChange={(filesJson) => patch({ filesJson })} />
            ) : (
                <TemplatePreview files={safeParse(draft.filesJson)} />
            )}

            <ImportFromServerDialog open={importOpen} onClose={() => setImportOpen(false)} onImported={onImported} />
        </div>
    );
}
