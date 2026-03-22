import clsx from 'clsx';
import { type ButtonProps } from '@/components/ui/Button.props';
import { Spinner } from '@/components/ui/Spinner';

const variantClasses: Record<NonNullable<ButtonProps['variant']>, string> = {
    primary: clsx(
        'bg-[var(--color-primary)] text-[var(--color-text-primary)] font-semibold',
        'hover:bg-[var(--color-primary-hover)] hover:shadow-[var(--shadow-glow)]',
        'hover:scale-[1.02] active:scale-[0.98]',
    ),
    danger: clsx(
        'bg-[var(--color-danger)] text-[var(--color-text-primary)] font-semibold',
        'hover:brightness-110 hover:shadow-[0_0_20px_var(--color-danger-glow)]',
        'hover:scale-[1.02] active:scale-[0.98]',
    ),
    ghost: clsx(
        'bg-transparent text-[var(--color-text-secondary)]',
        'hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
        'active:scale-[0.98]',
    ),
    secondary: clsx(
        'bg-[var(--color-surface)] text-[var(--color-text-primary)]',
        'border border-[var(--color-border)]',
        'hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)]',
        'hover:scale-[1.02] active:scale-[0.98]',
    ),
};

const sizeClasses: Record<NonNullable<ButtonProps['size']>, string> = {
    sm: 'px-3 py-1.5 text-xs gap-1.5',
    md: 'px-4 py-2 text-sm gap-2',
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
                'rounded-[var(--radius)] font-medium',
                'transition-all duration-[var(--transition-base)]',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:scale-100',
                'inline-flex items-center justify-center',
                variantClasses[variant],
                sizeClasses[size],
                className,
            )}
        >
            {isLoading && (
                <span className='inline-flex items-center justify-center'>
                    <Spinner size='sm' />
                </span>
            )}
            {children}
        </button>
    );
}
