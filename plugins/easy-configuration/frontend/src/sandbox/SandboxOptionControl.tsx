import clsx from 'clsx';
import { useT } from '../lib/i18n';
import { Select, Toggle } from '../ui/inputs';
import { optionDescription, optionLabel, type SandboxOption, valueLabel, valuesOf } from './codec';

/**
 * One sandbox option: localised label (+ modified dot) above its control — a
 * toggle for booleans, a select listing the option's value ladder otherwise.
 * The whole row greys out when another option's current value disables it.
 */
export function SandboxOptionControl({
    option,
    valueIndex,
    modified,
    disabled,
    onPick,
}: {
    option: SandboxOption;
    valueIndex: number;
    modified: boolean;
    disabled: boolean;
    onPick: (valueIndex: number) => void;
}) {
    const { lang } = useT();
    const values = valuesOf(option);
    const label = optionLabel(option, lang);

    return (
        <div className={clsx('sbx-opt', disabled && 'sbx-opt-disabled')} title={optionDescription(option, lang)}>
            <div className="sbx-opt-head">
                <span className="sbx-opt-label">{label}</span>
                {modified && <span className="sbx-opt-dot" aria-hidden="true" />}
            </div>
            {option.kind === 'bool' ? (
                <Toggle
                    checked={values[valueIndex] === true}
                    disabled={disabled}
                    label={label}
                    onChange={(on) => onPick(Math.max(0, values.findIndex((value) => value === on)))}
                />
            ) : (
                <Select value={String(valueIndex)} disabled={disabled} ariaLabel={label} onChange={(next) => onPick(Number(next))}>
                    {values.map((_, index) => (
                        <option key={index} value={index}>
                            {valueLabel(option, index, lang)}
                        </option>
                    ))}
                </Select>
            )}
        </div>
    );
}
