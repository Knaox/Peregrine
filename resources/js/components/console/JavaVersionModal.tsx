import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import clsx from 'clsx';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { Badge } from '@/components/ui/Badge';
import { fetchServerDockerImages, applyServerDockerImage } from '@/services/serverApi';
import { useNamespace } from '@/i18n/useNamespace';

interface JavaVersionModalProps {
    open: boolean;
    serverId: number;
    requiredJava: number | null;
    onClose: () => void;
    onApplied: () => void;
}

const JavaIcon = (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M8 3s1.5 1.5 0 3-1.5 2.5 0 4m4-7s1.5 1.5 0 3-1.5 2.5 0 4M5 14c0 1.7 3.1 3 7 3s7-1.3 7-3M5 14c0-1.1 1.3-2 3.3-2.6M19 14v3c0 1.7-3.1 3-7 3s-7-1.3-7-3v-3" />
    </svg>
);

/**
 * Java-version picker, auto-surfaced by the console when the server fails to
 * boot on an incompatible Java. Lists the egg's Docker images (+ yolks
 * fallback), pre-selects the recommended one, applies + restarts on confirm.
 */
export function JavaVersionModal({ open, serverId, requiredJava, onClose, onApplied }: JavaVersionModalProps) {
    useNamespace(['server-console'] as const);
    const { t } = useTranslation('server-console');
    const [selected, setSelected] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [applyError, setApplyError] = useState(false);

    const { data, isLoading, isError } = useQuery({
        queryKey: ['server-docker-images', serverId, requiredJava],
        queryFn: () => fetchServerDockerImages(serverId, requiredJava),
        enabled: open,
        staleTime: 60_000,
    });

    // Start fresh each time the modal opens so the pre-selection re-applies.
    useEffect(() => {
        if (!open) {
            setSelected(null);
            setApplyError(false);
        }
    }, [open]);

    // Pre-select the recommended image (else the first non-current one).
    useEffect(() => {
        if (!open || !data || selected !== null) return;
        const pick =
            data.images.find((i) => i.is_recommended) ??
            data.images.find((i) => !i.is_current) ??
            data.images[0];
        setSelected(pick?.image ?? null);
    }, [open, data, selected]);

    async function apply() {
        if (!selected) return;
        setBusy(true);
        setApplyError(false);
        try {
            await applyServerDockerImage(serverId, selected);
            onApplied();
            onClose();
        } catch {
            setApplyError(true);
        } finally {
            setBusy(false);
        }
    }

    const body = requiredJava
        ? t('fix.java.body_with_version', { java: requiredJava })
        : t('fix.java.body');

    return (
        <Modal
            open={open}
            onClose={busy ? () => {} : onClose}
            title={t('fix.java.title')}
            icon={JavaIcon}
            size="lg"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={busy}>
                        {t('fix.java.cancel')}
                    </Button>
                    <Button variant="primary" onClick={apply} isLoading={busy} disabled={!selected || isLoading}>
                        {busy ? t('fix.java.applying') : t('fix.java.apply')}
                    </Button>
                </>
            }
        >
            <p className="text-[var(--color-text-secondary)]">{body}</p>

            <div className="mt-3 flex flex-col gap-2">
                {isLoading && (
                    <div className="flex items-center justify-center gap-2 py-8 text-[var(--color-text-muted)]">
                        <Spinner size="md" /> {t('fix.java.loading')}
                    </div>
                )}
                {isError && <Alert variant="error">{t('fix.java.error')}</Alert>}
                {data && data.images.length === 0 && <Alert variant="info">{t('fix.java.empty')}</Alert>}

                {data?.images.map((img) => {
                    const isSelected = selected === img.image;
                    return (
                        <button
                            key={img.image}
                            type="button"
                            onClick={() => setSelected(img.image)}
                            className={clsx(
                                'flex items-center gap-3 rounded-[var(--radius)] border p-3 text-left transition-all cursor-pointer',
                                isSelected
                                    ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 shadow-[0_0_16px_var(--color-primary-glow)]'
                                    : 'border-[var(--color-border)] hover:border-[var(--color-border-hover)] hover:bg-[var(--color-surface-hover)]',
                            )}
                        >
                            <span
                                className={clsx(
                                    'flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border',
                                    isSelected ? 'border-[var(--color-primary)]' : 'border-[var(--color-text-muted)]',
                                )}
                            >
                                {isSelected && <span className="h-2 w-2 rounded-full bg-[var(--color-primary)]" />}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm font-semibold text-[var(--color-text-primary)]">{img.label}</span>
                                    {img.is_recommended && <Badge color="green">{t('fix.java.recommended')}</Badge>}
                                    {img.is_current && <Badge color="gray">{t('fix.java.current')}</Badge>}
                                </div>
                                <div className="truncate font-mono text-[11px] text-[var(--color-text-muted)]">{img.image}</div>
                            </div>
                        </button>
                    );
                })}
            </div>

            {applyError && (
                <div className="mt-3">
                    <Alert variant="error">{t('fix.java.error')}</Alert>
                </div>
            )}
        </Modal>
    );
}
