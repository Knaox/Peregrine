import clsx from 'clsx';
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';

type ToastVariant = 'success' | 'error' | 'warning' | 'info';

interface ToastOptions {
    /** Toasts sharing a key REPLACE each other (live value updates) instead of stacking. */
    key?: string;
    /** Extra line rendered in a monospace pill (e.g. a generated code). */
    detail?: string;
}

interface ToastItem {
    id: number;
    variant: ToastVariant;
    message: string;
    key?: string;
    detail?: string;
}

interface ToastApi {
    show: (message: string, variant?: ToastVariant, options?: ToastOptions) => void;
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
    const timers = useRef(new Map<number, number>());

    const dismiss = useCallback((id: number) => {
        const timer = timers.current.get(id);
        if (timer !== undefined) {
            window.clearTimeout(timer);
            timers.current.delete(id);
        }
        setItems((prev) => prev.filter((item) => item.id !== id));
    }, []);

    const show = useCallback(
        (message: string, variant: ToastVariant = 'info', options?: ToastOptions) => {
            counter.current += 1;
            const id = counter.current;
            const next: ToastItem = { id, variant, message, key: options?.key, detail: options?.detail };
            setItems((prev) => {
                // Same-key toast: replace in place (its timer is rearmed below)
                // so rapid successive updates read as ONE live notification.
                const existing = options?.key !== undefined ? prev.find((item) => item.key === options.key) : undefined;
                if (existing === undefined) {
                    return [...prev, next];
                }
                const timer = timers.current.get(existing.id);
                if (timer !== undefined) {
                    window.clearTimeout(timer);
                    timers.current.delete(existing.id);
                }

                return prev.map((item) => (item === existing ? next : item));
            });
            timers.current.set(id, window.setTimeout(() => dismiss(id), 4500));
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
                            <span className="ec-toast-body">
                                {item.message}
                                {item.detail !== undefined && <code className="ec-toast-detail">{item.detail}</code>}
                            </span>
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
