import clsx from 'clsx';
import { pickLabel, useT } from '../lib/i18n';
import type { ConfigParam, ParamConfig } from '../types';
import { Input, Select, Textarea, Toggle } from '../ui/inputs';

interface Props {
    param: ConfigParam;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    invalid?: boolean;
}

/** Renders the interactive control for a parameter, dispatched by display_type. */
export function FieldControl({ param, value, onChange, disabled, invalid }: Props) {
    const { lang } = useT();
    const config = param.config;

    switch (param.display_type) {
        case 'boolean':
            return <BooleanControl config={config} value={value} onChange={onChange} disabled={disabled} />;
        case 'slider':
            return <SliderControl config={config} value={value} onChange={onChange} disabled={disabled} invalid={invalid} />;
        case 'number':
            return (
                <Input
                    className="ec-input-narrow"
                    type="number"
                    min={config.min}
                    max={config.max}
                    step={config.step ?? (config.float ? 'any' : 1)}
                    value={value}
                    disabled={disabled}
                    invalid={invalid}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'select':
            return (
                <Select value={value} disabled={disabled} invalid={invalid} onChange={onChange}>
                    {(config.options ?? []).map((option) => (
                        <option key={option.value} value={option.value}>
                            {pickLabel(option.label, lang, option.value)}
                        </option>
                    ))}
                </Select>
            );
        case 'multiselect':
            return <MultiSelectControl config={config} value={value} onChange={onChange} disabled={disabled} />;
        case 'textarea':
            return (
                <Textarea
                    value={value}
                    disabled={disabled}
                    invalid={invalid}
                    maxLength={config.max_length}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'color':
            return <ColorControl value={value} onChange={onChange} disabled={disabled} invalid={invalid} />;
        default:
            return (
                <Input
                    value={value}
                    disabled={disabled}
                    invalid={invalid}
                    maxLength={config.max_length}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
    }
}

function BooleanControl({ config, value, onChange, disabled }: { config: ParamConfig; value: string; onChange: (v: string) => void; disabled?: boolean }) {
    const trueValue = config.true_value ?? 'true';
    const falseValue = config.false_value ?? 'false';

    return <Toggle checked={value === trueValue} disabled={disabled} onChange={(on) => onChange(on ? trueValue : falseValue)} />;
}

function SliderControl({ config, value, onChange, disabled, invalid }: { config: ParamConfig; value: string; onChange: (v: string) => void; disabled?: boolean; invalid?: boolean }) {
    const min = config.min ?? 0;
    const max = config.max ?? 100;
    const step = config.step ?? 1;

    return (
        <div className="ec-slider-wrap">
            <input
                type="range"
                className="ec-slider"
                min={min}
                max={max}
                step={step}
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
            <Input
                className="ec-slider-number"
                type="number"
                min={min}
                max={max}
                step={step}
                value={value}
                disabled={disabled}
                invalid={invalid}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function MultiSelectControl({ config, value, onChange, disabled }: { config: ParamConfig; value: string; onChange: (v: string) => void; disabled?: boolean }) {
    const { lang } = useT();
    const separator = config.separator && config.separator !== '' ? config.separator : ',';
    const selected = value.split(separator).map((item) => item.trim()).filter((item) => item !== '');

    const toggle = (optionValue: string): void => {
        const next = selected.includes(optionValue)
            ? selected.filter((item) => item !== optionValue)
            : [...selected, optionValue];
        onChange(next.join(separator));
    };

    return (
        <div className="ec-chips">
            {(config.options ?? []).map((option) => (
                <button
                    key={option.value}
                    type="button"
                    disabled={disabled}
                    className={clsx('ec-chip', selected.includes(option.value) && 'ec-chip-on')}
                    onClick={() => toggle(option.value)}
                >
                    {pickLabel(option.label, lang, option.value)}
                </button>
            ))}
        </div>
    );
}

function ColorControl({ value, onChange, disabled, invalid }: { value: string; onChange: (v: string) => void; disabled?: boolean; invalid?: boolean }) {
    const hex = /^#[0-9a-fA-F]{6}$/.test(value) ? value : `#${value}`;
    const safe = /^#[0-9a-fA-F]{6}$/.test(hex) ? hex : '#000000';

    return (
        <div className="ec-color">
            <input type="color" className="ec-color-swatch" value={safe} disabled={disabled} onChange={(event) => onChange(event.target.value)} />
            <Input className="ec-input-narrow" value={value} disabled={disabled} invalid={invalid} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}
