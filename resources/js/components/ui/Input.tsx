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
                        className='text-sm font-medium text-slate-300'
                    >
                        {label}
                    </label>
                )}
                <input
                    ref={ref}
                    id={inputId}
                    className={clsx(
                        'w-full px-3 py-2 bg-slate-700 border rounded-lg text-white placeholder-slate-500 text-sm',
                        'focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent',
                        error ? 'border-red-500' : 'border-slate-600',
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
