import { useId, useRef, useState, useEffect } from 'react';
import clsx from 'clsx';

interface ColorFieldProps {
    label: string;
    value: string;
    onChange: (next: string) => void;
    description?: string;
}

const HEX_RE = /^#[0-9a-fA-F]{6}$/;

/**
 * Color picker field with the native `<input type="color">` hidden behind
 * a styled swatch button. The swatch shows the current colour and acts as
 * the click target — clicking it opens the OS picker. The hex input lets
 * the admin paste exact values.
 */
export function ColorField({ label, value, onChange, description }: ColorFieldProps) {
    const id = useId();
    const nativeRef = useRef<HTMLInputElement>(null);
    const [text, setText] = useState(value);

    useEffect(() => {
        setText(value);
    }, [value]);

    const commit = (next: string): void => {
        const trimmed = next.trim();
        if (HEX_RE.test(trimmed)) {
            onChange(trimmed.toLowerCase());
        }
    };

    return (
        <div className="flex flex-col gap-2">
            <label
                htmlFor={id}
                className="text-[11px] font-medium tracking-wide text-[var(--color-text-secondary)]"
            >
                {label}
            </label>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    aria-label={`${label} — pick colour`}
                    onClick={() => nativeRef.current?.click()}
                    className={clsx(
                        'relative h-9 w-9 shrink-0 rounded-lg border border-[var(--color-border)]',
                        'cursor-pointer transition-all duration-150',
                        'hover:scale-[1.04] hover:border-[var(--color-border-hover)]',
                        'hover:shadow-[0_0_0_3px_var(--surface-overlay-soft)]',
                    )}
                    style={{ background: value }}
                >
                    <input
                        ref={nativeRef}
                        id={id}
                        type="color"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                        aria-hidden
                    />
                </button>
                <input
                    type="text"
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onBlur={(e) => commit(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            (e.target as HTMLInputElement).blur();
                        }
                    }}
                    spellCheck={false}
                    className={clsx(
                        'h-9 flex-1 min-w-0 rounded-lg px-3 font-mono text-[12px]',
                        'border border-[var(--color-border)] bg-[var(--color-surface)]/60',
                        'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                        'transition-colors duration-150',
                        'hover:border-[var(--color-border-hover)]',
                        'focus:outline-none focus:border-[var(--color-primary)]',
                        'focus:shadow-[0_0_0_3px_var(--color-primary-glow)]',
                    )}
                    placeholder="#000000"
                />
            </div>
            {description && (
                <p className="text-[11px] text-[var(--color-text-muted)]">{description}</p>
            )}
        </div>
    );
}
