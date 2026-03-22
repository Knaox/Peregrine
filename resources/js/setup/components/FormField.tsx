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
            <label className="block text-sm font-medium text-slate-300">
                {label}
                {required && <span className="text-red-400 ml-1">*</span>}
            </label>
            {children}
            {help && !error && (
                <p className="text-xs text-slate-500">{help}</p>
            )}
            {error && (
                <p className="text-xs text-red-400">{error}</p>
            )}
        </div>
    );
}
