import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m, AnimatePresence } from 'motion/react';
import clsx from 'clsx';
import { Button } from '@/components/ui/Button';
import { useNamespace } from '@/i18n/useNamespace';
import { useSaveCoordinatorStore, selectTotalDirty } from '@/stores/saveCoordinatorStore';

const SaveIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M5 3h11l3 3v13a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M7 3v5h7M7 21v-6h10v6" />
    </svg>
);

const CheckIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
    </svg>
);

type Status = 'idle' | 'saving' | 'saved' | 'error';

/**
 * Single floating save bar shared by every editor on the server screen — the
 * core env-variable panel and any opt-in plugin (e.g. easy-configuration). It
 * reads the {@link useSaveCoordinatorStore} registry: one click flushes every
 * registered source in parallel, each hitting its own endpoint. The bar itself
 * holds no domain knowledge.
 *
 * Mounted once in the server shell; it self-hides when nothing is dirty, so it
 * costs nothing on sub-pages that register no source.
 */
export function GlobalSaveBar() {
    useNamespace(['server-shell']);
    const { t } = useTranslation('server-shell');
    const sources = useSaveCoordinatorStore((s) => s.sources);
    const totalDirty = useSaveCoordinatorStore(selectTotalDirty);
    const [status, setStatus] = useState<Status>('idle');

    const handleSave = useCallback(async () => {
        if (totalDirty === 0) {
            return;
        }
        setStatus('saving');
        const results = await Promise.allSettled(Object.values(sources).map((source) => source.save()));
        const failed = results.some((result) => result.status === 'rejected');
        setStatus(failed ? 'error' : 'saved');
        if (!failed) {
            window.setTimeout(() => setStatus('idle'), 2000);
        }
    }, [sources, totalDirty]);

    // Cmd/Ctrl+S saves everything, matching the easy-configuration shortcut.
    useEffect(() => {
        const onKey = (event: KeyboardEvent): void => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's' && totalDirty > 0) {
                event.preventDefault();
                void handleSave();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [handleSave, totalDirty]);

    // Guard tab close / hard refresh while unsaved (precedent: ThemeStudioPage).
    // In-app navigation is guarded separately at the sidebar (no data router).
    useEffect(() => {
        if (totalDirty === 0) {
            return;
        }
        const onBeforeUnload = (event: BeforeUnloadEvent): void => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', onBeforeUnload);
        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [totalDirty]);

    // A fresh edit after a finished attempt clears the saved/error flash, so the
    // bar reflects the new pending changes instead of a stale outcome. Guarded on
    // `totalDirty > 0` so it never cuts the post-save success flash short.
    useEffect(() => {
        if (totalDirty > 0) {
            setStatus((s) => (s === 'saved' || s === 'error' ? 'idle' : s));
        }
    }, [totalDirty]);

    const visible = totalDirty > 0 || status === 'saving' || status === 'saved';
    const showSaved = status === 'saved' && totalDirty === 0;

    const message =
        status === 'saving'
            ? t('save_bar.saving')
            : status === 'error'
                ? t('save_bar.error')
                : totalDirty > 0
                    ? t('save_bar.unsaved', { count: totalDirty })
                    : t('save_bar.saved');

    return (
        <AnimatePresence>
            {visible && (
                <m.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 20 }}
                    transition={{ duration: 0.2, ease: 'easeOut' }}
                    className={clsx(
                        // Sit above the floating dock when present (it sets
                        // --bottom-safe-area = 5.5rem); fall back to 1.5rem so the
                        // two bottom-centered bars never overlap.
                        'glass-card-enhanced fixed bottom-[calc(var(--bottom-safe-area,1.5rem)+0.75rem)] left-1/2 z-50 -translate-x-1/2',
                        'flex items-center gap-3.5 rounded-full py-2.5 pl-5 pr-2.5',
                        'shadow-[var(--shadow-lg)]',
                    )}
                    role="region"
                    aria-live="polite"
                >
                    <span
                        className={clsx(
                            'text-sm font-medium',
                            status === 'error' ? 'text-[var(--color-danger)]' : 'text-[var(--color-text-primary)]',
                        )}
                    >
                        {message}
                    </span>
                    <Button
                        size="sm"
                        onClick={handleSave}
                        isLoading={status === 'saving'}
                        disabled={status === 'saving' || (totalDirty === 0 && status !== 'error')}
                    >
                        {showSaved ? CheckIcon : SaveIcon}
                        {showSaved ? t('save_bar.saved') : t('save_bar.save')}
                    </Button>
                </m.div>
            )}
        </AnimatePresence>
    );
}
