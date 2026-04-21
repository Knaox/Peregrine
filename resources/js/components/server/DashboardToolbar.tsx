import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { ServerSearchBar } from '@/components/server/ServerSearchBar';

interface DashboardToolbarProps {
    search: string;
    onSearchChange: (value: string) => void;
    isSelectionMode: boolean;
    onToggleSelection: () => void;
}

/**
 * Row containing the selection-mode toggle + the server search input.
 * Lives above the category grid on the dashboard.
 */
export function DashboardToolbar({ search, onSearchChange, isSelectionMode, onToggleSelection }: DashboardToolbarProps) {
    const { t } = useTranslation();

    return (
        <div className="mb-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 p-2 glass-card-enhanced rounded-[var(--radius-lg)]">
            <button
                type="button"
                onClick={onToggleSelection}
                className={clsx(
                    'flex items-center justify-center sm:justify-start gap-1.5 rounded-[var(--radius)] border px-3 py-2.5 sm:py-2 text-xs font-medium cursor-pointer',
                    'transition-all duration-200',
                    isSelectionMode
                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] shadow-[var(--shadow-glow)]'
                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:shadow-[var(--shadow-glow)]',
                )}
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                {t('servers.list.select_mode')}
            </button>
            <div className="flex-1">
                <ServerSearchBar value={search} onChange={onSearchChange} />
            </div>
        </div>
    );
}
