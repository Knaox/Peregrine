import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useUploadStore, type ActivityKind } from '@/stores/uploadStore';
import { useNamespace } from '@/i18n/useNamespace';

const BUSY_LABEL_KEY: Record<Exclude<ActivityKind, 'upload'>, string> = {
    compress: 'compressing',
    decompress: 'decompressing',
    pull: 'pulling',
};

/**
 * Floating, app-wide file-activity indicator. Mounted once at the root
 * (app.tsx) so it stays visible while the user navigates between server tabs
 * during an upload, and it owns the `beforeunload` guard (upload only).
 *
 * Shows a real % bar for uploads; an indeterminate animated bar for
 * compress/decompress/pull (Pelican exposes no progress for those).
 */
export function UploadProgressWidget() {
    useNamespace(["server-files"] as const);
    const { t } = useTranslation();
    const active = useUploadStore((s) => s.active);
    const kind = useUploadStore((s) => s.kind);
    const percent = useUploadStore((s) => s.percent);
    const fileCount = useUploadStore((s) => s.fileCount);
    const directory = useUploadStore((s) => s.directory);
    const error = useUploadStore((s) => s.error);
    const reset = useUploadStore((s) => s.reset);

    // Only uploads stream bytes from the browser, so only they are lost on a
    // hard reload. compress/decompress/pull run server-side — no guard needed.
    useEffect(() => {
        if (!active || kind !== 'upload') return;
        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [active, kind]);

    const visible = active || !!error;
    const determinate = kind === 'upload';
    const label = kind === 'upload'
        ? `${t('server-files:files.uploading')} · ${fileCount} → ${directory}`
        : t(`server-files:files.${BUSY_LABEL_KEY[kind]}`);

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
                                <p className="truncate text-xs font-medium text-[var(--color-text-secondary)]">{label}</p>
                                {determinate && <span className="shrink-0 text-xs font-semibold text-[var(--color-primary)]">{percent}%</span>}
                            </div>
                            <div className="relative h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-surface-hover)]">
                                {determinate ? (
                                    <m.div
                                        className="h-full rounded-full bg-[var(--color-primary)]"
                                        animate={{ width: `${percent}%` }}
                                        transition={{ duration: 0.2, ease: 'easeOut' }}
                                    />
                                ) : (
                                    <m.div
                                        className="absolute inset-y-0 w-1/3 rounded-full bg-[var(--color-primary)]"
                                        animate={{ x: ['-110%', '330%'] }}
                                        transition={{ duration: 1.1, repeat: Infinity, ease: 'easeInOut' }}
                                    />
                                )}
                            </div>
                        </>
                    )}
                </m.div>
            )}
        </AnimatePresence>
    );
}
