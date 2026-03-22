import clsx from 'clsx';
import { type ButtonProps } from '@/components/ui/Button.props';
import { Spinner } from '@/components/ui/Spinner';

const variantClasses: Record<NonNullable<ButtonProps['variant']>, string> = {
    primary: 'bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-[var(--color-text-primary)]',
    danger: 'bg-[var(--color-danger)] hover:bg-[var(--color-danger)] text-[var(--color-text-primary)]',
    ghost: 'bg-transparent hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
    secondary: 'bg-[var(--color-surface-hover)] hover:bg-[var(--color-surface-hover)] text-[var(--color-text-primary)]',
};

const sizeClasses: Record<NonNullable<ButtonProps['size']>, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
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
                'rounded-[var(--radius)] font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
                'inline-flex items-center justify-center gap-2',
                variantClasses[variant],
                sizeClasses[size],
                className,
            )}
        >
            {isLoading && <Spinner size='sm' />}
            {children}
        </button>
    );
}
