import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';

interface SidebarBackButtonProps {
    /** Collapse the label on narrow screens; icon-only. */
    compact?: boolean;
    /** Pill-style glass (Dock/TopTabs) or flat (LeftSidebar). */
    variant?: 'glass' | 'flat';
}

/**
 * Back-to-dashboard affordance shared by every sidebar preset.
 *
 * Ensures a minimum 44×44pt touch target and a visible focus ring.
 */
export function SidebarBackButton({ compact = false, variant = 'glass' }: SidebarBackButtonProps) {
    const { t } = useTranslation();
    const navigate = useNavigate();

    const isGlass = variant === 'glass';

    return (
        <button
            type="button"
            onClick={() => navigate('/dashboard')}
            title={t('servers.detail.back')}
            aria-label={t('servers.detail.back')}
            className={clsx(
                'inline-flex items-center gap-2 min-h-[44px] cursor-pointer transition-all duration-150',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                isGlass ? 'px-3.5 py-2.5 text-sm rounded-full' : 'px-3 text-xs',
            )}
            style={isGlass
                ? {
                    background: 'var(--color-glass)',
                    backdropFilter: 'var(--glass-blur)',
                    border: '1px solid var(--color-glass-border)',
                    color: 'var(--color-text-secondary)',
                }
                : { color: 'var(--color-text-muted)' }
            }
        >
            <svg className="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {!compact && <span className="hidden sm:inline">{t('servers.detail.back')}</span>}
        </button>
    );
}
