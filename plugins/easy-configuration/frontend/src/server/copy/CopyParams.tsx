import { fieldKeyOf } from '../../lib/fieldKey';
import { pickLabel, useT } from '../../lib/i18n';
import type { ConfigFile, ConfigTemplate } from '../../types';

interface Props {
    templates: ConfigTemplate[];
    selected: Record<string, boolean>;
    setSelected: (updater: (current: Record<string, boolean>) => Record<string, boolean>) => void;
}

const isOn = (selected: Record<string, boolean>, key: string): boolean => selected[key] ?? true;

/** Step 2: choose which files/parameters to copy. Everything is selected by default. */
export function CopyParams({ templates, selected, setSelected }: Props) {
    const { t, lang } = useT();
    const files = templates.flatMap((template) => template.files);

    const toggleParam = (key: string): void => {
        setSelected((current) => ({ ...current, [key]: !isOn(current, key) }));
    };

    const allOn = (file: ConfigFile): boolean => file.parameters.every((param) => isOn(selected, fieldKeyOf(file.id, param)));

    const toggleFile = (file: ConfigFile): void => {
        const next = !allOn(file);
        setSelected((current) => {
            const updated = { ...current };
            for (const param of file.parameters) {
                updated[fieldKeyOf(file.id, param)] = next;
            }

            return updated;
        });
    };

    if (files.length === 0) {
        return <div className="ec-empty">{t('copy.no_params')}</div>;
    }

    return (
        <div className="ec-stack">
            {files.map((file) => (
                <div className="ec-section-group" key={file.id}>
                    <label className="ec-section-head ec-check-row">
                        <input type="checkbox" checked={allOn(file)} onChange={() => toggleFile(file)} />
                        <span>{pickLabel(file.label, lang, file.path)}</span>
                        <span className="ec-section-count">{file.parameters.length}</span>
                    </label>
                    <div className="ec-section-body">
                        {file.parameters.map((param) => {
                            const key = fieldKeyOf(file.id, param);

                            return (
                                <label key={key} className="ec-field ec-check-row">
                                    <input type="checkbox" checked={isOn(selected, key)} onChange={() => toggleParam(key)} />
                                    <span className="ec-grow ec-truncate">{pickLabel(param.label, lang, param.key)}</span>
                                    <span className="ec-field-desc ec-muted">{param.section ? `${param.section} · ${param.key}` : param.key}</span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
