import { useCallback, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useNetwork } from '@/hooks/useNetwork';
import { useServer } from '@/hooks/useServer';
import { useServerPermissions } from '@/hooks/useServerPermissions';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import { AllocationCard } from '@/components/network/AllocationCard';

function GlobeIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" />
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z" />
        </svg>
    );
}

function PlusIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
    );
}

function EmptyState() {
    const { t } = useTranslation();
    return (
        <div className="flex flex-col items-center justify-center py-16">
            <div className="mb-4 rounded-full bg-[var(--color-surface-hover)] p-4">
                <GlobeIcon className="h-10 w-10 text-[var(--color-text-muted)]" />
            </div>
            <p className="text-sm font-medium text-[var(--color-text-secondary)]">
                {t('servers.network.no_allocations')}
            </p>
            <p className="mt-1 text-xs text-[var(--color-text-muted)]">
                {t('servers.network.no_allocations_hint')}
            </p>
        </div>
    );
}

export function ServerNetworkPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: allocations, isLoading, add, updateNotes, setPrimary, remove } = useNetwork(serverId);
    const { data: server } = useServer(serverId);
    const perms = useServerPermissions(server);
    const canCreate = perms.has('allocation.create');
    const canUpdate = perms.has('allocation.update');
    const canDelete = perms.has('allocation.delete');

    const [editingNotes, setEditingNotes] = useState<number | null>(null);
    const [notesValue, setNotesValue] = useState('');
    const [selected, setSelected] = useState<Set<number>>(new Set());

    const toggleSelect = useCallback((allocId: number) => {
        setSelected((prev) => {
            const n = new Set(prev);
            n.has(allocId) ? n.delete(allocId) : n.add(allocId);
            return n;
        });
    }, []);
    const deselectAll = useCallback(() => setSelected(new Set()), []);

    const startEditNotes = (allocId: number, current: string | null) => {
        if (!canUpdate) return;
        setEditingNotes(allocId);
        setNotesValue(current ?? '');
    };
    const saveNotes = (allocId: number) => {
        if (!canUpdate) return;
        updateNotes.mutate({ allocationId: allocId, notes: notesValue }, {
            onSuccess: () => setEditingNotes(null),
        });
    };

    const handleBulkDelete = () => {
        if (!canDelete) return;
        if (!window.confirm(t('servers.network.bulk_confirm', { count: selected.size }))) return;
        const ids = Array.from(selected);
        ids.forEach((allocId) => remove.mutate(allocId));
        deselectAll();
    };

    if (isLoading) {
        return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;
    }

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35 }}
            className="space-y-6 pb-20"
        >
            {/* Header */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="rounded-[var(--radius)] bg-[var(--color-primary)]/10 p-2">
                        <GlobeIcon className="h-5 w-5 text-[var(--color-primary)]" />
                    </div>
                    <h2 className="text-xl font-bold text-[var(--color-text-primary)]">
                        {t('servers.network.title')}
                    </h2>
                </div>
                {canCreate && (
                    <Button variant="primary" size="sm" isLoading={add.isPending} onClick={() => add.mutate()}>
                        <PlusIcon className="h-4 w-4" />
                        {t('servers.network.add')}
                    </Button>
                )}
            </div>

            {/* Error banner */}
            {add.isError && (
                <div className="rounded-[var(--radius)] bg-[var(--color-danger)]/10 border border-[var(--color-danger)]/20 px-4 py-2.5 text-sm text-[var(--color-danger)]">
                    {t('servers.network.add_error')}
                </div>
            )}

            {/* Allocation list or empty state */}
            {(!allocations || allocations.length === 0) ? (
                <EmptyState />
            ) : (
                <div className="space-y-3">
                    {allocations.map((alloc, index) => (
                        <m.div
                            key={alloc.id}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.3, delay: index * 0.05 }}
                        >
                            <AllocationCard
                                alloc={alloc}
                                isSelected={selected.has(alloc.id)}
                                onToggleSelect={() => toggleSelect(alloc.id)}
                                onEditNotes={() => startEditNotes(alloc.id, alloc.notes)}
                                editingNotes={editingNotes === alloc.id}
                                notesValue={notesValue}
                                onNotesChange={setNotesValue}
                                onSaveNotes={() => saveNotes(alloc.id)}
                                onCancelEdit={() => setEditingNotes(null)}
                                isNotesSaving={updateNotes.isPending}
                                onSetPrimary={() => setPrimary.mutate(alloc.id)}
                                isPrimaryPending={setPrimary.isPending}
                                onDelete={() => {
                                    if (window.confirm(t('servers.network.confirm_delete'))) {
                                        remove.mutate(alloc.id);
                                    }
                                }}
                                isDeletePending={remove.isPending}
                                canUpdate={canUpdate}
                                canDelete={canDelete}
                            />
                        </m.div>
                    ))}
                </div>
            )}

            {/* Bulk action bar */}
            <AnimatePresence>
                {selected.size > 0 && canDelete && (
                    <m.div
                        initial={{ opacity: 0, y: 40, scale: 0.95 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 40, scale: 0.95 }}
                        transition={{ type: 'spring', stiffness: 400, damping: 30 }}
                        className="fixed bottom-6 left-1/2 z-50 -translate-x-1/2"
                    >
                        <div className="glass-card-enhanced flex items-center gap-3 sm:gap-4 rounded-[var(--radius-lg)] px-3 sm:px-5 py-3 shadow-[var(--shadow-lg)]">
                            <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                                {t('servers.network.selected_count', { count: selected.size })}
                            </span>
                            <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={handleBulkDelete}>
                                {t('servers.network.bulk_delete')}
                            </Button>
                            <button
                                type="button"
                                onClick={deselectAll}
                                className="cursor-pointer text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors"
                            >
                                {t('servers.network.deselect_all')}
                            </button>
                        </div>
                    </m.div>
                )}
            </AnimatePresence>
        </m.div>
    );
}
