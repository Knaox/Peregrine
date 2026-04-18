import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useDatabases } from '@/hooks/useDatabases';
import { getDatabaseHostString } from '@/types/Database';
import { copyToClipboard } from '@/utils/clipboard';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

function DatabaseIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" />
            <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" />
        </svg>
    );
}

function CopyIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
        </svg>
    );
}

function CheckIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
        </svg>
    );
}

export function ServerDatabasesPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: databases, isLoading, create, rotate, remove } = useDatabases(serverId);

    const [showCreate, setShowCreate] = useState(false);
    const [dbName, setDbName] = useState('');
    const [dbRemote, setDbRemote] = useState('%');
    const [visiblePasswords, setVisiblePasswords] = useState<Set<string>>(new Set());
    const [copiedId, setCopiedId] = useState<string | null>(null);

    const handleCreate = () => {
        create.mutate({ database: dbName, remote: dbRemote }, {
            onSuccess: () => { setShowCreate(false); setDbName(''); setDbRemote('%'); },
        });
    };

    const togglePassword = (dbId: string) => {
        setVisiblePasswords((prev) => {
            const next = new Set(prev);
            next.has(dbId) ? next.delete(dbId) : next.add(dbId);
            return next;
        });
    };

    const handleCopyPassword = async (dbId: string, password: string) => {
        await copyToClipboard(password);
        setCopiedId(dbId);
        setTimeout(() => setCopiedId(null), 2000);
    };

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-[var(--radius-lg)] bg-[var(--color-primary)]/10">
                        <DatabaseIcon className="h-5 w-5 text-[var(--color-primary)]" />
                    </div>
                    <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('servers.databases.title')}</h2>
                </div>
                <Button variant="primary" size="sm" onClick={() => setShowCreate(!showCreate)}>
                    {t('servers.databases.create')}
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
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.databases.name')}</label>
                            <input value={dbName} onChange={(e) => setDbName(e.target.value)} placeholder="s1_mydb" className={INPUT_CLS} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.databases.remote')}</label>
                            <input value={dbRemote} onChange={(e) => setDbRemote(e.target.value)} placeholder="%" className={INPUT_CLS} />
                            <p className="mt-1 text-xs text-[var(--color-text-muted)]">{t('servers.databases.remote_help')}</p>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setShowCreate(false)}>{t('common.cancel')}</Button>
                            <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('servers.databases.create')}</Button>
                        </div>
                    </div>
                </m.div>
            )}

            {/* Database list */}
            {(!databases || databases.length === 0) ? (
                <div className="flex flex-col items-center justify-center py-16">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-surface)]">
                        <DatabaseIcon className="h-8 w-8 text-[var(--color-text-muted)]" />
                    </div>
                    <p className="text-sm text-[var(--color-text-muted)]">{t('servers.databases.no_databases')}</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {databases.map((db, index) => (
                        <m.div
                            key={db.id}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.3, delay: index * 0.05 }}
                            className="glass-card-enhanced hover-lift rounded-[var(--radius-lg)] p-4"
                        >
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0 space-y-1.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm font-semibold text-[var(--color-text-primary)]">{db.name}</p>
                                        <span className="rounded-[var(--radius-sm)] bg-[var(--color-accent)]/10 px-1.5 py-0.5 text-[10px] font-medium text-[var(--color-accent)]">
                                            {db.connections_from}
                                        </span>
                                    </div>
                                    <p className="text-xs text-[var(--color-text-muted)]">
                                        {getDatabaseHostString(db)} &middot; {db.username}
                                    </p>
                                    {db.password && (
                                        <div className="flex items-center gap-2">
                                            <code className="text-xs text-[var(--color-text-secondary)]" style={{ fontFamily: 'var(--font-mono)' }}>
                                                {visiblePasswords.has(db.id) ? db.password : '••••••••'}
                                            </code>
                                            <button
                                                type="button"
                                                onClick={() => togglePassword(db.id)}
                                                className="cursor-pointer text-xs text-[var(--color-primary)] hover:underline"
                                            >
                                                {visiblePasswords.has(db.id) ? t('servers.databases.hide_password') : t('servers.databases.show_password')}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => void handleCopyPassword(db.id, db.password as string)}
                                                className="cursor-pointer rounded-[var(--radius-sm)] p-1 text-[var(--color-text-muted)] transition-colors hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                                title={t('servers.list.copy_ip')}
                                            >
                                                {copiedId === db.id
                                                    ? <CheckIcon className="h-3.5 w-3.5 text-[var(--color-success)]" />
                                                    : <CopyIcon className="h-3.5 w-3.5" />
                                                }
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button variant="secondary" size="sm" isLoading={rotate.isPending} onClick={() => rotate.mutate(db.id)}>
                                        {t('servers.databases.rotate_password')}
                                    </Button>
                                    <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={() => {
                                        if (window.confirm(t('servers.databases.confirm_delete', { name: db.name }))) {
                                            remove.mutate(db.id);
                                        }
                                    }}>
                                        {t('servers.databases.delete')}
                                    </Button>
                                </div>
                            </div>
                        </m.div>
                    ))}
                </div>
            )}
        </m.div>
    );
}
