import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { Button } from '@/components/ui/Button';
import type { ServerBulkBarProps } from '@/components/server/ServerBulkBar.props';

export function ServerBulkBar({
    selectedCount,
    onBulkPower,
    onDeselectAll,
    isPending,
}: ServerBulkBarProps) {
    const { t } = useTranslation();

    return (
        <AnimatePresence>
            {selectedCount > 0 && (
                <m.div
                    initial={{ y: 100, opacity: 0 }}
                    animate={{ y: 0, opacity: 1 }}
                    exit={{ y: 100, opacity: 0 }}
                    transition={{ type: 'spring', damping: 25, stiffness: 300 }}
                    className="fixed bottom-0 left-0 right-0 z-50 backdrop-blur-xl bg-[var(--color-glass)] border-t border-[var(--color-glass-border)] shadow-[0_-8px_30px_rgba(0,0,0,0.5)] px-6 py-3"
                >
                    <div className="mx-auto flex max-w-5xl items-center justify-between">
                        <span className="text-sm font-medium text-[var(--color-text-primary)]">
                            <span className="text-[var(--color-primary)]">{selectedCount}</span>
                            {' '}{t('servers.list.selected_count', { count: selectedCount })}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="primary"
                                size="sm"
                                disabled={isPending}
                                onClick={() => onBulkPower('start')}
                                className="bg-[var(--color-success)] hover:shadow-[var(--shadow-glow-success)]"
                            >
                                {t('servers.bulk.start_all')}
                            </Button>
                            <Button
                                variant="danger"
                                size="sm"
                                disabled={isPending}
                                onClick={() => onBulkPower('stop')}
                            >
                                {t('servers.bulk.stop_all')}
                            </Button>
                            <Button
                                variant="primary"
                                size="sm"
                                disabled={isPending}
                                onClick={() => onBulkPower('restart')}
                                className="bg-[var(--color-warning)] hover:shadow-[0_0_20px_rgba(245,158,11,0.3)]"
                            >
                                {t('servers.bulk.restart_all')}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={onDeselectAll}
                            >
                                {t('servers.list.deselect_all')}
                            </Button>
                        </div>
                    </div>
                </m.div>
            )}
        </AnimatePresence>
    );
}
