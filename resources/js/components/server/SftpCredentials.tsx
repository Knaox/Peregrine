import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { copyToClipboard } from '@/utils/clipboard';
import { GlassCard } from '@/components/ui/GlassCard';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { useSftpPassword } from '@/hooks/useSftpPassword';
import type { SftpCredentialsProps } from '@/components/server/SftpCredentials.props';

function CopyField({ label, value }: { label: string; value: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const handleCopy = () => {
        void copyToClipboard(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <div className="flex items-center justify-between py-3">
            <div>
                <span className="block text-xs text-[var(--color-text-muted)]">{label}</span>
                <span className="text-sm font-medium text-[var(--color-text-primary)]" style={{ fontFamily: 'var(--font-mono)' }}>{value}</span>
            </div>
            <button type="button" onClick={handleCopy} className="rounded-[var(--radius)] px-2.5 py-1 text-xs font-medium transition-all duration-150 bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
                {copied ? t('servers.sftp.copied') : t('servers.sftp.copy')}
            </button>
        </div>
    );
}

export function SftpCredentials({ server }: SftpCredentialsProps) {
    const { t } = useTranslation();
    const { setSftpPassword, isPending, isSuccess, error, reset } = useSftpPassword();

    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [mismatch, setMismatch] = useState(false);

    const sftp = server.sftp_details;
    const sftpHost = sftp?.ip ?? server.allocation?.ip ?? '—';
    const sftpPort = String(sftp?.port ?? 2022);
    const sftpUsername = sftp?.username ?? '—';
    const quickConnect = `sftp://${sftpUsername}@${sftpHost}:${sftpPort}`;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setMismatch(false);
        reset();
        if (password !== confirm) { setMismatch(true); return; }
        setSftpPassword({ password, password_confirmation: confirm });
        setPassword('');
        setConfirm('');
    };

    const [qcCopied, setQcCopied] = useState(false);
    const handleCopyQuickConnect = () => {
        void copyToClipboard(quickConnect).then(() => {
            setQcCopied(true);
            setTimeout(() => setQcCopied(false), 2000);
        });
    };

    return (
        <GlassCard className="p-6">
            <h2 className="text-lg font-semibold text-[var(--color-text-primary)] mb-4">
                {t('servers.sftp.title')}
            </h2>

            <div className="divide-y divide-[var(--color-border)]">
                <CopyField label={t('servers.sftp.host')} value={sftpHost} />
                <CopyField label={t('servers.sftp.port')} value={sftpPort} />
                <CopyField label={t('servers.sftp.username')} value={sftpUsername} />
            </div>

            {/* Quick-connect clipboard */}
            <div className="mt-4">
                <button
                    type="button"
                    onClick={handleCopyQuickConnect}
                    className="w-full rounded-[var(--radius)] px-4 py-2.5 text-sm font-medium transition-all duration-150"
                    style={{
                        background: qcCopied ? 'rgba(var(--color-success-rgb), 0.15)' : 'rgba(var(--color-primary-rgb), 0.1)',
                        color: qcCopied ? 'var(--color-success)' : 'var(--color-primary)',
                        border: `1px solid ${qcCopied ? 'rgba(var(--color-success-rgb), 0.3)' : 'rgba(var(--color-primary-rgb), 0.2)'}`,
                    }}
                >
                    {qcCopied ? t('servers.sftp.copied') : t('servers.sftp.quick_connect')}
                </button>
            </div>

            <p className="mt-3 text-xs text-[var(--color-text-muted)]">{t('servers.sftp.host_note')}</p>

            <hr className="my-5 border-[var(--color-border)]" />

            <h3 className="text-base font-medium text-[var(--color-text-primary)] mb-3">{t('servers.sftp.set_password')}</h3>

            {isSuccess && <Alert variant="success" className="mb-4">{t('servers.sftp.password_changed')}</Alert>}
            {error && <Alert variant="error" className="mb-4">{t('servers.sftp.password_error')}</Alert>}
            {mismatch && <Alert variant="error" className="mb-4">{t('servers.sftp.password_mismatch')}</Alert>}

            <form onSubmit={handleSubmit} className="space-y-4">
                <Input type="password" label={t('servers.sftp.password_label')} value={password} onChange={(e) => setPassword(e.target.value)} />
                <Input type="password" label={t('servers.sftp.password_confirm')} value={confirm} onChange={(e) => setConfirm(e.target.value)} />
                <Button type="submit" variant="primary" isLoading={isPending} disabled={!password || !confirm}>
                    {t('servers.sftp.set_password')}
                </Button>
            </form>

            <p className="mt-5 text-xs text-[var(--color-text-muted)] leading-relaxed">{t('servers.sftp.instructions')}</p>
        </GlassCard>
    );
}
