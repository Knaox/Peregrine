import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useServer } from '@/hooks/useServer';
import { useServerPermissions } from '@/hooks/useServerPermissions';
import { SftpCredentials } from '@/components/server/SftpCredentials';
import { Spinner } from '@/components/ui/Spinner';

function KeyIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
        </svg>
    );
}

export function ServerSftpPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading } = useServer(serverId);
    const perms = useServerPermissions(server);

    if (isLoading || !server) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    if (!perms.has('file.sftp')) {
        return (
            <m.div
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
                className="flex flex-col items-center justify-center py-20 text-center"
            >
                <div className="rounded-full bg-[var(--color-surface-hover)] p-4 mb-4">
                    <KeyIcon className="h-10 w-10 text-[var(--color-text-muted)]" />
                </div>
                <p className="text-sm font-medium text-[var(--color-text-secondary)]">
                    {t('errors.no_permission')}
                </p>
            </m.div>
        );
    }

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="space-y-6"
        >
            {/* Header */}
            <div className="flex items-center gap-3">
                <div className="rounded-[var(--radius)] bg-[var(--color-primary)]/10 p-2">
                    <KeyIcon className="h-5 w-5 text-[var(--color-primary)]" />
                </div>
                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">
                    {t('servers.sftp.title')}
                </h2>
            </div>

            <SftpCredentials server={server} />
        </m.div>
    );
}
