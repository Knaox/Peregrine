import clsx from 'clsx';
import { type AlertProps } from '@/components/ui/Alert.props';

const variantClasses: Record<AlertProps['variant'], string> = {
    error: clsx(
        'bg-[var(--color-danger)]/5 backdrop-blur-sm',
        'border border-[var(--color-danger)]/20 border-l-2 border-l-[var(--color-danger)]',
        'text-[var(--color-danger)]',
    ),
    success: clsx(
        'bg-[var(--color-success)]/5 backdrop-blur-sm',
        'border border-[var(--color-success)]/20 border-l-2 border-l-[var(--color-success)]',
        'text-[var(--color-success)]',
    ),
    info: clsx(
        'bg-[var(--color-info)]/5 backdrop-blur-sm',
        'border border-[var(--color-info)]/20 border-l-2 border-l-[var(--color-info)]',
        'text-[var(--color-info)]',
    ),
};

function AlertIcon({ variant }: { variant: AlertProps['variant'] }) {
    if (variant === 'error') {
        return (
            <svg
                className='w-5 h-5 flex-shrink-0 text-[var(--color-danger)]'
                fill='none'
                viewBox='0 0 24 24'
                stroke='currentColor'
                strokeWidth={2}
            >
                <path
                    strokeLinecap='round'
                    strokeLinejoin='round'
                    d='M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'
                />
            </svg>
        );
    }

    if (variant === 'success') {
        return (
            <svg
                className='w-5 h-5 flex-shrink-0 text-[var(--color-success)]'
                fill='none'
                viewBox='0 0 24 24'
                stroke='currentColor'
                strokeWidth={2}
            >
                <path
                    strokeLinecap='round'
                    strokeLinejoin='round'
                    d='M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'
                />
            </svg>
        );
    }

    return (
        <svg
            className='w-5 h-5 flex-shrink-0 text-[var(--color-info)]'
            fill='none'
            viewBox='0 0 24 24'
            stroke='currentColor'
            strokeWidth={2}
        >
            <path
                strokeLinecap='round'
                strokeLinejoin='round'
                d='m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z'
            />
        </svg>
    );
}

export function Alert({ variant, children, className }: AlertProps) {
    return (
        <div
            className={clsx(
                'flex items-start gap-3 rounded-[var(--radius)] p-4 text-sm',
                'animate-[fade-in_var(--transition-smooth)]',
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
