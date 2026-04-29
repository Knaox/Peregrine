import { useId } from 'react';
import clsx from 'clsx';

interface ToggleFieldProps {
    label: string;
    value: boolean;
    onChange: (next: boolean) => void;
    description?: string;
}

/**
 * Switch-style toggle. The knob is a flex child translated horizontally
 * (rather than `absolute` + translate-x) so it stays inside the track
 * regardless of parent container width.
 */
export function ToggleField({ label, value, onChange, description }: ToggleFieldProps) {
    const id = useId();

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between gap-3">
                <label
                    htmlFor={id}
                    className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)] cursor-pointer"
                >
                    {label}
                </label>
                <button
                    id={id}
                    type="button"
                    role="switch"
                    aria-checked={value}
                    onClick={() => onChange(!value)}
                    className={clsx(
                        'inline-flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 transition-all duration-200',
                        'border cursor-pointer',
                        value
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)] shadow-[0_0_0_3px_var(--color-primary-glow)]'
                            : 'border-[var(--color-border)] bg-[var(--color-surface-hover)]',
                    )}
                >
                    <span
                        className={clsx(
                            'h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200',
                            value ? 'translate-x-5' : 'translate-x-0',
                        )}
                    />
                </button>
            </div>
            {description && (
                <p className="text-[11px] text-[var(--color-text-muted)]">{description}</p>
            )}
        </div>
    );
}
