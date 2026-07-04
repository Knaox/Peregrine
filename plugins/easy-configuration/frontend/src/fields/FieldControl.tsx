import clsx from 'clsx';
import { pickLabel, useT } from '../lib/i18n';
import { SandboxCodeField } from '../sandbox/SandboxCodeField';
import type { ConfigParam, ParamConfig } from '../types';
import { Input, Select, Textarea, Toggle } from '../ui/inputs';

interface Props {
    param: ConfigParam;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    invalid?: boolean;
    /** Accessible name for the control — the parameter's visible label lives in a
     *  sibling element, so each control is named explicitly for screen readers. */
    ariaLabel?: string;
}

/** Renders the interactive control for a parameter, dispatched by display_type. */
export function FieldControl({ param, value, onChange, disabled, invalid, ariaLabel }: Props) {
    const { lang } = useT();
    const config = param.config;
    // Env-linked params hard-cap at `max` (the value also drives the Pelican
    // variable); others let the player type above the slider max manually.
    const envLinked = typeof param.env_var === 'string' && param.env_var !== '';

    // Generator-backed field (7DTD SandboxCode): the text value stays the
    // source of truth, an inline panel re-encodes it from game options.
    if (config.generator === '7dtd-sandbox') {
        return <SandboxCodeField value={value} onChange={onChange} disabled={disabled} invalid={invalid} ariaLabel={ariaLabel} />;
    }

    switch (param.display_type) {
        case 'boolean':
            return <BooleanControl config={config} value={value} onChange={onChange} disabled={disabled} ariaLabel={ariaLabel} />;
        case 'slider':
            return <SliderControl config={config} value={value} onChange={onChange} disabled={disabled} invalid={invalid} envLinked={envLinked} ariaLabel={ariaLabel} />;
        case 'number':
            return (
                <Input
                    className="ec-input-narrow"
                    type="number"
                    aria-label={ariaLabel}
                    min={envLinked ? config.min : undefined}
                    max={envLinked ? config.max : undefined}
                    step={config.step ?? (config.float ? 'any' : 1)}
                    value={value}
                    disabled={disabled}
                    invalid={invalid}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'select':
            return (
                <Select value={value} disabled={disabled} invalid={invalid} ariaLabel={ariaLabel} onChange={onChange}>
                    {(config.options ?? []).map((option) => (
                        <option key={option.value} value={option.value}>
                            {pickLabel(option.label, lang, option.value)}
                        </option>
                    ))}
                </Select>
            );
        case 'multiselect':
            return <MultiSelectControl config={config} value={value} onChange={onChange} disabled={disabled} ariaLabel={ariaLabel} />;
        case 'textarea':
            return (
                <Textarea
                    value={value}
                    aria-label={ariaLabel}
                    disabled={disabled}
                    invalid={invalid}
                    maxLength={config.max_length}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'color':
            return <ColorControl value={value} onChange={onChange} disabled={disabled} invalid={invalid} ariaLabel={ariaLabel} />;
        default:
            return (
                <Input
                    value={value}
                    aria-label={ariaLabel}
                    disabled={disabled}
                    invalid={invalid}
                    maxLength={config.max_length}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
    }
}

function BooleanControl({ config, value, onChange, disabled, ariaLabel }: { config: ParamConfig; value: string; onChange: (v: string) => void; disabled?: boolean; ariaLabel?: string }) {
    const trueValue = config.true_value ?? 'true';
    const falseValue = config.false_value ?? 'false';

    return <Toggle checked={value === trueValue} disabled={disabled} label={ariaLabel} onChange={(on) => onChange(on ? trueValue : falseValue)} />;
}

function SliderControl({ config, value, onChange, disabled, invalid, envLinked, ariaLabel }: { config: ParamConfig; value: string; onChange: (v: string) => void; disabled?: boolean; invalid?: boolean; envLinked?: boolean; ariaLabel?: string }) {
    const min = config.min ?? 0;
    const max = config.max ?? 100;
    const step = config.step ?? 1;

    return (
        <div className="ec-slider-wrap">
            <input
                type="range"
                className="ec-slider"
                aria-label={ariaLabel}
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
                aria-label={ariaLabel}
                min={envLinked ? min : undefined}
                max={envLinked ? max : undefined}
                step={step}
                value={value}
                disabled={disabled}
                invalid={invalid}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function MultiSelectControl({ config, value, onChange, disabled, ariaLabel }: { config: ParamConfig; value: string; onChange: (v: string) => void; disabled?: boolean; ariaLabel?: string }) {
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
        <div className="ec-chips" role="group" aria-label={ariaLabel}>
            {(config.options ?? []).map((option) => {
                const isOn = selected.includes(option.value);

                return (
                    <button
                        key={option.value}
                        type="button"
                        disabled={disabled}
                        aria-pressed={isOn}
                        className={clsx('ec-chip', isOn && 'ec-chip-on')}
                        onClick={() => toggle(option.value)}
                    >
                        {pickLabel(option.label, lang, option.value)}
                    </button>
                );
            })}
        </div>
    );
}

function ColorControl({ value, onChange, disabled, invalid, ariaLabel }: { value: string; onChange: (v: string) => void; disabled?: boolean; invalid?: boolean; ariaLabel?: string }) {
    const hex = /^#[0-9a-fA-F]{6}$/.test(value) ? value : `#${value}`;
    const safe = /^#[0-9a-fA-F]{6}$/.test(hex) ? hex : '#000000';

    return (
        <div className="ec-color">
            <input type="color" className="ec-color-swatch" aria-label={ariaLabel} value={safe} disabled={disabled} onChange={(event) => onChange(event.target.value)} />
            <Input className="ec-input-narrow" value={value} aria-label={ariaLabel} disabled={disabled} invalid={invalid} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}
