import clsx from 'clsx';
import { type AlertProps } from '@/components/ui/Alert.props';

const variantClasses: Record<AlertProps['variant'], string> = {
    error: 'bg-red-500/10 border-red-500/30 text-red-300',
    success: 'bg-green-500/10 border-green-500/30 text-green-300',
    info: 'bg-blue-500/10 border-blue-500/30 text-blue-300',
};

function AlertIcon({ variant }: { variant: AlertProps['variant'] }) {
    if (variant === 'error') {
        return (
            <svg
                className='w-5 h-5 flex-shrink-0'
                fill='none'
                viewBox='0 0 24 24'
                stroke='currentColor'
                strokeWidth={2}
            >
                <path
                    strokeLinecap='round'
                    strokeLinejoin='round'
                    d='M6 18L18 6M6 6l12 12'
                />
            </svg>
        );
    }

    if (variant === 'success') {
        return (
            <svg
                className='w-5 h-5 flex-shrink-0'
                fill='none'
                viewBox='0 0 24 24'
                stroke='currentColor'
                strokeWidth={2}
            >
                <path
                    strokeLinecap='round'
                    strokeLinejoin='round'
                    d='M5 13l4 4L19 7'
                />
            </svg>
        );
    }

    return (
        <svg
            className='w-5 h-5 flex-shrink-0'
            fill='none'
            viewBox='0 0 24 24'
            stroke='currentColor'
            strokeWidth={2}
        >
            <path
                strokeLinecap='round'
                strokeLinejoin='round'
                d='M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
            />
        </svg>
    );
}

export function Alert({ variant, children, className }: AlertProps) {
    return (
        <div
            className={clsx(
                'flex items-start gap-3 rounded-lg border p-4 text-sm',
                variantClasses[variant],
                className,
            )}
            role='alert'
        >
            <AlertIcon variant={variant} />
            <div>{children}</div>
        </div>
    );
}
