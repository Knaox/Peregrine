import { useState } from 'react';
import { useT } from '../../../lib/i18n';
import type { Json } from '../../../lib/templateFiles';
import { Select } from '../../../ui/inputs';
import { useServerCatalog } from '../../hooks/useTemplates';
import { useServerEnvVars } from '../../hooks/useServerEnvVars';
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
 * edits as text. Parses the JSON, offers an optional server picker to autocomplete
 * env-variable names, and renders one VisualFileEditor per file. Every edit is
 * serialised straight back through onChange, so the JSON tab stays in sync.
 */
export function VisualEditorPanel({ filesJson, lang, onChange }: { filesJson: string; lang: string; onChange: (filesJson: string) => void }) {
    const { t } = useT();
    const servers = useServerCatalog();
    const [serverId, setServerId] = useState('');
    const envVars = useServerEnvVars(serverId === '' ? null : Number(serverId));

    const files = parseFiles(filesJson);
    if (files === null) {
        return <span className="ec-field-desc ec-muted">{t('admin.editor.links_fix_json')}</span>;
    }
    if (files.length === 0) {
        return <div className="ec-empty">{t('admin.visual.empty')}</div>;
    }

    const replaceFile = (index: number, next: Json): void => {
        onChange(JSON.stringify(files.map((file, i) => (i === index ? next : file)), null, 2));
    };

    return (
        <div className="ec-stack">
            <span className="ec-field-desc ec-muted">{t('admin.visual.intro')}</span>

            <div className="ec-field-group">
                <label>{t('admin.editor.links_env_server')}</label>
                <Select value={serverId} onChange={setServerId} disabled={servers.isLoading}>
                    <option value="">{t('admin.editor.links_env_server_ph')}</option>
                    {(servers.data ?? []).map((server) => (
                        <option key={server.id} value={String(server.id)}>
                            {server.egg_name !== null ? `${server.name} — ${server.egg_name}` : server.name}
                        </option>
                    ))}
                </Select>
            </div>

            <datalist id={DATALIST_ID}>
                {(envVars.data ?? []).map((variable) => (
                    <option key={variable.env_variable} value={variable.env_variable} />
                ))}
            </datalist>

            {files.map((file, index) => (
                <VisualFileEditor
                    key={`${String(file.id ?? '')}-${index}`}
                    file={file}
                    datalistId={DATALIST_ID}
                    lang={lang}
                    onChange={(next) => replaceFile(index, next)}
                />
            ))}
        </div>
    );
}
