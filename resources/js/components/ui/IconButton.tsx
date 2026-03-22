import clsx from 'clsx';
import { type IconButtonProps } from '@/components/ui/IconButton.props';
import { Spinner } from '@/components/ui/Spinner';

const variantClasses: Record<NonNullable<IconButtonProps['variant']>, string> = {
    ghost: 'bg-transparent hover:bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)]',
    danger: 'bg-transparent hover:bg-red-500/20 text-red-400',
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
                'inline-flex items-center justify-center rounded-[var(--radius)] transition-colors',
                'disabled:opacity-50 disabled:cursor-not-allowed',
                variantClasses[variant],
                sizeClasses[size],
                className,
            )}
        >
            {isLoading ? <Spinner size='sm' /> : icon}
        </button>
    );
}
