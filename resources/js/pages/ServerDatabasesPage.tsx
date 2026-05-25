import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useDatabases } from '@/hooks/useDatabases';
import { useServer } from '@/hooks/useServer';
import { useServerPermissions } from '@/hooks/useServerPermissions';
import type { Database } from '@/types/Database';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import { withServerConflictGate } from '@/components/server/withServerConflictGate';
import { ResourceQuota } from '@/components/server/ResourceQuota';
import { DatabaseRow } from '@/components/server/DatabaseRow';
import { usePluginStore } from '@/plugins/pluginStore';
import { useNamespace } from '@/i18n/useNamespace';

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

function ServerDatabasesPageImpl() {
    const { t } = useTranslation();
    useNamespace(['server-databases', 'server-overview', 'common'] as const);
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: databases, isLoading, create, rotate, remove } = useDatabases(serverId);
    const { data: server } = useServer(serverId);
    const perms = useServerPermissions(server);
    const canCreate = perms.has('database.create');
    const canUpdate = perms.has('database.update');
    const canDelete = perms.has('database.delete');
    const canViewPassword = perms.has('database.view_password');
    const rowActions = usePluginStore((s) => s.databaseRowActionComponents);

    const usedDatabases = databases?.length ?? 0;
    const databaseLimit = server?.feature_limits?.databases;
    const atDatabaseLimit = databaseLimit !== undefined && usedDatabases >= databaseLimit;

    const [showCreate, setShowCreate] = useState(false);
    const [dbName, setDbName] = useState('');
    const [remoteMode, setRemoteMode] = useState<'anywhere' | 'specific'>('anywhere');
    const [remoteHost, setRemoteHost] = useState('');
    // Passwords surfaced by create/rotate, keyed by database id, passed to the
    // matching row so it can auto-reveal without a credentials round-trip.
    const [seeded, setSeeded] = useState<Record<string, string>>({});
    const [feedback, setFeedback] = useState<{ type: 'success' | 'error' | 'warning'; message: string } | null>(null);

    const notify = (type: 'success' | 'error' | 'warning', message: string) => {
        setFeedback({ type, message });
        window.setTimeout(() => setFeedback(null), 4000);
    };

    const handleCreate = () => {
        // Validate client-side: keep the form open and warn instead of firing a
        // request that would only come back as a generic error.
        if (!dbName.trim()) {
            notify('warning', t('server-databases:databases.name_required'));
            return;
        }
        const remote = remoteMode === 'specific' ? remoteHost.trim() : '%';
        if (remoteMode === 'specific' && remote === '') {
            notify('warning', t('server-databases:databases.remote_required'));
            return;
        }
        create.mutate({ database: dbName.trim(), remote }, {
            onSuccess: (created) => {
                setShowCreate(false);
                setDbName('');
                setRemoteMode('anywhere');
                setRemoteHost('');
                if (created.password) setSeeded((s) => ({ ...s, [created.id]: created.password as string }));
                notify('success', t('server-databases:databases.created'));
            },
            onError: () => notify('error', t('server-databases:databases.action_failed')),
        });
    };

    const handleRotate = (db: Database) => {
        rotate.mutate(db.id, {
            onSuccess: (updated) => {
                if (updated.password) setSeeded((s) => ({ ...s, [db.id]: updated.password as string }));
                notify('success', t('server-databases:databases.rotated'));
            },
            onError: () => notify('error', t('server-databases:databases.action_failed')),
        });
    };

    const handleDelete = (db: Database) => {
        if (!window.confirm(t('server-databases:databases.confirm_delete', { name: db.name }))) return;
        remove.mutate(db.id, {
            onSuccess: () => notify('success', t('server-databases:databases.deleted')),
            onError: () => notify('error', t('server-databases:databases.action_failed')),
        });
    };

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6">
            {feedback && (
                <div className={`rounded-[var(--radius-lg)] border px-4 py-3 text-sm ${
                    feedback.type === 'success'
                        ? 'border-[var(--color-success)]/30 bg-[var(--color-success)]/10 text-[var(--color-success)]'
                        : feedback.type === 'warning'
                            ? 'border-[var(--color-warning)]/30 bg-[var(--color-warning)]/10 text-[var(--color-warning)]'
                            : 'border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 text-[var(--color-danger)]'
                }`}>
                    {feedback.message}
                </div>
            )}

            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-[var(--radius-lg)] bg-[var(--color-primary)]/10">
                        <DatabaseIcon className="h-5 w-5 text-[var(--color-primary)]" />
                    </div>
                    <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('server-databases:databases.title')}</h2>
                </div>
                {canCreate && (
                    <Button variant="primary" size="sm" disabled={atDatabaseLimit} onClick={() => setShowCreate(!showCreate)}>
                        {t('server-databases:databases.create')}
                    </Button>
                )}
            </div>

            {/* Quota — used / limit + remaining (or limit reached) */}
            {databaseLimit !== undefined && (
                <ResourceQuota label={t('server-databases:databases.title')} used={usedDatabases} limit={databaseLimit} />
            )}

            {/* Create form */}
            {showCreate && (
                <m.div
                    initial={{ opacity: 0, y: -8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.25 }}
                    className="glass-card-enhanced rounded-[var(--radius-lg)] p-5"
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('server-databases:databases.name')}</label>
                            <input value={dbName} onChange={(e) => setDbName(e.target.value)} placeholder="s1_mydb" className={INPUT_CLS} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('server-databases:databases.remote')}</label>
                            <select
                                value={remoteMode}
                                onChange={(e) => setRemoteMode(e.target.value as 'anywhere' | 'specific')}
                                className={INPUT_CLS}
                            >
                                <option value="anywhere">{t('server-databases:databases.remote_anywhere')}</option>
                                <option value="specific">{t('server-databases:databases.remote_specific')}</option>
                            </select>
                            {remoteMode === 'specific' && (
                                <input
                                    value={remoteHost}
                                    onChange={(e) => setRemoteHost(e.target.value)}
                                    placeholder="192.168.1.50"
                                    className={`${INPUT_CLS} mt-2`}
                                />
                            )}
                            <p className="mt-1 text-xs text-[var(--color-text-muted)]">{t('server-databases:databases.remote_help')}</p>
                        </div>
                        <div className="flex items-end gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setShowCreate(false)}>{t('common:cancel')}</Button>
                            <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('server-databases:databases.create')}</Button>
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
                    <p className="text-sm text-[var(--color-text-muted)]">{t('server-databases:databases.no_databases')}</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {databases.map((db, index) => (
                        <DatabaseRow
                            key={db.id}
                            db={db}
                            serverId={serverId}
                            index={index}
                            canUpdate={canUpdate}
                            canDelete={canDelete}
                            canViewPassword={canViewPassword}
                            seededPassword={seeded[db.id]}
                            rotatePending={rotate.isPending}
                            removePending={remove.isPending}
                            onRotate={handleRotate}
                            onDelete={handleDelete}
                            onNotify={notify}
                            rowActions={rowActions}
                        />
                    ))}
                </div>
            )}
        </m.div>
    );
}

// See ServerFilesPage for the rationale: gate by conflict state so a
// suspended / provisioning server can't render its databases page.
export const ServerDatabasesPage = withServerConflictGate(ServerDatabasesPageImpl);
