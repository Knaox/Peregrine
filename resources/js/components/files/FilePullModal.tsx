import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { Button } from '@/components/ui/Button';

interface FilePullModalProps {
    open: boolean;
    directory: string;
    isPending: boolean;
    onSubmit: (url: string, filename: string | undefined) => void;
    onClose: () => void;
}

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

export function FilePullModal({ open, directory, isPending, onSubmit, onClose }: FilePullModalProps) {
    const { t } = useTranslation();
    const [url, setUrl] = useState('');
    const [filename, setFilename] = useState('');

    const handleSubmit = () => {
        const trimmed = url.trim();
        if (!trimmed) return;
        onSubmit(trimmed, filename.trim() || undefined);
    };

    const handleClose = () => {
        setUrl(''); setFilename('');
        onClose();
    };

    return (
        <AnimatePresence>
            {open && (
                <m.div
                    initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                    className="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm p-4"
                    style={{ background: 'var(--modal-scrim)' }}
                    onClick={handleClose}
                >
                    <m.div
                        initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.95 }}
                        transition={{ duration: 0.2 }}
                        className="glass-card-enhanced w-full max-w-md max-h-[90vh] overflow-y-auto rounded-[var(--radius-lg)] p-5 space-y-4"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h3 className="text-base font-semibold text-[var(--color-text-primary)]">{t('servers.files.pull')}</h3>
                        <p className="text-xs text-[var(--color-text-muted)]">
                            {t('servers.files.pull_target_dir', { defaultValue: 'Target directory: {{dir}}', dir: directory })}
                        </p>

                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.files.pull_url')}</label>
                            <input
                                type="url" value={url}
                                onChange={(e) => setUrl(e.target.value)}
                                placeholder="https://example.com/file.zip"
                                className={INPUT_CLS}
                                autoFocus
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.files.pull_filename')}</label>
                            <input
                                type="text" value={filename}
                                onChange={(e) => setFilename(e.target.value)}
                                placeholder="file.zip"
                                className={INPUT_CLS}
                            />
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-2">
                            <Button variant="ghost" size="sm" onClick={handleClose}>{t('common.cancel')}</Button>
                            <Button
                                variant="primary" size="sm"
                                isLoading={isPending}
                                disabled={!url.trim() || isPending}
                                onClick={handleSubmit}
                            >
                                {t('servers.files.pull_start')}
                            </Button>
                        </div>
                    </m.div>
                </m.div>
            )}
        </AnimatePresence>
    );
}
