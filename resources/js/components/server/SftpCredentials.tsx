import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { IconButton } from '@/components/ui/IconButton';
import { useSftpPassword } from '@/hooks/useSftpPassword';
import type { SftpCredentialsProps } from '@/components/server/SftpCredentials.props';

function ClipboardIcon() {
    return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round"
                d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
        </svg>
    );
}

interface CredentialRowProps {
    label: string;
    value: string;
}

function CredentialRow({ label, value }: CredentialRowProps) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        void navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="flex items-center justify-between gap-3 py-2">
            <span className="text-sm text-slate-400 whitespace-nowrap">{label}</span>
            <div className="flex items-center gap-2">
                <span className="bg-slate-700 px-3 py-2 rounded-lg font-mono text-sm text-white">
                    {value}
                </span>
                <IconButton
                    icon={<ClipboardIcon />}
                    size="sm"
                    title={copied ? t('servers.sftp.copied') : t('servers.sftp.copy')}
                    onClick={handleCopy}
                />
            </div>
        </div>
    );
}

export function SftpCredentials({ server, userEmail }: SftpCredentialsProps) {
    const { t } = useTranslation();
    const { setSftpPassword, isPending, isSuccess, error, reset } = useSftpPassword();

    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [mismatch, setMismatch] = useState(false);

    const sftpUsername = `${userEmail}.${server.pelican_server_id ?? server.id}`;

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

    return (
        <Card className="p-6">
            <h2 className="text-lg font-semibold text-white mb-4">
                {t('servers.sftp.title')}
            </h2>

            <div className="divide-y divide-slate-700">
                <CredentialRow label={t('servers.sftp.port')} value="2022" />
                <CredentialRow label={t('servers.sftp.username')} value={sftpUsername} />
            </div>

            <p className="mt-3 text-xs text-slate-500">
                {t('servers.sftp.host_note')}
            </p>

            <hr className="my-5 border-slate-700" />

            <h3 className="text-base font-medium text-white mb-3">
                {t('servers.sftp.set_password')}
            </h3>

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

            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    type="password"
                    label={t('servers.sftp.password_label')}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />
                <Input
                    type="password"
                    label={t('servers.sftp.password_confirm')}
                    value={confirm}
                    onChange={(e) => setConfirm(e.target.value)}
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

            <p className={clsx('mt-5 text-xs text-slate-500 leading-relaxed')}>
                {t('servers.sftp.instructions')}
            </p>
        </Card>
    );
}
