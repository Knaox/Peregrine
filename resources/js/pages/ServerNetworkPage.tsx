import { useCallback, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useNetwork } from '@/hooks/useNetwork';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import type { Allocation } from '@/types/Allocation';

function GlobeIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" />
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z" />
        </svg>
    );
}

function StarIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="currentColor" stroke="none">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
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

function AllocationCard({ alloc, isSelected, onToggleSelect, onEditNotes, editingNotes, notesValue, onNotesChange, onSaveNotes, onCancelEdit, isNotesSaving, onSetPrimary, isPrimaryPending, onDelete, isDeletePending }: {
    alloc: Allocation;
    isSelected: boolean;
    onToggleSelect: () => void;
    onEditNotes: () => void;
    editingNotes: boolean;
    notesValue: string;
    onNotesChange: (v: string) => void;
    onSaveNotes: () => void;
    onCancelEdit: () => void;
    isNotesSaving: boolean;
    onSetPrimary: () => void;
    isPrimaryPending: boolean;
    onDelete: () => void;
    isDeletePending: boolean;
}) {
    const { t } = useTranslation();

    return (
        <div className={clsx(
            'glass-card-enhanced rounded-[var(--radius-lg)] p-4 transition-all duration-200',
            isSelected && 'ring-2 ring-[var(--color-primary)]/40',
        )}>
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                <div className="flex items-center gap-3 min-w-0">
                    {!alloc.is_default && (
                        <input
                            type="checkbox"
                            checked={isSelected}
                            onChange={onToggleSelect}
                            className="cursor-pointer rounded border-[var(--color-border)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        />
                    )}
                    <div className="space-y-2">
                        <div className="flex items-center gap-2.5">
                            <div className="rounded-[var(--radius)] bg-[var(--color-surface-hover)] px-3 py-1.5">
                                <span
                                    className="text-sm sm:text-base font-bold text-[var(--color-text-primary)] break-all"
                                    style={{ fontFamily: 'var(--font-mono)' }}
                                >
                                    {alloc.ip_alias ?? alloc.ip}:{alloc.port}
                                </span>
                            </div>
                            {alloc.is_default && (
                                <span className="inline-flex items-center gap-1 rounded-[var(--radius)] bg-[var(--color-primary)]/15 px-2 py-1 text-xs font-semibold text-[var(--color-primary)]">
                                    <StarIcon className="h-3 w-3" />
                                    {t('servers.network.primary')}
                                </span>
                            )}
                        </div>
                        {editingNotes ? (
                            <div className="flex items-center gap-2">
                                <input
                                    value={notesValue}
                                    onChange={(e) => onNotesChange(e.target.value)}
                                    placeholder={t('servers.network.notes_placeholder')}
                                    className="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-2.5 py-1.5 text-xs text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-glow)]"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') onSaveNotes();
                                        if (e.key === 'Escape') onCancelEdit();
                                    }}
                                    autoFocus
                                />
                                <Button variant="primary" size="sm" isLoading={isNotesSaving} onClick={onSaveNotes}>
                                    {t('common.save')}
                                </Button>
                                <button
                                    type="button"
                                    onClick={onCancelEdit}
                                    className="cursor-pointer text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors"
                                >
                                    {t('common.cancel')}
                                </button>
                            </div>
                        ) : (
                            <button
                                type="button"
                                onClick={onEditNotes}
                                className="cursor-pointer flex items-center gap-1.5 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors"
                            >
                                <svg className="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                </svg>
                                {alloc.notes || t('servers.network.notes')}
                            </button>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    {!alloc.is_default && (
                        <Button variant="secondary" size="sm" isLoading={isPrimaryPending} onClick={onSetPrimary}>
                            {t('servers.network.set_primary')}
                        </Button>
                    )}
                    {!alloc.is_default && (
                        <Button variant="danger" size="sm" isLoading={isDeletePending} onClick={onDelete}>
                            {t('servers.network.delete')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
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
        setEditingNotes(allocId);
        setNotesValue(current ?? '');
    };
    const saveNotes = (allocId: number) => {
        updateNotes.mutate({ allocationId: allocId, notes: notesValue }, {
            onSuccess: () => setEditingNotes(null),
        });
    };

    const handleBulkDelete = () => {
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
                <Button variant="primary" size="sm" isLoading={add.isPending} onClick={() => add.mutate()}>
                    <PlusIcon className="h-4 w-4" />
                    {t('servers.network.add')}
                </Button>
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
                            />
                        </m.div>
                    ))}
                </div>
            )}

            {/* Bulk action bar */}
            <AnimatePresence>
                {selected.size > 0 && (
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
