import clsx from 'clsx';
import { type ButtonProps } from '@/components/ui/Button.props';
import { Spinner } from '@/components/ui/Spinner';

const variantClasses: Record<NonNullable<ButtonProps['variant']>, string> = {
    primary: clsx(
        'bg-[var(--color-primary)] text-white font-semibold',
        'hover:bg-[var(--color-primary-hover)] hover:shadow-[0_0_24px_var(--color-primary-glow)]',
        'hover:scale-[1.03] active:scale-[0.97]',
        'shadow-[0_2px_8px_var(--color-primary-glow)]',
    ),
    danger: clsx(
        'bg-[var(--color-danger)] text-white font-semibold',
        'hover:brightness-110 hover:shadow-[0_0_24px_var(--color-danger-glow)]',
        'hover:scale-[1.03] active:scale-[0.97]',
    ),
    ghost: clsx(
        'bg-transparent text-[var(--color-text-secondary)]',
        'hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
        'active:scale-[0.97]',
    ),
    secondary: clsx(
        'bg-[var(--color-surface)] text-[var(--color-text-primary)]',
        'border border-[var(--color-border-hover)]',
        'hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-text-secondary)]',
        'hover:shadow-[var(--shadow-md)]',
        'hover:scale-[1.02] active:scale-[0.97]',
    ),
};

const sizeClasses: Record<NonNullable<ButtonProps['size']>, string> = {
    sm: 'px-3 py-1.5 text-xs gap-1.5',
    md: 'px-5 py-2.5 text-sm gap-2',
};

export function Button({
    variant = 'primary',
    size = 'md',
    isLoading = false,
    disabled = false,
    type = 'button',
    onClick,
    children,
    className,
}: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || isLoading}
            onClick={onClick}
            className={clsx(
                'rounded-[var(--radius)] font-medium cursor-pointer',
                'transition-all duration-[var(--transition-base)]',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100 disabled:hover:shadow-none',
                'inline-flex items-center justify-center',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                variantClasses[variant],
                sizeClasses[size],
                className,
            )}
        >
            {isLoading && (
                <span className="inline-flex items-center justify-center">
                    <Spinner size="sm" />
                </span>
            )}
            {children}
        </button>
    );
}
