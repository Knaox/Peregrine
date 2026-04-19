import { useTranslation } from 'react-i18next';
import { m, AnimatePresence } from 'motion/react';
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

    return (
        <AnimatePresence>
            {selectedCount > 0 && (
                <m.div
                    initial={{ opacity: 0, y: 20, scale: 0.95 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: 20, scale: 0.95 }}
                    transition={{ type: 'spring', stiffness: 400, damping: 25 }}
                    className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 sm:gap-3 rounded-[var(--radius-lg)] px-3 sm:px-5 py-3 glass-card-enhanced"
                    style={{ boxShadow: '0 12px 40px rgba(0,0,0,0.5)' }}
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
                    <button type="button" onClick={onDeselectAll}
                        className="text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors cursor-pointer">
                        {t('servers.files.deselect_all')}
                    </button>
                </m.div>
            )}
        </AnimatePresence>
    );
}
