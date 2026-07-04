import clsx from 'clsx';
import { useT } from '../lib/i18n';
import { Select, Toggle } from '../ui/inputs';
import { type SandboxOption, valueLabel, valuesOf } from './codec';

/**
 * One sandbox option: label (+ modified dot) above its control — a toggle for
 * booleans, a select listing the option's value ladder otherwise. The whole
 * row greys out when another option's current value disables this one.
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
    const { t } = useT();
    const values = valuesOf(option);
    const yes = t('sandbox.yes');
    const no = t('sandbox.no');

    return (
        <div className={clsx('sbx-opt', disabled && 'sbx-opt-disabled')} title={option.description ?? ''}>
            <div className="sbx-opt-head">
                <span className="sbx-opt-label">{option.label}</span>
                {modified && <span className="sbx-opt-dot" aria-hidden="true" />}
            </div>
            {option.kind === 'bool' ? (
                <Toggle
                    checked={values[valueIndex] === true}
                    disabled={disabled}
                    label={option.label}
                    onChange={(on) => onPick(Math.max(0, values.findIndex((value) => value === on)))}
                />
            ) : (
                <Select value={String(valueIndex)} disabled={disabled} ariaLabel={option.label} onChange={(next) => onPick(Number(next))}>
                    {values.map((_, index) => (
                        <option key={index} value={index}>
                            {valueLabel(option, index, yes, no)}
                        </option>
                    ))}
                </Select>
            )}
        </div>
    );
}
