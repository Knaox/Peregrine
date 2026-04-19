import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { copyToClipboard } from '@/utils/clipboard';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { useSftpPassword } from '@/hooks/useSftpPassword';
import type { SftpCredentialsProps } from '@/components/server/SftpCredentials.props';

function ClipboardIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
        </svg>
    );
}

function CheckIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
        </svg>
    );
}

function KeyIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
        </svg>
    );
}

function LockIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
    );
}

function LinkIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
        </svg>
    );
}

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
        <div className="flex items-center justify-between gap-2 rounded-[var(--radius)] px-2 sm:px-3 py-2 sm:py-3 transition-colors hover:bg-[var(--color-surface-hover)]/50">
            <div className="min-w-0">
                <span className="block text-xs text-[var(--color-text-muted)]">{label}</span>
                <span
                    className="text-xs sm:text-sm font-medium text-[var(--color-text-primary)] break-all"
                    style={{ fontFamily: 'var(--font-mono)' }}
                >
                    {value}
                </span>
            </div>
            <button
                type="button"
                onClick={handleCopy}
                className="cursor-pointer ml-3 flex items-center gap-1.5 rounded-[var(--radius)] px-2.5 py-1.5 text-xs font-medium transition-all duration-200 bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-border)]"
            >
                {copied ? (
                    <>
                        <CheckIcon className="h-3.5 w-3.5 text-[var(--color-success)]" />
                        <span className="text-[var(--color-success)]">{t('servers.sftp.copied')}</span>
                    </>
                ) : (
                    <>
                        <ClipboardIcon className="h-3.5 w-3.5" />
                        {t('servers.sftp.copy')}
                    </>
                )}
            </button>
        </div>
    );
}

function CredentialsSection({ sftpHost, sftpPort, sftpUsername, quickConnect, qcCopied, onCopyQuickConnect }: {
    sftpHost: string;
    sftpPort: string;
    sftpUsername: string;
    quickConnect: string;
    qcCopied: boolean;
    onCopyQuickConnect: () => void;
}) {
    const { t } = useTranslation();

    return (
        <m.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: 0.05 }}
            className="glass-card-enhanced rounded-[var(--radius-lg)] p-4 sm:p-5"
        >
            <div className="mb-3 sm:mb-4 flex items-center gap-2.5">
                <div className="rounded-[var(--radius)] bg-[var(--color-primary)]/10 p-1.5">
                    <KeyIcon className="h-4 w-4 text-[var(--color-primary)]" />
                </div>
                <h3 className="text-sm sm:text-base font-semibold text-[var(--color-text-primary)]">
                    {t('servers.sftp.credentials_title')}
                </h3>
            </div>

            <div className="space-y-1 divide-y divide-[var(--color-border)]/50">
                <CopyField label={t('servers.sftp.host')} value={sftpHost} />
                <CopyField label={t('servers.sftp.port')} value={sftpPort} />
                <CopyField label={t('servers.sftp.username')} value={sftpUsername} />
            </div>

            {/* Quick connect */}
            <div className="mt-4">
                <button
                    type="button"
                    onClick={onCopyQuickConnect}
                    className="cursor-pointer w-full flex items-center justify-center gap-2 rounded-[var(--radius)] px-4 py-3 text-sm font-semibold transition-all duration-200 hover:scale-[1.01] active:scale-[0.99]"
                    style={{
                        background: qcCopied
                            ? 'rgba(var(--color-success-rgb), 0.12)'
                            : 'rgba(var(--color-primary-rgb), 0.1)',
                        color: qcCopied ? 'var(--color-success)' : 'var(--color-primary)',
                        border: `1px solid ${qcCopied
                            ? 'rgba(var(--color-success-rgb), 0.3)'
                            : 'rgba(var(--color-primary-rgb), 0.25)'}`,
                    }}
                >
                    {qcCopied ? (
                        <CheckIcon className="h-4 w-4" />
                    ) : (
                        <LinkIcon className="h-4 w-4" />
                    )}
                    {qcCopied ? t('servers.sftp.copied') : t('servers.sftp.quick_connect')}
                </button>
            </div>

            <p className="mt-3 text-xs text-[var(--color-text-muted)] leading-relaxed">
                {t('servers.sftp.host_note')}
            </p>
        </m.div>
    );
}

function PasswordSection({ password, confirm, mismatch, isPending, isSuccess, error, onPasswordChange, onConfirmChange, onSubmit }: {
    password: string;
    confirm: string;
    mismatch: boolean;
    isPending: boolean;
    isSuccess: boolean;
    error: Error | null;
    onPasswordChange: (v: string) => void;
    onConfirmChange: (v: string) => void;
    onSubmit: (e: FormEvent) => void;
}) {
    const { t } = useTranslation();

    return (
        <m.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: 0.1 }}
            className="glass-card-enhanced rounded-[var(--radius-lg)] p-4 sm:p-5"
        >
            <div className="mb-3 sm:mb-4 flex items-center gap-2.5">
                <div className="rounded-[var(--radius)] bg-[var(--color-warning)]/10 p-1.5">
                    <LockIcon className="h-4 w-4 text-[var(--color-warning)]" />
                </div>
                <h3 className="text-sm sm:text-base font-semibold text-[var(--color-text-primary)]">
                    {t('servers.sftp.set_password')}
                </h3>
            </div>

            {isSuccess && (
                <Alert variant="success" className="mb-4">
                    {t('servers.sftp.password_changed')}
                </Alert>
            )}
            {error && (
                <Alert variant="error" className="mb-4">
                    {t('servers.sftp.password_error')}
                </Alert>
            )}
            {mismatch && (
                <Alert variant="error" className="mb-4">
                    {t('servers.sftp.password_mismatch')}
                </Alert>
            )}

            <form onSubmit={onSubmit} className="space-y-4">
                <Input
                    type="password"
                    label={t('servers.sftp.password_label')}
                    value={password}
                    onChange={(e) => onPasswordChange(e.target.value)}
                />
                <Input
                    type="password"
                    label={t('servers.sftp.password_confirm')}
                    value={confirm}
                    onChange={(e) => onConfirmChange(e.target.value)}
                />
                <Button
                    type="submit"
                    variant="primary"
                    isLoading={isPending}
                    disabled={!password || !confirm}
                >
                    {t('servers.sftp.set_password')}
                </Button>
            </form>

            <p className="mt-5 text-xs text-[var(--color-text-muted)] leading-relaxed">
                {t('servers.sftp.instructions')}
            </p>
        </m.div>
    );
}

export function SftpCredentials({ server }: SftpCredentialsProps) {
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
        if (password !== confirm) {
            setMismatch(true);
            return;
        }
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
        <div className="grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-2">
            <CredentialsSection
                sftpHost={sftpHost}
                sftpPort={sftpPort}
                sftpUsername={sftpUsername}
                quickConnect={quickConnect}
                qcCopied={qcCopied}
                onCopyQuickConnect={handleCopyQuickConnect}
            />
            <PasswordSection
                password={password}
                confirm={confirm}
                mismatch={mismatch}
                isPending={isPending}
                isSuccess={isSuccess}
                error={error}
                onPasswordChange={setPassword}
                onConfirmChange={setConfirm}
                onSubmit={handleSubmit}
            />
        </div>
    );
}
