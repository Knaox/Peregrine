interface FormFieldProps {
    label: string;
    required?: boolean;
    error?: string;
    help?: string;
    children: React.ReactNode;
}

export function FormField({ label, required, error, help, children }: FormFieldProps) {
    return (
        <div className="space-y-1">
            <label className="block text-sm font-medium text-[var(--color-text-secondary)]">
                {label}
                {required && <span className="text-[var(--color-danger)] ml-1">*</span>}
            </label>
            {children}
            {help && !error && (
                <p className="text-xs text-[var(--color-text-muted)]">{help}</p>
            )}
            {error && (
                <p className="text-xs text-[var(--color-danger)]">{error}</p>
            )}
        </div>
    );
}
