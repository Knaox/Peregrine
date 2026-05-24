import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { acceptServerEula } from '@/services/serverApi';
import { useNamespace } from '@/i18n/useNamespace';

interface EulaPromptModalProps {
    open: boolean;
    serverId: number;
    onClose: () => void;
    onAccepted: () => void;
}

const ScrollIcon = (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z" />
    </svg>
);

/**
 * "Accept the Minecraft EULA?" prompt. Auto-surfaced by the console when the
 * server logs the EULA boot failure. Yes → writes eula.txt=true and restarts.
 */
export function EulaPromptModal({ open, serverId, onClose, onAccepted }: EulaPromptModalProps) {
    useNamespace(['server-console'] as const);
    const { t } = useTranslation('server-console');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);

    // Clear a stale error when the modal is dismissed and reopened later.
    useEffect(() => {
        if (!open) setError(false);
    }, [open]);

    async function accept() {
        setBusy(true);
        setError(false);
        try {
            await acceptServerEula(serverId);
            onAccepted();
            onClose();
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    }

    return (
        <Modal
            open={open}
            onClose={busy ? () => {} : onClose}
            title={t('fix.eula.title')}
            icon={ScrollIcon}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={busy}>
                        {t('fix.eula.decline')}
                    </Button>
                    <Button variant="primary" onClick={accept} isLoading={busy}>
                        {busy ? t('fix.eula.accepting') : t('fix.eula.accept')}
                    </Button>
                </>
            }
        >
            <p className="text-[var(--color-text-secondary)]">{t('fix.eula.body')}</p>
            <a
                href="https://aka.ms/MinecraftEULA"
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex items-center gap-1.5 text-[var(--color-primary)] hover:underline"
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
                {t('fix.eula.link')}
            </a>
            {error && (
                <div className="mt-3">
                    <Alert variant="error">{t('fix.eula.error')}</Alert>
                </div>
            )}
        </Modal>
    );
}
