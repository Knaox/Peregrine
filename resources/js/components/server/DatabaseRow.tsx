import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import type { Database } from '@/types/Database';
import { getDatabaseHostString } from '@/types/Database';
import { fetchDatabaseCredentials } from '@/services/databaseApi';
import { copyToClipboard } from '@/utils/clipboard';
import { Button } from '@/components/ui/Button';

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

export interface DatabaseRowProps {
    db: Database;
    serverId: number;
    index: number;
    canUpdate: boolean;
    canDelete: boolean;
    canViewPassword: boolean;
    /** Plaintext password handed back by a create/rotate response, auto-revealed. */
    seededPassword?: string;
    rotatePending: boolean;
    removePending: boolean;
    onRotate: (db: Database) => void;
    onDelete: (db: Database) => void;
    onNotify: (type: 'success' | 'error', message: string) => void;
    rowActions: Record<string, React.ComponentType<{ serverId: number; database: Database }>>;
}

/**
 * One database card in the server "Databases" tab. Owns its password-reveal
 * state: the list never carries the plaintext password, so "Show password"
 * lazily fetches it from the credentials endpoint, while create/rotate seed it
 * directly via `seededPassword`. Plugin-contributed row actions (e.g. the
 * phpMyAdmin button) render after Rotate/Delete through the `rowActions` slot.
 */
export function DatabaseRow({
    db, serverId, index, canUpdate, canDelete, canViewPassword,
    seededPassword, rotatePending, removePending, onRotate, onDelete, onNotify, rowActions,
}: DatabaseRowProps) {
    const { t } = useTranslation();
    const [visible, setVisible] = useState(false);
    const [revealed, setRevealed] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    // Create/rotate hand us the fresh password — reveal it without a round-trip.
    useEffect(() => {
        if (seededPassword !== undefined) {
            setRevealed(seededPassword);
            setVisible(true);
        }
    }, [seededPassword]);

    const togglePassword = async () => {
        if (visible) {
            setVisible(false);
            return;
        }
        if (revealed !== null) {
            setVisible(true);
            return;
        }
        setLoading(true);
        try {
            const creds = await fetchDatabaseCredentials(serverId, db.id);
            setRevealed(creds.password ?? '');
            setVisible(true);
        } catch {
            onNotify('error', t('server-databases:databases.password_load_failed'));
        } finally {
            setLoading(false);
        }
    };

    const copyPassword = async () => {
        if (!revealed) return;
        await copyToClipboard(revealed);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <m.div
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
                    {canViewPassword && (
                        <div className="flex items-center gap-2">
                            <code className="text-xs text-[var(--color-text-secondary)]" style={{ fontFamily: 'var(--font-mono)' }}>
                                {visible ? (revealed ?? '••••••••') : '••••••••'}
                            </code>
                            <button
                                type="button"
                                disabled={loading}
                                onClick={() => void togglePassword()}
                                className="cursor-pointer text-xs text-[var(--color-primary)] hover:underline disabled:opacity-50"
                            >
                                {loading
                                    ? t('common:loading')
                                    : visible
                                        ? t('server-databases:databases.hide_password')
                                        : t('server-databases:databases.show_password')}
                            </button>
                            {visible && revealed && (
                                <button
                                    type="button"
                                    onClick={() => void copyPassword()}
                                    className="cursor-pointer rounded-[var(--radius-sm)] p-1 text-[var(--color-text-muted)] transition-colors hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                    title={t('server-overview:list.copy_ip')}
                                >
                                    {copied
                                        ? <CheckIcon className="h-3.5 w-3.5 text-[var(--color-success)]" />
                                        : <CopyIcon className="h-3.5 w-3.5" />}
                                </button>
                            )}
                        </div>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {canUpdate && (
                        <Button variant="secondary" size="sm" isLoading={rotatePending} onClick={() => onRotate(db)}>
                            {t('server-databases:databases.rotate_password')}
                        </Button>
                    )}
                    {canDelete && (
                        <Button variant="danger" size="sm" isLoading={removePending} onClick={() => onDelete(db)}>
                            {t('server-databases:databases.delete')}
                        </Button>
                    )}
                    {Object.entries(rowActions).map(([actionId, Comp]) => (
                        <Comp key={actionId} serverId={serverId} database={db} />
                    ))}
                </div>
            </div>
        </m.div>
    );
}
