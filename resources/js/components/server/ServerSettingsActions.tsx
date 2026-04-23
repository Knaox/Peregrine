import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/Button';
import { renameServer, reinstallServer } from '@/services/serverApi';
import { ApiError } from '@/services/http';
import type { Server } from '@/types/Server';

interface ServerSettingsActionsProps {
    server: Server;
    canRename: boolean;
    canReinstall: boolean;
}

type Dialog = null | 'rename' | 'reinstall';

/**
 * "Settings" card on the server overview page — surfaces rename + reinstall
 * actions behind dedicated confirmation dialogs. Kept separate from
 * ServerInfoCard (read-only) because the dialogs carry state + mutations.
 */
export function ServerSettingsActions({ server, canRename, canReinstall }: ServerSettingsActionsProps) {
    const { t } = useTranslation();
    const queryClient = useQueryClient();

    const [dialog, setDialog] = useState<Dialog>(null);
    const [renameValue, setRenameValue] = useState(server.name);
    const [confirmText, setConfirmText] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    if (!canRename && !canReinstall) {
        return null;
    }

    const close = () => {
        if (submitting) return;
        setDialog(null);
        setError('');
        setRenameValue(server.name);
        setConfirmText('');
    };

    const handleRename = async () => {
        const trimmed = renameValue.trim();
        if (trimmed === '' || trimmed === server.name) {
            close();
            return;
        }
        setSubmitting(true);
        setError('');
        try {
            await renameServer(server.id, trimmed);
            await queryClient.invalidateQueries({ queryKey: ['servers', server.id] });
            await queryClient.invalidateQueries({ queryKey: ['servers'] });
            setDialog(null);
        } catch (err) {
            setError(err instanceof ApiError ? t('servers.settings.rename_error') : t('common.error'));
        } finally {
            setSubmitting(false);
        }
    };

    const handleReinstall = async () => {
        if (confirmText.trim().toLowerCase() !== server.name.trim().toLowerCase()) {
            setError(t('servers.settings.reinstall_name_mismatch'));
            return;
        }
        setSubmitting(true);
        setError('');
        try {
            await reinstallServer(server.id);
            await queryClient.invalidateQueries({ queryKey: ['servers', server.id] });
            setDialog(null);
        } catch (err) {
            setError(err instanceof ApiError ? t('servers.settings.reinstall_error') : t('common.error'));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <div className="glass-card-enhanced rounded-[var(--radius-lg)] p-4 sm:p-5">
                <div className="mb-3 flex items-center gap-2">
                    <svg className="h-4 w-4" style={{ color: 'var(--color-text-muted)' }} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <h3 className="text-sm font-semibold text-[var(--color-text-primary)]">
                        {t('servers.settings.title')}
                    </h3>
                </div>

                <div className="flex flex-wrap gap-2">
                    {canRename && (
                        <button
                            type="button"
                            onClick={() => { setRenameValue(server.name); setDialog('rename'); }}
                            className="inline-flex items-center gap-2 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-1.5 text-sm text-[var(--color-text-primary)] transition-all hover:border-[var(--color-primary)]/50 hover:bg-[var(--color-surface-hover)] cursor-pointer"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                            </svg>
                            {t('servers.settings.rename')}
                        </button>
                    )}
                    {canReinstall && (
                        <button
                            type="button"
                            onClick={() => { setConfirmText(''); setDialog('reinstall'); }}
                            className="inline-flex items-center gap-2 rounded-[var(--radius)] border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-3 py-1.5 text-sm text-[var(--color-danger)] transition-all hover:border-[var(--color-danger)] hover:bg-[var(--color-danger)]/20 cursor-pointer"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {t('servers.settings.reinstall')}
                        </button>
                    )}
                </div>
            </div>

            <AnimatePresence>
                {dialog !== null && (
                    <m.div
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                        className="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
                        style={{ background: 'var(--modal-scrim)' }}
                        onClick={close}
                    >
                        <m.div
                            initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.95 }}
                            transition={{ duration: 0.2 }}
                            className="glass-card-enhanced w-full max-w-md rounded-[var(--radius-lg)] p-5 space-y-4"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {dialog === 'rename' && (
                                <>
                                    <h3 className="text-base font-semibold text-[var(--color-text-primary)]">
                                        {t('servers.settings.rename_title')}
                                    </h3>
                                    <p className="text-xs text-[var(--color-text-muted)]">
                                        {t('servers.settings.rename_help')}
                                    </p>
                                    <input
                                        type="text"
                                        value={renameValue}
                                        onChange={(e) => setRenameValue(e.target.value)}
                                        autoFocus
                                        disabled={submitting}
                                        maxLength={191}
                                        className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none"
                                    />
                                    {error && <p className="text-xs text-[var(--color-danger)]">{error}</p>}
                                    <div className="flex justify-end gap-2">
                                        <Button variant="ghost" onClick={close} disabled={submitting}>
                                            {t('common.cancel')}
                                        </Button>
                                        <Button
                                            onClick={handleRename}
                                            disabled={submitting || renameValue.trim() === '' || renameValue.trim() === server.name}
                                        >
                                            {submitting ? t('common.loading') : t('servers.settings.rename_confirm')}
                                        </Button>
                                    </div>
                                </>
                            )}
                            {dialog === 'reinstall' && (
                                <>
                                    <h3 className="text-base font-semibold text-[var(--color-danger)]">
                                        {t('servers.settings.reinstall_title')}
                                    </h3>
                                    <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-3 py-2 text-xs text-[var(--color-danger)]">
                                        {t('servers.settings.reinstall_warning')}
                                    </div>
                                    <p className="text-xs text-[var(--color-text-muted)]">
                                        {t('servers.settings.reinstall_confirm_help', { name: server.name })}
                                    </p>
                                    <input
                                        type="text"
                                        value={confirmText}
                                        onChange={(e) => setConfirmText(e.target.value)}
                                        autoFocus
                                        disabled={submitting}
                                        placeholder={server.name}
                                        className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-danger)] focus:outline-none"
                                    />
                                    {error && <p className="text-xs text-[var(--color-danger)]">{error}</p>}
                                    <div className="flex justify-end gap-2">
                                        <Button variant="ghost" onClick={close} disabled={submitting}>
                                            {t('common.cancel')}
                                        </Button>
                                        <Button
                                            variant="danger"
                                            onClick={handleReinstall}
                                            disabled={submitting || confirmText.trim() === ''}
                                        >
                                            {submitting ? t('common.loading') : t('servers.settings.reinstall_confirm')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </m.div>
                    </m.div>
                )}
            </AnimatePresence>
        </>
    );
}
