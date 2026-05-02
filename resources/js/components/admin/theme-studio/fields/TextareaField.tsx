import { useId } from 'react';
import clsx from 'clsx';

interface TextareaFieldProps {
    label: string;
    value: string;
    onChange: (next: string) => void;
    rows?: number;
    placeholder?: string;
    description?: string;
}

export function TextareaField({
    label,
    value,
    onChange,
    rows = 6,
    placeholder,
    description,
}: TextareaFieldProps) {
    const id = useId();

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={id}
                className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]"
            >
                {label}
            </label>
            <textarea
                id={id}
                value={value}
                rows={rows}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
                spellCheck={false}
                className={clsx(
                    'w-full resize-y rounded-lg px-3 py-2.5 font-mono text-[12px] leading-relaxed',
                    'border border-[var(--color-border)] bg-[var(--color-surface)]/60',
                    'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                    'transition-colors duration-150',
                    'hover:border-[var(--color-border-hover)]',
                    'focus:outline-none focus:border-[var(--color-primary)]',
                    'focus:shadow-[0_0_0_3px_var(--color-primary-glow)]',
                )}
            />
            {description && (
                <p className="text-[11px] text-[var(--color-text-muted)]">{description}</p>
            )}
        </div>
    );
}
