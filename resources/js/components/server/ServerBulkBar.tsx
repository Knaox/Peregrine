import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { ServerBulkBarProps } from '@/components/server/ServerBulkBar.props';

export function ServerBulkBar({
    selectedCount,
    onBulkPower,
    onDeselectAll,
    isPending,
}: ServerBulkBarProps) {
    const { t } = useTranslation();

    return (
        <div
            className={clsx(
                'fixed bottom-0 left-0 right-0 z-50 border-t border-[var(--color-border)] bg-[var(--color-surface)] px-6 py-3 transition-transform duration-300',
                selectedCount > 0 ? 'translate-y-0' : 'translate-y-full',
            )}
        >
            <div className="mx-auto flex max-w-5xl items-center justify-between">
                <span className="text-sm font-medium text-[var(--color-text-primary)]">
                    {t('servers.list.selected_count', { count: selectedCount })}
                </span>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        disabled={isPending}
                        onClick={() => onBulkPower('start')}
                        className="rounded-[var(--radius)] bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                    >
                        {t('servers.bulk.start_all')}
                    </button>
                    <button
                        type="button"
                        disabled={isPending}
                        onClick={() => onBulkPower('stop')}
                        className="rounded-[var(--radius)] bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                    >
                        {t('servers.bulk.stop_all')}
                    </button>
                    <button
                        type="button"
                        disabled={isPending}
                        onClick={() => onBulkPower('restart')}
                        className="rounded-[var(--radius)] bg-amber-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-amber-700 disabled:opacity-50"
                    >
                        {t('servers.bulk.restart_all')}
                    </button>
                    <button
                        type="button"
                        onClick={onDeselectAll}
                        className="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-1.5 text-xs font-medium text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-text-primary)]"
                    >
                        {t('servers.list.deselect_all')}
                    </button>
                </div>
            </div>
        </div>
    );
}
