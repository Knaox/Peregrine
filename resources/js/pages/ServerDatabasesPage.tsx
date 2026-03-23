import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useDatabases } from '@/hooks/useDatabases';
import { getDatabaseHostString } from '@/types/Database';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';

export function ServerDatabasesPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: databases, isLoading, create, rotate, remove } = useDatabases(serverId);

    const [showCreate, setShowCreate] = useState(false);
    const [dbName, setDbName] = useState('');
    const [dbRemote, setDbRemote] = useState('%');
    const [visiblePasswords, setVisiblePasswords] = useState<Set<string>>(new Set());

    const handleCreate = () => {
        create.mutate({ database: dbName, remote: dbRemote }, {
            onSuccess: () => { setShowCreate(false); setDbName(''); setDbRemote('%'); },
        });
    };

    const togglePassword = (id: string) => {
        setVisiblePasswords((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('servers.databases.title')}</h2>
                <Button variant="primary" size="sm" onClick={() => setShowCreate(!showCreate)}>{t('servers.databases.create')}</Button>
            </div>

            {showCreate && (
                <div style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 20 }}>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.databases.name')}</label>
                            <input value={dbName} onChange={(e) => setDbName(e.target.value)} placeholder="s1_mydb" className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.databases.remote')}</label>
                            <input value={dbRemote} onChange={(e) => setDbRemote(e.target.value)} placeholder="%" className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none" />
                            <p className="mt-1 text-xs text-[var(--color-text-muted)]">{t('servers.databases.remote_help')}</p>
                        </div>
                        <div className="flex items-end">
                            <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('servers.databases.create')}</Button>
                        </div>
                    </div>
                </div>
            )}

            {(!databases || databases.length === 0) ? (
                <p className="py-8 text-center text-[var(--color-text-muted)]">{t('servers.databases.no_databases')}</p>
            ) : (
                <div className="space-y-3">
                    {databases.map((db) => (
                        <div key={db.id} style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 16 }}>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold text-[var(--color-text-primary)]">{db.name}</p>
                                    <p className="text-xs text-[var(--color-text-muted)]">{getDatabaseHostString(db)} &middot; {db.username}</p>
                                    {db.password && (
                                        <div className="flex items-center gap-2">
                                            <code className="text-xs text-[var(--color-text-secondary)]" style={{ fontFamily: 'var(--font-mono)' }}>
                                                {visiblePasswords.has(db.id) ? db.password : '••••••••'}
                                            </code>
                                            <button type="button" onClick={() => togglePassword(db.id)} className="text-xs text-[var(--color-primary)] hover:underline">
                                                {visiblePasswords.has(db.id) ? t('servers.databases.hide_password') : t('servers.databases.show_password')}
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
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
                        </div>
                    ))}
                </div>
            )}
        </m.div>
    );
}
