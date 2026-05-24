import clsx from 'clsx';
import type { InputHTMLAttributes, ReactNode, TextareaHTMLAttributes } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    invalid?: boolean;
}

export function Input({ invalid, className, ...rest }: InputProps) {
    return <input {...rest} className={clsx('ec-input', invalid && 'ec-input-invalid', className)} />;
}

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    invalid?: boolean;
}

export function Textarea({ invalid, className, ...rest }: TextareaProps) {
    return <textarea {...rest} className={clsx('ec-textarea', invalid && 'ec-input-invalid', className)} />;
}

interface SelectProps {
    value: string;
    onChange: (value: string) => void;
    children: ReactNode;
    className?: string;
    disabled?: boolean;
    invalid?: boolean;
    ariaLabel?: string;
}

export function Select({ value, onChange, children, className, disabled, invalid, ariaLabel }: SelectProps) {
    return (
        <select
            className={clsx('ec-select', invalid && 'ec-input-invalid', className)}
            value={value}
            disabled={disabled}
            aria-label={ariaLabel}
            onChange={(event) => onChange(event.target.value)}
        >
            {children}
        </select>
    );
}

interface ToggleProps {
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
    label?: string;
}

export function Toggle({ checked, onChange, disabled, label }: ToggleProps) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            className={clsx('ec-toggle', checked && 'ec-toggle-on')}
            onClick={() => onChange(!checked)}
        >
            <span className="ec-toggle-knob" />
        </button>
    );
}
