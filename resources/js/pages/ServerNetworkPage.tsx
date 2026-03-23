import { useCallback, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { useNetwork } from '@/hooks/useNetwork';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';

export function ServerNetworkPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: allocations, isLoading, add, updateNotes, setPrimary, remove } = useNetwork(serverId);

    const [editingNotes, setEditingNotes] = useState<number | null>(null);
    const [notesValue, setNotesValue] = useState('');
    const [selected, setSelected] = useState<Set<number>>(new Set());

    const toggleSelect = useCallback((allocId: number) => {
        setSelected((prev) => { const n = new Set(prev); n.has(allocId) ? n.delete(allocId) : n.add(allocId); return n; });
    }, []);
    const deselectAll = useCallback(() => setSelected(new Set()), []);

    const startEditNotes = (allocId: number, current: string | null) => { setEditingNotes(allocId); setNotesValue(current ?? ''); };
    const saveNotes = (allocId: number) => { updateNotes.mutate({ allocationId: allocId, notes: notesValue }, { onSuccess: () => setEditingNotes(null) }); };

    const handleBulkDelete = () => {
        if (!window.confirm(t('servers.network.bulk_confirm', { count: selected.size }))) return;
        const ids = Array.from(selected);
        ids.forEach((allocId) => remove.mutate(allocId));
        deselectAll();
    };

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6 pb-16">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('servers.network.title')}</h2>
                <Button variant="primary" size="sm" isLoading={add.isPending} onClick={() => add.mutate()}>
                    + {t('servers.network.add')}
                </Button>
            </div>

            {add.isError && (
                <div className="rounded-[var(--radius)] bg-[var(--color-danger)]/10 px-3 py-2 text-sm text-[var(--color-danger)]">
                    {(() => {
                        const status = (add.error as { status?: number })?.status;
                        if (status === 429) return t('servers.network.add_rate_limited');
                        if (status === 422) return t('servers.network.add_limit_reached');
                        return t('servers.network.add_error');
                    })()}
                </div>
            )}

            {(!allocations || allocations.length === 0) ? (
                <p className="py-8 text-center text-[var(--color-text-muted)]">{t('servers.network.no_allocations')}</p>
            ) : (
                <div className="space-y-3">
                    {allocations.map((alloc) => (
                        <div key={alloc.id} className={clsx('transition-all duration-150', selected.has(alloc.id) && 'ring-1 ring-[var(--color-primary)]/30')} style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 16 }}>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    {!alloc.is_default && (
                                        <input type="checkbox" checked={selected.has(alloc.id)} onChange={() => toggleSelect(alloc.id)} className="rounded" />
                                    )}
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-semibold text-[var(--color-text-primary)]" style={{ fontFamily: 'var(--font-mono)' }}>
                                                {alloc.ip_alias ?? alloc.ip}:{alloc.port}
                                            </p>
                                            {alloc.is_default && (
                                                <span className="rounded-[var(--radius-sm)] bg-[var(--color-primary)]/15 px-1.5 py-0.5 text-[10px] font-medium text-[var(--color-primary)]">
                                                    {t('servers.network.primary')}
                                                </span>
                                            )}
                                        </div>
                                        {editingNotes === alloc.id ? (
                                            <div className="flex items-center gap-2">
                                                <input value={notesValue} onChange={(e) => setNotesValue(e.target.value)} placeholder={t('servers.network.notes_placeholder')}
                                                    className="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-2 py-1 text-xs text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none"
                                                    onKeyDown={(e) => { if (e.key === 'Enter') saveNotes(alloc.id); if (e.key === 'Escape') setEditingNotes(null); }} autoFocus />
                                                <Button variant="primary" size="sm" isLoading={updateNotes.isPending} onClick={() => saveNotes(alloc.id)}>{t('common.save')}</Button>
                                            </div>
                                        ) : (
                                            <button type="button" onClick={() => startEditNotes(alloc.id, alloc.notes)} className="text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)]">
                                                {alloc.notes || t('servers.network.notes')}
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    {!alloc.is_default && <Button variant="secondary" size="sm" isLoading={setPrimary.isPending} onClick={() => setPrimary.mutate(alloc.id)}>{t('servers.network.set_primary')}</Button>}
                                    {!alloc.is_default && <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={() => { if (window.confirm(t('servers.network.confirm_delete'))) remove.mutate(alloc.id); }}>{t('servers.network.delete')}</Button>}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Bulk action bar */}
            {selected.size > 0 && (
                <div className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-[var(--radius-lg)] px-5 py-3 shadow-[var(--shadow-lg)]"
                    style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', backdropFilter: 'blur(12px)' }}>
                    <span className="text-sm font-medium text-[var(--color-text-secondary)]">{t('servers.network.selected_count', { count: selected.size })}</span>
                    <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={handleBulkDelete}>{t('servers.network.bulk_delete')}</Button>
                    <button type="button" onClick={deselectAll} className="text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors">{t('servers.network.deselect_all')}</button>
                </div>
            )}
        </m.div>
    );
}
