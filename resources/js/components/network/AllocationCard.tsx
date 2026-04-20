import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { Button } from '@/components/ui/Button';
import type { Allocation } from '@/types/Allocation';

function StarIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="currentColor" stroke="none">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
        </svg>
    );
}

interface AllocationCardProps {
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
    canUpdate?: boolean;
    canDelete?: boolean;
}

export function AllocationCard({
    alloc, isSelected, onToggleSelect, onEditNotes, editingNotes, notesValue, onNotesChange,
    onSaveNotes, onCancelEdit, isNotesSaving, onSetPrimary, isPrimaryPending, onDelete,
    isDeletePending,
    canUpdate = true,
    canDelete: canDeleteAllocation = true,
}: AllocationCardProps) {
    const { t } = useTranslation();

    return (
        <div className={clsx(
            'glass-card-enhanced rounded-[var(--radius-lg)] p-4 transition-all duration-200',
            isSelected && 'ring-2 ring-[var(--color-primary)]/40',
        )}>
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                <div className="flex items-center gap-3 min-w-0">
                    {!alloc.is_default && canDeleteAllocation && (
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
                        {editingNotes && canUpdate ? (
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
                        ) : canUpdate ? (
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
                        ) : alloc.notes ? (
                            <p className="text-xs text-[var(--color-text-muted)]">{alloc.notes}</p>
                        ) : null}
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    {!alloc.is_default && canUpdate && (
                        <Button variant="secondary" size="sm" isLoading={isPrimaryPending} onClick={onSetPrimary}>
                            {t('servers.network.set_primary')}
                        </Button>
                    )}
                    {!alloc.is_default && canDeleteAllocation && (
                        <Button variant="danger" size="sm" isLoading={isDeletePending} onClick={onDelete}>
                            {t('servers.network.delete')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
