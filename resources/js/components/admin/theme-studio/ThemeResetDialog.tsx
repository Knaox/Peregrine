import { useEffect, useState } from 'react';
import { AnimatePresence, m } from 'motion/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';

interface ThemeResetDialogProps {
    /** Whether the dialog is visible. */
    open: boolean;
    /** True while the reset request is in flight. */
    isResetting: boolean;
    /** Length of the admin's current `theme_custom_css` for the warning. */
    customCssLength: number;
    /** True if any non-default upload (login background or carousel) is referenced. */
    hasCustomUploads: boolean;
    /** Closes the dialog without resetting. */
    onCancel: () => void;
    /** Fires the actual reset call. */
    onConfirm: () => void;
}

const REQUIRED_TYPED = 'RESET';

/**
 * Destructive-action confirmation dialog for the Theme Studio "Reset to
 * defaults" button. Replaces the previous `window.confirm` with:
 *
 *  - Visual warnings tailored to the current draft (volume of custom_css,
 *    presence of uploads) so the admin sees what they're about to lose
 *  - Typed confirmation : the destructive button stays disabled until the
 *    admin types the literal string "RESET"
 *  - Loading state during the round-trip
 *
 * Reuses the modal stack pattern from ServerSettingsActions (motion +
 * `glass-card-enhanced` + scrim) — see `ServerSettingsActions.tsx:124-200`.
 */
export function ThemeResetDialog({
    open,
    isResetting,
    customCssLength,
    hasCustomUploads,
    onCancel,
    onConfirm,
}: ThemeResetDialogProps) {
    const { t } = useTranslation();
    const [typed, setTyped] = useState('');

    // Re-arm the confirmation each time the dialog re-opens so a previous
    // "RESET" typed string doesn't auto-confirm on the next attempt.
    useEffect(() => {
        if (open) setTyped('');
    }, [open]);

    const ready = typed === REQUIRED_TYPED && !isResetting;

    return (
        <AnimatePresence>
            {open && (
                <m.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.18 }}
                    className="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
                    style={{ background: 'var(--modal-scrim)' }}
                    onClick={() => {
                        if (!isResetting) onCancel();
                    }}
                    role="presentation"
                >
                    <m.div
                        initial={{ opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.95 }}
                        transition={{ duration: 0.2 }}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="theme-reset-dialog-title"
                        className="glass-card-enhanced w-full max-w-md max-h-[90vh] overflow-y-auto rounded-[var(--radius-lg)] p-5 space-y-4"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h3
                            id="theme-reset-dialog-title"
                            className="text-base font-semibold text-[var(--color-danger)]"
                        >
                            {t('theme_studio.reset_dialog.title', 'Reset the entire theme?')}
                        </h3>

                        <p className="text-xs text-[var(--color-text-muted)]">
                            {t(
                                'theme_studio.reset_dialog.body',
                                'Every theme setting goes back to factory defaults. This cannot be undone — export a backup first if you want to keep this version (php artisan theme:export).',
                            )}
                        </p>

                        {customCssLength > 100 && (
                            <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-3 py-2 text-xs text-[var(--color-danger)]">
                                {t(
                                    'theme_studio.reset_dialog.warning_custom_css',
                                    'You have {{length}} characters of custom CSS that will be discarded.',
                                    { length: customCssLength },
                                )}
                            </div>
                        )}

                        {hasCustomUploads && (
                            <div className="rounded-[var(--radius)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/10 px-3 py-2 text-xs text-[var(--color-warning)]">
                                {t(
                                    'theme_studio.reset_dialog.warning_uploads',
                                    'Login background uploads will be unlinked. Files stay on disk until the weekly cleanup runs.',
                                )}
                            </div>
                        )}

                        <div>
                            <label
                                htmlFor="theme-reset-confirm"
                                className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5"
                            >
                                {t(
                                    'theme_studio.reset_dialog.type_to_confirm',
                                    'Type RESET to confirm',
                                )}
                            </label>
                            <input
                                id="theme-reset-confirm"
                                type="text"
                                value={typed}
                                onChange={(e) => setTyped(e.target.value)}
                                autoFocus
                                disabled={isResetting}
                                spellCheck={false}
                                autoComplete="off"
                                className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 font-mono text-sm text-[var(--color-text-primary)] focus:border-[var(--color-danger)] focus:outline-none"
                                placeholder={REQUIRED_TYPED}
                            />
                        </div>

                        <div className="flex justify-end gap-2 pt-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={onCancel}
                                disabled={isResetting}
                            >
                                {t('common.cancel', 'Cancel')}
                            </Button>
                            <Button
                                variant="danger"
                                size="sm"
                                onClick={onConfirm}
                                disabled={!ready}
                                isLoading={isResetting}
                            >
                                {t('theme_studio.reset_dialog.confirm_button', 'Reset to defaults')}
                            </Button>
                        </div>
                    </m.div>
                </m.div>
            )}
        </AnimatePresence>
    );
}
