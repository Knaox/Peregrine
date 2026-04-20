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
                        className="text-sm font-medium text-[var(--color-text-secondary)] transition-colors duration-[var(--transition-fast)]"
                    >
                        {label}
                    </label>
                )}
                <div className="relative">
                    <input
                        ref={ref}
                        id={inputId}
                        className={clsx(
                            'w-full px-4 py-2.5 text-sm',
                            'bg-[var(--color-surface)] rounded-[var(--radius)]',
                            'text-[var(--color-text-primary)] placeholder:text-[var(--color-text-secondary)]',
                            'transition-all duration-[var(--transition-base)]',
                            'focus:outline-none focus:ring-2',
                            'focus:shadow-[0_0_0_1px_var(--color-primary),0_0_16px_var(--color-primary-glow)]',
                            error
                                ? 'border border-[var(--color-danger)] focus:border-[var(--color-danger)] focus:ring-[var(--color-danger-glow)]'
                                : 'border border-[var(--color-border)] focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
                            'hover:border-[var(--color-border-hover)]',
                        )}
                        {...rest}
                    />
                </div>
                {error && (
                    <span className="text-xs text-[var(--color-danger)] animate-[slide-up-fade_200ms_ease-out]">
                        {error}
                    </span>
                )}
            </div>
        );
    },
);

Input.displayName = 'Input';
