import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';

interface FileBulkBarProps {
    selectedCount: number;
    onDelete: () => void;
    onCompress: () => void;
    onDeselectAll: () => void;
    isDeleting: boolean;
    isCompressing: boolean;
}

export function FileBulkBar({ selectedCount, onDelete, onCompress, onDeselectAll, isDeleting, isCompressing }: FileBulkBarProps) {
    const { t } = useTranslation();

    if (selectedCount === 0) return null;

    return (
        <div
            className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-[var(--radius-lg)] px-5 py-3 shadow-[var(--shadow-lg)]"
            style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', backdropFilter: 'blur(12px)' }}
        >
            <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                {t('servers.files.selected_count', { count: selectedCount })}
            </span>
            <Button variant="secondary" size="sm" isLoading={isCompressing} onClick={onCompress}>
                {t('servers.files.compress')}
            </Button>
            <Button variant="danger" size="sm" isLoading={isDeleting} onClick={onDelete}>
                {t('servers.files.delete')}
            </Button>
            <button type="button" onClick={onDeselectAll} className="text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors">
                {t('servers.files.deselect_all')}
            </button>
        </div>
    );
}
