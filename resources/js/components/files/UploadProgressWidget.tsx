import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useUploadStore } from '@/stores/uploadStore';
import { useNamespace } from '@/i18n/useNamespace';

/**
 * Floating, app-wide upload indicator. Mounted once at the root (app.tsx) so
 * it stays visible while the user navigates between server tabs during an
 * upload, and it owns the `beforeunload` guard that warns before a hard reload
 * would cancel an in-flight transfer.
 */
export function UploadProgressWidget() {
    useNamespace(["server-files"] as const);
    const { t } = useTranslation();
    const isUploading = useUploadStore((s) => s.isUploading);
    const percent = useUploadStore((s) => s.percent);
    const fileCount = useUploadStore((s) => s.fileCount);
    const directory = useUploadStore((s) => s.directory);
    const error = useUploadStore((s) => s.error);
    const reset = useUploadStore((s) => s.reset);

    useEffect(() => {
        if (!isUploading) return;
        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [isUploading]);

    const visible = isUploading || !!error;

    return (
        <AnimatePresence>
            {visible && (
                <m.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 20 }}
                    transition={{ duration: 0.25, ease: 'easeOut' }}
                    className="fixed bottom-4 right-4 z-50 w-72 rounded-[var(--radius-lg)] glass-card-enhanced p-4 shadow-lg"
                    role="status"
                    aria-live="polite"
                >
                    {error ? (
                        <div className="flex items-start gap-2">
                            <p className="flex-1 text-sm text-[var(--color-danger)]">
                                {t('server-files:files.upload_error')}: {error}
                            </p>
                            <button
                                type="button"
                                onClick={reset}
                                aria-label={t('common:close', 'Close')}
                                className="cursor-pointer text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]"
                            >
                                ✕
                            </button>
                        </div>
                    ) : (
                        <>
                            <div className="mb-2 flex items-center justify-between gap-2">
                                <p className="truncate text-xs font-medium text-[var(--color-text-secondary)]">
                                    {t('server-files:files.uploading')} · {fileCount} → <span style={{ fontFamily: 'var(--font-mono)' }}>{directory}</span>
                                </p>
                                <span className="shrink-0 text-xs font-semibold text-[var(--color-primary)]">{percent}%</span>
                            </div>
                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-surface-hover)]">
                                <m.div
                                    className="h-full rounded-full bg-[var(--color-primary)]"
                                    animate={{ width: `${percent}%` }}
                                    transition={{ duration: 0.2, ease: 'easeOut' }}
                                />
                            </div>
                        </>
                    )}
                </m.div>
            )}
        </AnimatePresence>
    );
}
