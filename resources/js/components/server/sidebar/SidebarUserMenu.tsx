import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';

interface SidebarUserMenuProps {
    /** Menu placement — controls where the popover anchors. */
    align?: 'top' | 'bottom';
}

/**
 * Compact avatar-trigger user menu used in the Dock and TopTabs layouts where
 * there's no room for the full user pill. Opens a popover with Profile link
 * and Logout, matching the affordances available in the Classic sidebar.
 */
export function SidebarUserMenu({ align = 'bottom' }: SidebarUserMenuProps) {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    const close = useCallback(() => setOpen(false), []);

    useEffect(() => {
        const handler = (e: MouseEvent): void => {
            if (ref.current && !ref.current.contains(e.target as Node)) close();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [close]);

    if (!user) return null;

    const handleLogout = async (): Promise<void> => {
        close();
        await logout();
        navigate('/login');
    };

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                title={user.name}
                aria-label={t('nav.user_menu', { defaultValue: 'User menu' })}
                aria-expanded={open}
                aria-haspopup="menu"
                className={clsx(
                    'flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white cursor-pointer transition-all duration-150 hover:scale-105',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                )}
                style={{ background: 'var(--color-primary)', boxShadow: '0 0 16px var(--color-primary-glow)' }}
            >
                {user.name.charAt(0).toUpperCase()}
            </button>

            <AnimatePresence>
                {open && (
                    <m.div
                        role="menu"
                        initial={{ opacity: 0, y: align === 'top' ? 8 : -8, scale: 0.96 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: align === 'top' ? 8 : -8, scale: 0.96 }}
                        transition={{ duration: 0.18, ease: [0.4, 0, 0.2, 1] }}
                        className={clsx(
                            'absolute right-0 w-56 overflow-hidden rounded-[var(--radius)] py-1 z-50',
                            align === 'top' ? 'bottom-full mb-2' : 'top-full mt-2',
                        )}
                        style={{
                            background: 'var(--color-glass)',
                            backdropFilter: 'blur(16px)',
                            border: '1px solid var(--color-glass-border)',
                            boxShadow: 'var(--shadow-lg)',
                        }}
                    >
                        <div className="px-3 py-2">
                            <p className="truncate text-xs font-medium" style={{ color: 'var(--color-text-primary)' }}>{user.name}</p>
                            <p className="truncate" style={{ fontSize: '0.65rem', color: 'var(--color-text-muted)' }}>{user.email}</p>
                        </div>
                        <hr className="border-[var(--color-border)]" />
                        <Link
                            to="/profile"
                            onClick={close}
                            role="menuitem"
                            className="block px-3 py-2 text-sm text-[var(--color-text-secondary)] hover:bg-[var(--surface-overlay-hover)] hover:text-[var(--color-text-primary)]"
                        >
                            {t('nav.profile')}
                        </Link>
                        <button
                            type="button"
                            onClick={handleLogout}
                            role="menuitem"
                            className="block w-full px-3 py-2 text-left text-sm text-[var(--color-text-secondary)] hover:bg-[var(--surface-overlay-hover)] hover:text-[var(--color-danger)]"
                        >
                            {t('nav.logout')}
                        </button>
                    </m.div>
                )}
            </AnimatePresence>
        </div>
    );
}
