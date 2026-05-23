import clsx from 'clsx';
import { ChevronDown, Link2 } from 'lucide-react';
import { useState } from 'react';
import { FieldRow } from '../../../fields/FieldRow';
import { pickLabel, useT } from '../../../lib/i18n';
import { getParam } from '../../../lib/paramEdit';
import type { Json } from '../../../lib/templateFiles';
import type { ConfigParam, DisplayType, LocaleLabel, ParamConfig } from '../../../types';
import { Badge } from '../../../ui/surfaces';
import { ParameterForm } from './ParameterForm';

interface Props {
    file: Json;
    section: string | null;
    paramKey: string;
    datalistId: string;
    lang: string;
    onChange: (file: Json) => void;
}

/**
 * One parameter row in the visual editor: a collapsed summary (key · type · env
 * link) that expands to the full edit form plus a live preview rendered exactly
 * as the player sees it (reusing FieldRow with a local, throwaway value).
 */
export function ParameterCard({ file, section, paramKey, datalistId, lang, onChange }: Props) {
    const { t } = useT();
    const [expanded, setExpanded] = useState(false);

    const param = getParam(file, section, paramKey);
    const config = (param?.config ?? {}) as ParamConfig;
    const [previewValue, setPreviewValue] = useState<string>(config.default === undefined ? '' : String(config.default));

    if (param === null) {
        return null;
    }

    const type = String(param.display_type ?? 'text');
    const envVar = String(param.env_var ?? '');
    const friendly = pickLabel((param.label ?? null) as LocaleLabel | null, lang, paramKey);

    const previewParam: ConfigParam = {
        key: paramKey,
        section,
        display_type: type as DisplayType,
        config,
        label: (param.label ?? null) as LocaleLabel | null,
        description: (param.description ?? null) as LocaleLabel | null,
        value: previewValue,
        inferred: false,
        env_var: envVar === '' ? null : envVar,
    };

    return (
        <div className={clsx('ec-section-group', !expanded && 'ec-section-collapsed')}>
            <button type="button" className="ec-section-head" onClick={() => setExpanded((v) => !v)} aria-expanded={expanded}>
                <span className="ec-section-chevron">
                    <ChevronDown size={16} />
                </span>
                <span className="ec-grow ec-truncate">{friendly}</span>
                {envVar !== '' && (
                    <Badge variant="info">
                        <Link2 size={11} /> {envVar}
                    </Badge>
                )}
                <Badge variant="muted">{t(`admin.visual.type_${type}`)}</Badge>
            </button>

            {expanded && (
                <div className="ec-section-body" style={{ padding: '0.85rem', gap: '0.85rem' }}>
                    <ParameterForm file={file} section={section} paramKey={paramKey} datalistId={datalistId} onChange={onChange} />
                    <div className="ec-stack" style={{ gap: '0.4rem' }}>
                        <span className="ec-field-desc ec-muted">{t('admin.visual.preview')}</span>
                        <FieldRow param={previewParam} value={previewValue} onChange={setPreviewValue} />
                    </div>
                </div>
            )}
        </div>
    );
}
