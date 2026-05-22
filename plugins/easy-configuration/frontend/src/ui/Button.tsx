import clsx from 'clsx';
import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: 'sm' | 'md';
    loading?: boolean;
    children?: ReactNode;
}

export function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    disabled,
    type = 'button',
    className,
    children,
    ...rest
}: ButtonProps) {
    return (
        <button
            {...rest}
            type={type}
            disabled={disabled || loading}
            className={clsx('ec-btn', `ec-btn-${variant}`, size === 'sm' && 'ec-btn-sm', className)}
        >
            {loading && <span className="ec-spinner" aria-hidden />}
            {children}
        </button>
    );
}

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    label: string;
    children: ReactNode;
}

export function IconButton({ label, type = 'button', className, children, ...rest }: IconButtonProps) {
    return (
        <button {...rest} type={type} aria-label={label} title={label} className={clsx('ec-btn', 'ec-btn-icon', className)}>
            {children}
        </button>
    );
}
