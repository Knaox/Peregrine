import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useBackups } from '@/hooks/useBackups';
import { formatBytes, formatDate } from '@/utils/format';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

function ArchiveIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 8v13H3V8" />
            <path d="M1 3h22v5H1z" />
            <path d="M10 12h4" />
        </svg>
    );
}

function LockIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
    );
}

function UnlockIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 9.9-1" />
        </svg>
    );
}

function StatusBadge({ isSuccessful, completedAt }: { isSuccessful: boolean; completedAt: string | null }) {
    const { t } = useTranslation();

    if (isSuccessful) {
        return (
            <span className="inline-flex items-center gap-1 rounded-[var(--radius-sm)] bg-[var(--color-success)]/15 px-2 py-0.5 text-[10px] font-medium text-[var(--color-success)]">
                <span className="h-1.5 w-1.5 rounded-full bg-[var(--color-success)]" />
                {t('servers.backups.status_completed')}
            </span>
        );
    }

    if (completedAt) {
        return (
            <span className="inline-flex items-center gap-1 rounded-[var(--radius-sm)] bg-[var(--color-danger)]/15 px-2 py-0.5 text-[10px] font-medium text-[var(--color-danger)]">
                <span className="h-1.5 w-1.5 rounded-full bg-[var(--color-danger)]" />
                {t('servers.backups.status_failed')}
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 rounded-[var(--radius-sm)] bg-[var(--color-warning)]/15 px-2 py-0.5 text-[10px] font-medium text-[var(--color-warning)]">
            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-[var(--color-warning)]" />
            {t('servers.backups.status_creating')}
        </span>
    );
}

export function ServerBackupsPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: backups, isLoading, create, remove, lock, restore, download } = useBackups(serverId);

    const [showCreate, setShowCreate] = useState(false);
    const [bkName, setBkName] = useState('');
    const [bkIgnored, setBkIgnored] = useState('');

    const handleCreate = () => {
        create.mutate({ name: bkName || undefined, ignored: bkIgnored || undefined }, {
            onSuccess: () => { setShowCreate(false); setBkName(''); setBkIgnored(''); },
        });
    };

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-[var(--radius-lg)] bg-[var(--color-primary)]/10">
                        <ArchiveIcon className="h-5 w-5 text-[var(--color-primary)]" />
                    </div>
                    <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('servers.backups.title')}</h2>
                </div>
                <Button variant="primary" size="sm" onClick={() => setShowCreate(!showCreate)}>
                    {t('servers.backups.create')}
                </Button>
            </div>

            {/* Create form */}
            {showCreate && (
                <m.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.25 }}
                    className="glass-card-enhanced rounded-[var(--radius-lg)] p-5"
                >
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.backups.name')}</label>
                            <input value={bkName} onChange={(e) => setBkName(e.target.value)} placeholder={t('servers.backups.name_placeholder')} className={INPUT_CLS} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.backups.ignored_files')}</label>
                            <textarea value={bkIgnored} onChange={(e) => setBkIgnored(e.target.value)} rows={3} placeholder="*.log" className={INPUT_CLS} />
                            <p className="mt-1 text-xs text-[var(--color-text-muted)]">{t('servers.backups.ignored_help')}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setShowCreate(false)}>{t('common.cancel')}</Button>
                            <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('servers.backups.create')}</Button>
                        </div>
                    </div>
                </m.div>
            )}

            {/* Backup list */}
            {(!backups || backups.length === 0) ? (
                <div className="flex flex-col items-center justify-center py-16">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-surface)]">
                        <ArchiveIcon className="h-8 w-8 text-[var(--color-text-muted)]" />
                    </div>
                    <p className="text-sm text-[var(--color-text-muted)]">{t('servers.backups.no_backups')}</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {backups.map((bk, index) => (
                        <m.div
                            key={bk.uuid}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.3, delay: index * 0.05 }}
                            className="glass-card-enhanced hover-lift rounded-[var(--radius-lg)] p-4"
                        >
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0 space-y-1.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm font-semibold text-[var(--color-text-primary)]">{bk.name}</p>
                                        <StatusBadge isSuccessful={bk.is_successful} completedAt={bk.completed_at} />
                                        {bk.is_locked && (
                                            <span className="inline-flex items-center gap-1 rounded-[var(--radius-sm)] bg-[var(--color-warning)]/15 px-2 py-0.5 text-[10px] font-medium text-[var(--color-warning)]">
                                                <LockIcon className="h-3 w-3" />
                                                {t('servers.backups.locked')}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 text-xs text-[var(--color-text-muted)]">
                                        <span className="rounded-[var(--radius-sm)] bg-[var(--color-surface-hover)] px-1.5 py-0.5 font-medium">
                                            {formatBytes(bk.bytes)}
                                        </span>
                                        <span>&middot;</span>
                                        <span>{formatDate(bk.created_at)}</span>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {bk.is_successful && (
                                        <Button variant="secondary" size="sm" onClick={() => void download(bk.uuid)}>
                                            {t('servers.backups.download')}
                                        </Button>
                                    )}
                                    {bk.is_successful && (
                                        <Button variant="secondary" size="sm" isLoading={restore.isPending} onClick={() => {
                                            if (window.confirm(t('servers.backups.restore_confirm'))) restore.mutate({ backupId: bk.uuid });
                                        }}>
                                            {t('servers.backups.restore')}
                                        </Button>
                                    )}
                                    <Button variant="ghost" size="sm" isLoading={lock.isPending} onClick={() => lock.mutate(bk.uuid)}>
                                        {bk.is_locked
                                            ? <><UnlockIcon className="h-3.5 w-3.5" /> {t('servers.backups.unlock')}</>
                                            : <><LockIcon className="h-3.5 w-3.5" /> {t('servers.backups.lock')}</>
                                        }
                                    </Button>
                                    {!bk.is_locked && (
                                        <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={() => {
                                            if (window.confirm(t('servers.backups.confirm_delete', { name: bk.name }))) remove.mutate(bk.uuid);
                                        }}>
                                            {t('servers.backups.delete')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </m.div>
                    ))}
                </div>
            )}
        </m.div>
    );
}
