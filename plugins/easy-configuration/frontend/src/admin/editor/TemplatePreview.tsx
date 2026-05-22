import { useState } from 'react';
import { FieldRow } from '../../fields/FieldRow';
import { pickLabel, useT } from '../../lib/i18n';
import type { ConfigParam, DisplayType, LocaleLabel, ParamConfig } from '../../types';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toParam(key: string, section: string | null, def: Record<string, unknown>): ConfigParam {
    const config = isRecord(def.config) ? (def.config as ParamConfig) : {};
    const defaultValue = config.default;

    return {
        key,
        section,
        display_type: (typeof def.display_type === 'string' ? def.display_type : 'text') as DisplayType,
        config,
        label: isRecord(def.label) ? (def.label as LocaleLabel) : null,
        description: isRecord(def.description) ? (def.description as LocaleLabel) : null,
        value: defaultValue === undefined ? '' : String(defaultValue),
        inferred: false,
    };
}

function paramsOf(parameters: unknown): { sectioned: boolean; params: ConfigParam[] } {
    const params: ConfigParam[] = [];
    if (!isRecord(parameters)) {
        return { sectioned: false, params };
    }

    let sectioned = false;
    for (const [key, value] of Object.entries(parameters)) {
        if (isRecord(value) && 'display_type' in value) {
            params.push(toParam(key, null, value));
        } else if (isRecord(value)) {
            sectioned = true;
            for (const [childKey, childDef] of Object.entries(value)) {
                if (isRecord(childDef)) {
                    params.push(toParam(childKey, key, childDef));
                }
            }
        }
    }

    return { sectioned, params };
}

/** Renders the template exactly as a player would see it — fully interactive, local-only (no save). */
export function TemplatePreview({ files }: { files: unknown }) {
    const { t, lang } = useT();
    const [values, setValues] = useState<Record<string, string>>({});

    if (!Array.isArray(files) || files.length === 0) {
        return <div className="ec-empty">{t('admin.editor.preview_empty')}</div>;
    }

    return (
        <div className="ec-stack">
            {files.map((file, index) => {
                if (!isRecord(file)) {
                    return null;
                }
                const { params } = paramsOf(file.parameters);
                const title = isRecord(file.label) ? pickLabel(file.label as LocaleLabel, lang, String(file.path ?? '')) : String(file.path ?? `file ${index + 1}`);

                return (
                    <div key={index} className="ec-section-group">
                        <div className="ec-section-head">{title}</div>
                        <div className="ec-section-body">
                            {params.map((param) => {
                                const fieldKey = `${index}:${param.section ?? ''}:${param.key}`;

                                return (
                                    <FieldRow
                                        key={fieldKey}
                                        param={param}
                                        value={values[fieldKey] ?? param.value}
                                        onChange={(value) => setValues((prev) => ({ ...prev, [fieldKey]: value }))}
                                    />
                                );
                            })}
                            {params.length === 0 && <div className="ec-empty">{t('admin.editor.no_params')}</div>}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
