import { useId } from 'react';
import clsx from 'clsx';

interface SelectFieldProps<T extends string> {
    label: string;
    value: T;
    options: ReadonlyArray<{ value: T; label: string }>;
    onChange: (next: T) => void;
    description?: string;
}

export function SelectField<T extends string>({
    label,
    value,
    options,
    onChange,
    description,
}: SelectFieldProps<T>) {
    const id = useId();

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={id}
                className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]"
            >
                {label}
            </label>
            <div className="relative">
                <select
                    id={id}
                    value={value}
                    onChange={(e) => onChange(e.target.value as T)}
                    className={clsx(
                        'h-9 w-full cursor-pointer appearance-none rounded-lg pl-3 pr-9 text-[12px]',
                        'border border-[var(--color-border)] bg-[var(--color-surface)]/60',
                        'text-[var(--color-text-primary)]',
                        'transition-colors duration-150',
                        'hover:border-[var(--color-border-hover)]',
                        'focus:outline-none focus:border-[var(--color-primary)]',
                        'focus:shadow-[0_0_0_3px_var(--color-primary-glow)]',
                    )}
                >
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
                <svg
                    className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden
                >
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </div>
            {description && (
                <p className="text-[11px] text-[var(--color-text-muted)]">{description}</p>
            )}
        </div>
    );
}
