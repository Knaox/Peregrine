import clsx from 'clsx';
import { type ButtonProps } from '@/components/ui/Button.props';
import { Spinner } from '@/components/ui/Spinner';

const variantClasses: Record<NonNullable<ButtonProps['variant']>, string> = {
    primary: 'bg-orange-500 hover:bg-orange-600 text-white',
    danger: 'bg-red-500 hover:bg-red-600 text-white',
    ghost: 'bg-transparent hover:bg-slate-700 text-slate-300',
    secondary: 'bg-slate-700 hover:bg-slate-600 text-white',
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
                'rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
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
