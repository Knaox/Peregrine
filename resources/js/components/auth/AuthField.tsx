import clsx from 'clsx';

/**
 * Single labelled auth input with an animated focus ring. Stateless — the
 * parent owns the value and focus state. Extracted from LoginFormCard so the
 * auth components each stay within the 300-line file budget.
 */
export function AuthField({
    id,
    type,
    value,
    label,
    error,
    onChange,
    focused,
    onFocus,
    onBlur,
}: {
    id: string;
    type: string;
    value: string;
    label: string;
    error?: string;
    onChange: (v: string) => void;
    focused: boolean;
    onFocus: () => void;
    onBlur: () => void;
}) {
    return (
        <div>
            <label
                htmlFor={id}
                className={clsx(
                    'mb-1.5 block text-xs font-medium uppercase tracking-wider transition-colors duration-150',
                    focused ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-muted)]',
                )}
            >
                {label}
            </label>
            <input
                id={id}
                type={type}
                value={value}
                required
                autoComplete={type === 'email' ? 'email' : type === 'password' ? 'current-password' : undefined}
                onChange={(e) => onChange(e.target.value)}
                onFocus={onFocus}
                onBlur={onBlur}
                className={clsx(
                    'w-full rounded-[var(--radius)] border px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
                    'bg-[var(--color-background)] transition-all duration-200',
                    'focus:outline-none focus:ring-1',
                    focused
                        ? 'border-[var(--color-primary)] ring-[var(--color-primary-glow)] shadow-[0_0_12px_var(--color-primary-glow)]'
                        : 'border-[var(--color-border)] ring-transparent hover:border-[var(--color-border-hover)]',
                )}
            />
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
