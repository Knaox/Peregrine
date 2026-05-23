import type { ReactNode } from 'react';
import { useT } from '../../../lib/i18n';
import { getOptions, getParam, setConfigField, setDisplayType, setLocale, setOptions } from '../../../lib/paramEdit';
import { setEnvVar, type Json } from '../../../lib/templateFiles';
import { Input, Select, Toggle } from '../../../ui/inputs';
import { OptionsEditor } from './OptionsEditor';

const TYPES = ['boolean', 'slider', 'select', 'multiselect', 'text', 'number', 'textarea', 'color'];

interface Props {
    file: Json;
    section: string | null;
    paramKey: string;
    datalistId: string;
    onChange: (file: Json) => void;
}

/**
 * Visual editor for one parameter: localised label/description, display type,
 * default value, env-var link, and the type-specific config (only the fields
 * relevant to the chosen display type are shown — progressive disclosure).
 */
export function ParameterForm({ file, section, paramKey, datalistId, onChange }: Props) {
    const { t } = useT();
    const param = getParam(file, section, paramKey);
    if (param === null) {
        return null;
    }

    const config = (param.config ?? {}) as Record<string, unknown>;
    const type = String(param.display_type ?? 'text');
    const label = (param.label ?? {}) as Record<string, string>;
    const desc = (param.description ?? {}) as Record<string, string>;

    const cfg = (key: string): string => {
        const value = config[key];
        return value === undefined || value === null ? '' : String(value);
    };
    const num = (key: string, value: string): void => onChange(setConfigField(file, section, paramKey, key, value === '' ? undefined : Number(value)));
    const str = (key: string, value: string): void => onChange(setConfigField(file, section, paramKey, key, value));
    const loc = (field: 'label' | 'description', lang: 'en' | 'fr', value: string): void => onChange(setLocale(file, section, paramKey, field, lang, value));

    const field = (labelText: string, control: ReactNode): ReactNode => (
        <div className="ec-field-group">
            <label>{labelText}</label>
            {control}
        </div>
    );

    return (
        <div className="ec-stack" style={{ gap: '0.6rem' }}>
            <div className="ec-cols-2">
                {field(t('admin.visual.label_en'), <Input value={label.en ?? ''} onChange={(e) => loc('label', 'en', e.target.value)} />)}
                {field(t('admin.visual.label_fr'), <Input value={label.fr ?? ''} onChange={(e) => loc('label', 'fr', e.target.value)} />)}
            </div>
            <div className="ec-cols-2">
                {field(t('admin.visual.desc_en'), <Input value={desc.en ?? ''} onChange={(e) => loc('description', 'en', e.target.value)} />)}
                {field(t('admin.visual.desc_fr'), <Input value={desc.fr ?? ''} onChange={(e) => loc('description', 'fr', e.target.value)} />)}
            </div>
            <div className="ec-cols-2">
                {field(
                    t('admin.visual.type'),
                    <Select value={type} onChange={(value) => onChange(setDisplayType(file, section, paramKey, value))}>
                        {TYPES.map((ty) => (
                            <option key={ty} value={ty}>{t(`admin.visual.type_${ty}`)}</option>
                        ))}
                    </Select>,
                )}
                {field(t('admin.visual.default'), <Input value={cfg('default')} onChange={(e) => str('default', e.target.value)} />)}
            </div>
            {field(
                t('admin.visual.env_var'),
                <Input list={datalistId} value={String(param.env_var ?? '')} placeholder={t('admin.editor.links_param')} onChange={(e) => onChange(setEnvVar(file, section, paramKey, e.target.value))} />,
            )}

            {(type === 'number' || type === 'slider') && (
                <>
                    <div className="ec-cols-2">
                        {field(t('admin.visual.min'), <Input type="number" value={cfg('min')} onChange={(e) => num('min', e.target.value)} />)}
                        {field(t('admin.visual.max'), <Input type="number" value={cfg('max')} onChange={(e) => num('max', e.target.value)} />)}
                    </div>
                    <div className="ec-cols-2">
                        {field(t('admin.visual.step'), <Input type="number" value={cfg('step')} onChange={(e) => num('step', e.target.value)} />)}
                        {field(t('admin.visual.suffix'), <Input value={cfg('suffix')} onChange={(e) => str('suffix', e.target.value)} />)}
                    </div>
                    <label className="ec-row" style={{ cursor: 'pointer', gap: '0.5rem' }}>
                        <Toggle checked={Boolean(config.float)} onChange={(on) => onChange(setConfigField(file, section, paramKey, 'float', on ? true : undefined))} label={t('admin.visual.float')} />
                        <span className="ec-field-desc">{t('admin.visual.float')}</span>
                    </label>
                </>
            )}

            {(type === 'select' || type === 'multiselect') && (
                <>
                    {field(t('admin.visual.options'), <OptionsEditor options={getOptions(file, section, paramKey)} onChange={(options) => onChange(setOptions(file, section, paramKey, options))} />)}
                    {type === 'multiselect' && field(t('admin.visual.separator'), <Input value={cfg('separator')} placeholder="," onChange={(e) => str('separator', e.target.value)} />)}
                </>
            )}

            {(type === 'text' || type === 'textarea') && (
                <div className="ec-cols-2">
                    {field(t('admin.visual.max_length'), <Input type="number" value={cfg('max_length')} onChange={(e) => num('max_length', e.target.value)} />)}
                    {field(t('admin.visual.regex'), <Input value={cfg('regex')} onChange={(e) => str('regex', e.target.value)} />)}
                </div>
            )}

            {type === 'color' && field(t('admin.visual.format'), <Input value={cfg('format')} placeholder="hex" onChange={(e) => str('format', e.target.value)} />)}

            {type === 'boolean' && (
                <div className="ec-cols-2">
                    {field(t('admin.visual.true_value'), <Input value={cfg('true_value')} placeholder="true" onChange={(e) => str('true_value', e.target.value)} />)}
                    {field(t('admin.visual.false_value'), <Input value={cfg('false_value')} placeholder="false" onChange={(e) => str('false_value', e.target.value)} />)}
                </div>
            )}
        </div>
    );
}
