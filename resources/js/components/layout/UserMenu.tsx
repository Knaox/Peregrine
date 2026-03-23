import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';

export function UserMenu() {
    const { t, i18n } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const location = useLocation();
    const [isOpen, setIsOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    const close = useCallback(() => setIsOpen(false), []);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) close();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [close]);

    useEffect(() => { close(); }, [location.pathname, close]);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    if (!user) return null;

    return (
        <div className="relative hidden md:block" ref={menuRef}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-2 rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:text-[var(--color-text-primary)]"
            >
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-primary)] text-sm font-bold text-white ring-2 ring-[var(--color-primary-glow)]">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <span>{user.name}</span>
                <svg className={clsx('h-4 w-4 transition-transform duration-[var(--transition-base)]', isOpen && 'rotate-180')} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <AnimatePresence>
                {isOpen && (
                    <m.div
                        initial={{ opacity: 0, y: -8 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -8 }}
                        transition={{ duration: 0.15 }}
                        className="absolute right-0 mt-2 w-48 overflow-hidden rounded-[var(--radius)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] py-1 shadow-[var(--shadow-lg)] backdrop-blur-xl"
                    >
                        <Link to="/profile" onClick={close} className="block px-4 py-2 text-sm text-[var(--color-text-secondary)] transition-all duration-[var(--transition-fast)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                            {t('nav.profile')}
                        </Link>
                        {user.is_admin && (
                            <a href="/admin" className="block px-4 py-2 text-sm text-[var(--color-text-secondary)] transition-all duration-[var(--transition-fast)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                                {t('nav.settings')}
                            </a>
                        )}
                        <div className="flex items-center justify-between px-4 py-2">
                            <span className="text-xs text-[var(--color-text-muted)]">{t('profile.locale')}</span>
                            <div className="flex gap-1">
                                {['en', 'fr'].map((lang) => (
                                    <button
                                        key={lang}
                                        type="button"
                                        onClick={() => { void i18n.changeLanguage(lang); close(); }}
                                        className={clsx(
                                            'rounded px-2 py-0.5 text-xs font-medium transition-all duration-[var(--transition-fast)]',
                                            i18n.language.startsWith(lang) ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                        )}
                                    >
                                        {lang.toUpperCase()}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <hr className="my-1 border-[var(--color-border)]" />
                        <button type="button" onClick={handleLogout} className="block w-full px-4 py-2 text-left text-sm text-[var(--color-text-secondary)] transition-all duration-[var(--transition-fast)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                            {t('nav.logout')}
                        </button>
                    </m.div>
                )}
            </AnimatePresence>
        </div>
    );
}
