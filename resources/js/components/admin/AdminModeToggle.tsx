import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { m } from 'motion/react';
import clsx from 'clsx';

/**
 * Renders only when `user.is_admin` is true. Navigates to /admin/servers — the
 * React admin dashboard (separate from the Filament admin panel at /admin).
 */
export function AdminModeToggle() {
    const { t } = useTranslation();
    const navigate = useNavigate();

    return (
        <m.button
            type="button"
            onClick={() => navigate('/admin/servers')}
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: 0.35, type: 'spring', stiffness: 300, damping: 20 }}
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            className={clsx(
                'inline-flex items-center gap-2',
                'rounded-[var(--radius-full)] px-5 py-2.5 text-sm font-medium',
                'glass-card-enhanced border-[var(--color-primary)]/30',
                'text-[var(--color-primary)] cursor-pointer',
                'transition-all duration-200',
                'hover:border-[var(--color-primary)]/50 hover:shadow-[0_0_24px_var(--color-primary-glow)]',
            )}
        >
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                />
            </svg>
            {t('admin.servers.mode_toggle')}
        </m.button>
    );
}
