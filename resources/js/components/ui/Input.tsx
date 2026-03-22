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
                        'w-full px-3 py-2 text-sm',
                        'bg-[var(--color-surface)] rounded-[var(--radius)]',
                        'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]',
                        'transition-all duration-[var(--transition-fast)]',
                        'focus:outline-none focus:ring-2',
                        error
                            ? 'border border-[var(--color-danger)] focus:border-[var(--color-danger)] focus:ring-[var(--color-danger-glow)]'
                            : 'border border-[var(--color-border)] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
                    )}
                    {...rest}
                />
                {error && (
                    <span className='text-xs text-[var(--color-danger)]'>
                        {error}
                    </span>
                )}
            </div>
        );
    },
);

Input.displayName = 'Input';
