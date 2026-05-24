import { useEffect } from 'react';
import clsx from 'clsx';
import { AnimatePresence, m } from 'motion/react';
import { type ModalProps } from '@/components/ui/Modal.props';

const sizeClasses: Record<NonNullable<ModalProps['size']>, string> = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
};

/**
 * Glassy, animated modal built on motion/react + the theme tokens. The project
 * doesn't ship a headless-ui lib, so this is the house dialog: scrim with a
 * light blur, spring-in card, closes on backdrop click + Escape, and locks
 * body scroll while open.
 */
export function Modal({ open, onClose, title, icon, children, footer, size = 'md' }: ModalProps) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', onKey);
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = prevOverflow;
        };
    }, [open, onClose]);

    return (
        <AnimatePresence>
            {open && (
                <m.div
                    className="fixed inset-0 z-[60] flex items-center justify-center p-4"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.2 }}
                >
                    <div
                        className="absolute inset-0"
                        style={{ background: 'var(--modal-scrim)', backdropFilter: 'blur(2px)' }}
                        onClick={onClose}
                        aria-hidden
                    />
                    <m.div
                        role="dialog"
                        aria-modal="true"
                        aria-label={title}
                        initial={{ opacity: 0, scale: 0.95, y: 16 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.96, y: 8 }}
                        transition={{ duration: 0.28, ease: [0.34, 1.56, 0.64, 1] }}
                        className={clsx(
                            'relative w-full glass-card-enhanced rounded-[var(--radius-xl)] shadow-[var(--shadow-lg)]',
                            'flex max-h-[88vh] flex-col overflow-hidden',
                            sizeClasses[size],
                        )}
                    >
                        <div className="flex items-center gap-3 px-5 pt-5 pb-3">
                            {icon && (
                                <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-[var(--radius)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                                    {icon}
                                </span>
                            )}
                            <h2 className="text-base font-semibold text-[var(--color-text-primary)]">{title}</h2>
                            <button
                                type="button"
                                onClick={onClose}
                                aria-label="Close"
                                className="ml-auto cursor-pointer rounded-[var(--radius-sm)] p-1.5 text-[var(--color-text-muted)] transition-colors hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                            >
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div className="overflow-y-auto px-5 pb-2 text-sm text-[var(--color-text-secondary)] terminal-scrollbar">
                            {children}
                        </div>

                        {footer && (
                            <div className="mt-2 flex items-center justify-end gap-2 border-t border-[var(--color-border)] px-5 py-4">
                                {footer}
                            </div>
                        )}
                    </m.div>
                </m.div>
            )}
        </AnimatePresence>
    );
}
