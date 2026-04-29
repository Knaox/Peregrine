import { useId } from 'react';
import clsx from 'clsx';

interface SliderFieldProps {
    label: string;
    value: number;
    min: number;
    max: number;
    step?: number;
    onChange: (next: number) => void;
    suffix?: string;
    description?: string;
}

export function SliderField({
    label,
    value,
    min,
    max,
    step = 1,
    onChange,
    suffix = '',
    description,
}: SliderFieldProps) {
    const id = useId();
    const ratio = max === min ? 0 : (value - min) / (max - min);
    const fillPercent = Math.round(ratio * 100);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-2">
                <label
                    htmlFor={id}
                    className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]"
                >
                    {label}
                </label>
                <span
                    className={clsx(
                        'rounded-md border border-[var(--color-border)]/60 bg-[var(--color-surface-hover)]/60',
                        'px-2 py-0.5 font-mono text-[11px] text-[var(--color-text-primary)]',
                    )}
                >
                    {value}
                    {suffix}
                </span>
            </div>
            <input
                id={id}
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                className="theme-studio-slider w-full cursor-pointer"
                style={{
                    background: `linear-gradient(to right, var(--color-primary) 0%, var(--color-primary) ${fillPercent}%, var(--color-surface-hover) ${fillPercent}%, var(--color-surface-hover) 100%)`,
                }}
            />
            {description && (
                <p className="text-[11px] text-[var(--color-text-muted)]">{description}</p>
            )}
        </div>
    );
}
