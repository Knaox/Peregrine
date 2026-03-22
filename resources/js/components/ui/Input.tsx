import { forwardRef } from 'react';
import clsx from 'clsx';
import { type InputProps } from '@/components/ui/Input.props';

export const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ label, error, className, id, ...rest }, ref) => {
        const inputId = id ?? label?.toLowerCase().replace(/\s+/g, '-');

        return (
            <div className={clsx('flex flex-col gap-1.5', className)}>
                {label && (
                    <label
                        htmlFor={inputId}
                        className='text-sm font-medium text-[var(--color-text-secondary)]'
                    >
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={clsx(
                        'w-full px-3 py-2 bg-[var(--color-surface-hover)] border rounded-[var(--radius)] text-[var(--color-text-primary)] placeholder-[var(--color-text-muted)] text-sm',
                        'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent',
                        error ? 'border-red-500' : 'border-[var(--color-border)]',
                    )}
                    {...rest}
                />
                {error && (
                    <span className='text-xs text-red-400'>{error}</span>
                )}
            </div>
        );
    },
);

Input.displayName = 'Input';
