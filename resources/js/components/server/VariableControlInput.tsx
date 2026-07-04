import clsx from 'clsx';
import type { VariableControl } from '@/services/variableRules';

const inputClasses = (isInvalid: boolean, editable: boolean) =>
    clsx(
        'w-full px-3 py-2 text-sm',
        'bg-[var(--color-surface)] rounded-[var(--radius)]',
        'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
        'border transition-all duration-[var(--transition-fast)]',
        'focus:outline-none focus:ring-2 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
        isInvalid ? 'border-[var(--color-danger)]' : 'border-[var(--color-border)]',
        !editable && 'opacity-50 cursor-not-allowed',
    );

/**
 * The input side of a startup-variable card, dispatched on the control shape
 * derived from the variable's Pelican rules: toggle for booleans (whatever
 * their wire pair: 1/0, true/false, yes/no, on/off), select for `in:` lists,
 * bounded number input for integer/numeric, plain text otherwise.
 */
export function VariableControlInput({
    control,
    value,
    editable,
    isInvalid,
    ariaLabel,
    emptyLabel,
    onChange,
}: {
    control: VariableControl;
    value: string;
    editable: boolean;
    isInvalid: boolean;
    ariaLabel: string;
    emptyLabel: string;
    onChange: (value: string) => void;
}) {
    if (control.kind === 'toggle') {
        const isOn = value === control.onValue;

        return (
            <button
                type="button"
                role="switch"
                aria-checked={isOn}
                aria-label={ariaLabel}
                disabled={!editable}
                onClick={() => editable && onChange(isOn ? control.offValue : control.onValue)}
                className={clsx(
                    'relative inline-flex h-6 w-11 shrink-0 rounded-full',
                    'transition-colors duration-[var(--transition-fast)]',
                    'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
                    isOn ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-border)]',
                    !editable && 'opacity-50 cursor-not-allowed',
                )}
            >
                <span
                    className={clsx(
                        'pointer-events-none inline-block h-5 w-5 rounded-full',
                        'bg-white shadow transform transition-transform duration-[var(--transition-fast)]',
                        'mt-0.5',
                        isOn ? 'translate-x-[22px]' : 'translate-x-0.5',
                    )}
                />
            </button>
        );
    }

    if (control.kind === 'select') {
        // Keep an out-of-list current value selectable so the field never
        // silently jumps to another option (the invalid border flags it).
        const options = control.options.includes(value) || value === ''
            ? control.options
            : [value, ...control.options];

        return (
            <select
                value={value}
                aria-label={ariaLabel}
                disabled={!editable}
                onChange={(event) => onChange(event.target.value)}
                className={inputClasses(isInvalid, editable)}
            >
                {(control.allowEmpty || value === '') && <option value="">{emptyLabel}</option>}
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
        );
    }

    if (control.kind === 'number') {
        return (
            <input
                type="number"
                value={value}
                aria-label={ariaLabel}
                min={control.min ?? undefined}
                max={control.max ?? undefined}
                step={control.integer ? 1 : 'any'}
                readOnly={!editable}
                disabled={!editable}
                onChange={(event) => onChange(event.target.value)}
                className={inputClasses(isInvalid, editable)}
            />
        );
    }

    return (
        <input
            type="text"
            value={value}
            aria-label={ariaLabel}
            maxLength={control.maxLength ?? undefined}
            readOnly={!editable}
            disabled={!editable}
            onChange={(event) => onChange(event.target.value)}
            className={inputClasses(isInvalid, editable)}
        />
    );
}
