import clsx from 'clsx';
import { type SpinnerProps } from '@/components/ui/Spinner.props';

const sizeClasses: Record<NonNullable<SpinnerProps['size']>, string> = {
    sm: 'w-4 h-4',
    md: 'w-6 h-6',
    lg: 'w-8 h-8',
};

export function Spinner({ size = 'md', className }: SpinnerProps) {
    return (
        <div
            className={clsx(
                'animate-spin rounded-full border-2 border-slate-600 border-t-orange-500',
                sizeClasses[size],
                className,
            )}
            role='status'
            aria-label='Loading'
        />
    );
}
