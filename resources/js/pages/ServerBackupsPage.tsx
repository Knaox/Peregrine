import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useBackups } from '@/hooks/useBackups';
import { formatBytes, formatDate } from '@/utils/format';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';

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
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('servers.backups.title')}</h2>
                <Button variant="primary" size="sm" onClick={() => setShowCreate(!showCreate)}>{t('servers.backups.create')}</Button>
            </div>

            {showCreate && (
                <div style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 20 }}>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.backups.name')}</label>
                            <input value={bkName} onChange={(e) => setBkName(e.target.value)} placeholder={t('servers.backups.name_placeholder')} className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.backups.ignored_files')}</label>
                            <textarea value={bkIgnored} onChange={(e) => setBkIgnored(e.target.value)} rows={3} placeholder="*.log" className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none" />
                            <p className="mt-1 text-xs text-[var(--color-text-muted)]">{t('servers.backups.ignored_help')}</p>
                        </div>
                        <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('servers.backups.create')}</Button>
                    </div>
                </div>
            )}

            {(!backups || backups.length === 0) ? (
                <p className="py-8 text-center text-[var(--color-text-muted)]">{t('servers.backups.no_backups')}</p>
            ) : (
                <div className="space-y-3">
                    {backups.map((bk) => (
                        <div key={bk.uuid} style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 16 }}>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-[var(--color-text-primary)]">{bk.name}</p>
                                        {bk.is_locked && <span className="rounded-[var(--radius-sm)] bg-[var(--color-warning)]/15 px-1.5 py-0.5 text-[10px] font-medium text-[var(--color-warning)]">{t('servers.backups.locked')}</span>}
                                        <span className={`rounded-[var(--radius-sm)] px-1.5 py-0.5 text-[10px] font-medium ${bk.is_successful ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]' : bk.completed_at ? 'bg-[var(--color-danger)]/15 text-[var(--color-danger)]' : 'bg-[var(--color-warning)]/15 text-[var(--color-warning)]'}`}>
                                            {bk.is_successful ? t('servers.backups.status_completed') : bk.completed_at ? t('servers.backups.status_failed') : t('servers.backups.status_creating')}
                                        </span>
                                    </div>
                                    <p className="text-xs text-[var(--color-text-muted)]">{formatBytes(bk.bytes)} &middot; {formatDate(bk.created_at)}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {bk.is_successful && <Button variant="secondary" size="sm" onClick={() => void download(bk.uuid)}>{t('servers.backups.download')}</Button>}
                                    {bk.is_successful && (
                                        <Button variant="secondary" size="sm" isLoading={restore.isPending} onClick={() => {
                                            if (window.confirm(t('servers.backups.restore_confirm'))) restore.mutate({ backupId: bk.uuid });
                                        }}>{t('servers.backups.restore')}</Button>
                                    )}
                                    <Button variant="ghost" size="sm" isLoading={lock.isPending} onClick={() => lock.mutate(bk.uuid)}>
                                        {bk.is_locked ? t('servers.backups.unlock') : t('servers.backups.lock')}
                                    </Button>
                                    {!bk.is_locked && (
                                        <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={() => {
                                            if (window.confirm(t('servers.backups.confirm_delete', { name: bk.name }))) remove.mutate(bk.uuid);
                                        }}>{t('servers.backups.delete')}</Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </m.div>
    );
}
