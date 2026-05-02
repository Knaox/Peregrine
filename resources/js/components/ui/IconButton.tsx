import clsx from 'clsx';
import { type IconButtonProps } from '@/components/ui/IconButton.props';
import { Spinner } from '@/components/ui/Spinner';

const variantClasses: Record<NonNullable<IconButtonProps['variant']>, string> = {
    ghost: clsx(
        'bg-transparent text-[var(--color-text-secondary)]',
        'hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
        'hover:shadow-[0_0_8px_var(--color-primary-glow)]',
    ),
    danger: clsx(
        'bg-transparent text-[var(--color-text-secondary)]',
        'hover:text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10',
        'hover:shadow-[0_0_8px_var(--color-danger-glow)]',
    ),
};

const sizeClasses: Record<NonNullable<IconButtonProps['size']>, string> = {
    sm: 'w-8 h-8',
    md: 'w-10 h-10',
};

export function IconButton({
    icon,
    onClick,
    disabled = false,
    isLoading = false,
    variant = 'ghost',
    size = 'md',
    title,
    className,
}: IconButtonProps) {
    return (
        <button
            type='button'
            disabled={disabled || isLoading}
            onClick={onClick}
            title={title}
            className={clsx(
                'scale-on-hover',
                'inline-flex items-center justify-center rounded-[var(--radius)]',
                'transition-all duration-[var(--transition-fast)]',
                'disabled:opacity-40 disabled:cursor-not-allowed',
                variantClasses[variant],
                sizeClasses[size],
                className,
            )}
        >
            {isLoading ? <Spinner size='sm' /> : icon}
        </button>
    );
}
