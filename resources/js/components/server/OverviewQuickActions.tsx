import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { getIcon } from '@/utils/icons';
import type { SidebarEntry } from '@/hooks/useSidebarConfig';

interface OverviewQuickActionsProps {
    serverId: number;
    entries: SidebarEntry[];
}

/**
 * Row of quick-action buttons below the hero banner.
 * Excludes the "overview" entry (we're already on it).
 * Shows the first 5 enabled sidebar entries as glass pill buttons.
 */
export function OverviewQuickActions({ serverId, entries }: OverviewQuickActionsProps) {
    const { t } = useTranslation();
    const navigate = useNavigate();

    const actions = entries.filter((e) => e.id !== 'overview').slice(0, 6);

    if (actions.length === 0) return null;

    return (
        <m.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5, duration: 0.3 }}
            className="flex flex-wrap gap-2"
        >
            {actions.map((entry, i) => (
                <m.button
                    key={entry.id}
                    type="button"
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ delay: 0.5 + i * 0.05, type: 'spring', stiffness: 400, damping: 20 }}
                    onClick={() => navigate(`/servers/${serverId}${entry.route_suffix}`)}
                    className="inline-flex items-center gap-2 rounded-[var(--radius-full)] px-4 py-2 text-sm font-medium cursor-pointer transition-all duration-200 hover:scale-[1.03] active:scale-[0.97]"
                    style={{
                        background: 'var(--color-glass)',
                        backdropFilter: 'blur(12px)',
                        border: '1px solid var(--color-glass-border)',
                        color: 'var(--color-text-secondary)',
                        boxShadow: 'var(--glass-highlight)',
                    }}
                    onMouseEnter={(e) => {
                        e.currentTarget.style.borderColor = 'rgba(var(--color-primary-rgb), 0.3)';
                        e.currentTarget.style.color = 'var(--color-primary)';
                        e.currentTarget.style.boxShadow = '0 0 16px var(--color-primary-glow)';
                    }}
                    onMouseLeave={(e) => {
                        e.currentTarget.style.borderColor = 'var(--color-glass-border)';
                        e.currentTarget.style.color = 'var(--color-text-secondary)';
                        e.currentTarget.style.boxShadow = 'var(--glass-highlight)';
                    }}
                >
                    {getIcon(entry.icon)}
                    <span className="hidden sm:inline">{t(entry.label_key)}</span>
                </m.button>
            ))}
        </m.div>
    );
}
