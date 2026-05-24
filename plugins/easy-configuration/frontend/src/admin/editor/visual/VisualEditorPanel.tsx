import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../../../lib/i18n';
import { appendBlankFile, type Json } from '../../../lib/templateFiles';
import { Button } from '../../../ui/Button';
import { Select } from '../../../ui/inputs';
import { useEggCatalog } from '../../hooks/useTemplates';
import { useEggEnvVars } from '../../hooks/useEggEnvVars';
import { VisualFileEditor } from './VisualFileEditor';

const DATALIST_ID = 'ec-visual-envvars';

function parseFiles(json: string): Json[] | null {
    try {
        const parsed = JSON.parse(json);

        return Array.isArray(parsed) ? (parsed as Json[]) : null;
    } catch {
        return null;
    }
}

/**
 * Visual template editor: a structured lens over the same `files` JSON the admin
 * edits as text. Parses the JSON, offers an optional egg picker to autocomplete
 * env-variable names, and renders one VisualFileEditor per file. Every edit is
 * serialised straight back through onChange, so the JSON tab stays in sync.
 */
export function VisualEditorPanel({ filesJson, lang, onChange }: { filesJson: string; lang: string; onChange: (filesJson: string) => void }) {
    const { t } = useT();
    const eggs = useEggCatalog();
    const [eggId, setEggId] = useState('');
    const envVars = useEggEnvVars(eggId === '' ? null : Number(eggId));

    const files = parseFiles(filesJson);
    if (files === null) {
        return <span className="ec-field-desc ec-muted">{t('admin.editor.links_fix_json')}</span>;
    }

    const replaceFile = (index: number, next: Json): void => {
        onChange(JSON.stringify(files.map((file, i) => (i === index ? next : file)), null, 2));
    };
    // Append-only: spreads the existing files so no defined file/parameter is lost.
    const addFile = (): void => onChange(JSON.stringify(appendBlankFile(files), null, 2));

    return (
        <div className="ec-stack">
            <span className="ec-field-desc ec-muted">{t('admin.visual.intro')}</span>

            <div className="ec-field-group">
                <label>{t('admin.editor.links_env_egg')}</label>
                <Select value={eggId} onChange={setEggId} disabled={eggs.isLoading}>
                    <option value="">{t('admin.editor.links_env_egg_ph')}</option>
                    {(eggs.data ?? []).map((egg) => (
                        <option key={egg.id} value={String(egg.id)}>
                            {egg.name}
                        </option>
                    ))}
                </Select>
            </div>

            <datalist id={DATALIST_ID}>
                {(envVars.data ?? []).map((variable) => (
                    <option key={variable.env_variable} value={variable.env_variable} />
                ))}
            </datalist>

            {files.length === 0 ? (
                <div className="ec-empty">{t('admin.visual.empty')}</div>
            ) : (
                files.map((file, index) => (
                    <VisualFileEditor
                        key={`${String(file.id ?? '')}-${index}`}
                        file={file}
                        datalistId={DATALIST_ID}
                        lang={lang}
                        onChange={(next) => replaceFile(index, next)}
                    />
                ))
            )}

            <div>
                <Button variant="ghost" size="sm" onClick={addFile}>
                    <Plus size={14} /> {t('admin.visual.add_file')}
                </Button>
            </div>
        </div>
    );
}
