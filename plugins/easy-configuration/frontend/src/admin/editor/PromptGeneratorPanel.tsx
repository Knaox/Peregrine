import { ClipboardPaste, Copy, DownloadCloud, FilePlus2, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../../lib/i18n';
import { appendBlankFile, setExpandedByDefault, type Json } from '../../lib/templateFiles';
import type { EggOption } from '../../types';
import { Button } from '../../ui/Button';
import { Input, Select, Textarea, Toggle } from '../../ui/inputs';
import { Card, EmptyState } from '../../ui/surfaces';
import { useToast } from '../../ui/Toast';
import { useEggEnvVars } from '../hooks/useEggEnvVars';
import { buildPrompt, fileParamsForPrompt, promptFilesFrom, type PromptEnvLink } from './buildPrompt';
import type { Draft } from './draft';
import { EggSelector } from './EggSelector';
import { EnvLinkList, type ParamOption } from './EnvLinkList';
import { SectionWhitelist } from './SectionWhitelist';

const DATALIST_ID = 'ec-prompt-envvars';
const SEP = String.fromCharCode(0x1f);

function parseFiles(json: string): Json[] | null {
    try {
        const parsed = JSON.parse(json);

        return Array.isArray(parsed) ? (parsed as Json[]) : null;
    } catch {
        return null;
    }
}

interface Props {
    draft: Draft;
    patch: (partial: Partial<Draft>) => void;
    eggs: EggOption[];
    onOpenImport: () => void;
    onLoadTemplate: (template: Record<string, unknown>) => void;
}

/**
 * "AI prompt" tab: gathers the template options (eggs, columns, boost), the
 * config files imported from a real server (every key + value + type) and the
 * env_var links, then builds one self-contained prompt the admin pastes into any
 * chat AI. The returned JSON is pasted back and loaded into the editor to review.
 */
export function PromptGeneratorPanel({ draft, patch, eggs, onOpenImport, onLoadTemplate }: Props) {
    const { t } = useT();
    const toast = useToast();

    const [sourceEgg, setSourceEgg] = useState('');
    const [envLinks, setEnvLinks] = useState<PromptEnvLink[]>([]);
    const [prompt, setPrompt] = useState('');
    const [pasted, setPasted] = useState('');

    const files = parseFiles(draft.filesJson);
    const effectiveEgg = sourceEgg !== '' ? sourceEgg : draft.targetEggs[0] !== undefined ? String(draft.targetEggs[0]) : '';
    const envVars = useEggEnvVars(effectiveEgg === '' ? null : Number(effectiveEgg));

    const paramOptions: ParamOption[] = (files ?? []).flatMap((file) => {
        const fileId = String(file.id ?? '');

        return fileParamsForPrompt(file).map((p) => ({
            id: `${fileId}${SEP}${p.section ?? ''}${SEP}${p.key}`,
            fileId,
            section: p.section,
            key: p.key,
            label: `${fileId} · ${p.section !== null ? `${p.section} · ` : ''}${p.key}`,
        }));
    });

    if (files === null) {
        return <span className="ec-field-desc ec-muted">{t('admin.editor.invalid_files_json')}</span>;
    }

    const replaceFile = (index: number, next: Json): void => {
        patch({ filesJson: JSON.stringify(files.map((file, i) => (i === index ? next : file)), null, 2) });
    };
    const toggleExpanded = (index: number, on: boolean): void => replaceFile(index, setExpandedByDefault(files[index], on));

    const onGenerate = (): void => {
        setPrompt(buildPrompt({
            id: draft.id,
            nameEn: draft.nameEn,
            nameFr: draft.nameFr,
            descEn: draft.descEn,
            descFr: draft.descFr,
            author: draft.author,
            targetEggs: draft.targetEggs,
            columns: draft.columns,
            boostEnabled: draft.boostEnabled,
            blacklist: draft.blacklist.split(',').map((s) => s.trim()).filter((s) => s !== ''),
            files: promptFilesFrom(files),
            envLinks: envLinks.filter((l) => l.envVar.trim() !== ''),
        }));
    };

    const onCopy = (): void => {
        navigator.clipboard.writeText(prompt).then(() => toast.success(t('admin.prompt.copied')), () => toast.error(t('admin.prompt.copy_failed')));
    };

    const onLoad = (): void => {
        let parsed: unknown;
        try {
            parsed = JSON.parse(pasted);
        } catch {
            toast.error(t('admin.prompt.load_invalid'));

            return;
        }
        if (typeof parsed !== 'object' || parsed === null || !Array.isArray((parsed as Record<string, unknown>).files)) {
            toast.error(t('admin.prompt.load_invalid'));

            return;
        }
        onLoadTemplate(parsed as Record<string, unknown>);
    };

    return (
        <div className="ec-stack">
            <span className="ec-field-desc ec-muted">{t('admin.prompt.intro')}</span>

            <Card>
                <EggSelector value={draft.targetEggs} onChange={(ids) => patch({ targetEggs: ids })} eggs={eggs} />
            </Card>

            <Card>
                <div className="ec-stack">
                    <div className="ec-field-group">
                        <label>{t('admin.editor.columns')}</label>
                        <Select value={String(draft.columns)} onChange={(value) => patch({ columns: Number(value) })}>
                            <option value="1">{t('admin.editor.columns_1')}</option>
                            <option value="2">{t('admin.editor.columns_2')}</option>
                            <option value="3">{t('admin.editor.columns_3')}</option>
                        </Select>
                    </div>
                    <div className="ec-between">
                        <span className="ec-field-label">{t('admin.editor.boost_enabled')}</span>
                        <Toggle checked={draft.boostEnabled} onChange={(on) => patch({ boostEnabled: on })} label={t('admin.editor.boost_enabled')} />
                    </div>
                    {draft.boostEnabled && (
                        <Input value={draft.blacklist} placeholder="server-port, rcon.port" onChange={(e) => patch({ blacklist: e.target.value })} />
                    )}
                </div>
            </Card>

            <Card>
                <div className="ec-stack">
                    <div className="ec-between">
                        <span className="ec-field-label">{t('admin.prompt.files_title')}</span>
                        <div className="ec-row">
                            <Button variant="secondary" size="sm" onClick={onOpenImport}>
                                <DownloadCloud size={15} /> {t('admin.editor.import_button')}
                            </Button>
                            <Button variant="ghost" size="sm" onClick={() => patch({ filesJson: JSON.stringify(appendBlankFile(files), null, 2) })}>
                                <FilePlus2 size={15} /> {t('admin.visual.add_file')}
                            </Button>
                        </div>
                    </div>
                    {files.length === 0 ? (
                        <EmptyState>{t('admin.prompt.no_files')}</EmptyState>
                    ) : (
                        files.map((file, index) => (
                            <div key={`${String(file.id ?? '')}-${index}`} className="ec-stack">
                                <div className="ec-between">
                                    <span className="ec-field-desc ec-truncate">
                                        {String(file.path ?? file.id ?? '')} · {fileParamsForPrompt(file).length} {t('admin.prompt.params_count')}
                                    </span>
                                    <label className="ec-row" style={{ cursor: 'pointer', gap: '0.5rem' }}>
                                        <span className="ec-field-desc ec-secondary">{t('admin.visual.expanded_by_default')}</span>
                                        <Toggle checked={file.expanded_by_default === true} onChange={(on) => toggleExpanded(index, on)} label={t('admin.visual.expanded_by_default')} />
                                    </label>
                                </div>
                                <SectionWhitelist file={file} onChange={(next) => replaceFile(index, next)} />
                            </div>
                        ))
                    )}
                </div>
            </Card>

            <Card>
                <div className="ec-stack">
                    <span className="ec-field-label">{t('admin.prompt.links_title')}</span>
                    <div className="ec-field-group">
                        <label>{t('admin.prompt.source_egg')}</label>
                        <Select value={effectiveEgg} onChange={setSourceEgg}>
                            <option value="">{t('admin.prompt.source_egg_ph')}</option>
                            {draft.targetEggs.map((id) => (
                                <option key={id} value={String(id)}>
                                    {eggs.find((e) => e.id === id)?.name ?? `#${id}`}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <datalist id={DATALIST_ID}>
                        {(envVars.data ?? []).map((variable) => (
                            <option key={variable.env_variable} value={variable.env_variable} />
                        ))}
                    </datalist>
                    <EnvLinkList value={envLinks} onChange={setEnvLinks} paramOptions={paramOptions} datalistId={DATALIST_ID} />
                </div>
            </Card>

            <Card>
                <div className="ec-stack">
                    <Button onClick={onGenerate}>
                        <Sparkles size={15} /> {t('admin.prompt.generate')}
                    </Button>
                    {prompt !== '' && (
                        <>
                            <Textarea className="ec-mono" value={prompt} readOnly rows={12} spellCheck={false} />
                            <div>
                                <Button variant="secondary" size="sm" onClick={onCopy}>
                                    <Copy size={15} /> {t('admin.prompt.copy')}
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </Card>

            <Card>
                <div className="ec-stack">
                    <span className="ec-field-label">{t('admin.prompt.paste_label')}</span>
                    <Textarea className="ec-mono" value={pasted} placeholder={t('admin.prompt.paste_ph')} spellCheck={false} onChange={(e) => setPasted(e.target.value)} />
                    <div>
                        <Button variant="secondary" disabled={pasted.trim() === ''} onClick={onLoad}>
                            <ClipboardPaste size={15} /> {t('admin.prompt.load')}
                        </Button>
                    </div>
                </div>
            </Card>
        </div>
    );
}
