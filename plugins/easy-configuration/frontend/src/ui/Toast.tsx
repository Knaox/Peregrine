import clsx from 'clsx';
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';

type ToastVariant = 'success' | 'error' | 'warning' | 'info';

interface ToastItem {
    id: number;
    variant: ToastVariant;
    message: string;
}

interface ToastApi {
    show: (message: string, variant?: ToastVariant) => void;
    success: (message: string) => void;
    error: (message: string) => void;
    warning: (message: string) => void;
}

const ToastContext = createContext<ToastApi | null>(null);

export function useToast(): ToastApi {
    const ctx = useContext(ToastContext);
    if (ctx === null) {
        throw new Error('useToast must be used within a ToastProvider');
    }

    return ctx;
}

/** Wraps a plugin surface so any descendant can raise non-blocking toasts. */
export function ToastProvider({ children }: { children: ReactNode }) {
    const [items, setItems] = useState<ToastItem[]>([]);
    const counter = useRef(0);

    const dismiss = useCallback((id: number) => {
        setItems((prev) => prev.filter((item) => item.id !== id));
    }, []);

    const show = useCallback(
        (message: string, variant: ToastVariant = 'info') => {
            counter.current += 1;
            const id = counter.current;
            setItems((prev) => [...prev, { id, variant, message }]);
            window.setTimeout(() => dismiss(id), 4500);
        },
        [dismiss],
    );

    const api = useMemo<ToastApi>(
        () => ({
            show,
            success: (message) => show(message, 'success'),
            error: (message) => show(message, 'error'),
            warning: (message) => show(message, 'warning'),
        }),
        [show],
    );

    return (
        <ToastContext.Provider value={api}>
            {children}
            {items.length > 0 && (
                <div className="ec-toast-host">
                    {items.map((item) => (
                        <div
                            key={item.id}
                            className={clsx('ec-toast', `ec-toast-${item.variant}`)}
                            role="status"
                            onClick={() => dismiss(item.id)}
                        >
                            <span className="ec-toast-icon">
                                <ToastIcon variant={item.variant} />
                            </span>
                            <span>{item.message}</span>
                        </div>
                    ))}
                </div>
            )}
        </ToastContext.Provider>
    );
}

function ToastIcon({ variant }: { variant: ToastVariant }) {
    const size = 16;
    if (variant === 'success') {
        return <CheckCircle2 size={size} />;
    }
    if (variant === 'error') {
        return <XCircle size={size} />;
    }
    if (variant === 'warning') {
        return <AlertTriangle size={size} />;
    }

    return <Info size={size} />;
}
