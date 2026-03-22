import { useCallback, useEffect, useRef, useState } from 'react';
import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { AnimatedBackground } from '@/components/AnimatedBackground';

export function AppLayout() {
    const { t, i18n } = useTranslation();
    const { user, logout } = useAuthStore();
    const branding = useBranding();
    const location = useLocation();
    const navigate = useNavigate();
    const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const navLinks = [
        { to: '/dashboard', label: t('nav.dashboard') },
    ];

    const isActive = (path: string) => location.pathname === path;

    const closeUserMenu = useCallback(() => setIsUserMenuOpen(false), []);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
                closeUserMenu();
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [closeUserMenu]);

    useEffect(() => {
        setIsMobileMenuOpen(false);
        closeUserMenu();
    }, [location.pathname, closeUserMenu]);

    return (
        <div className="min-h-screen bg-[var(--color-background)] text-[var(--color-text-primary)]">
            {/* Top navbar */}
            <nav className="sticky top-0 z-50 border-b border-[var(--color-glass-border)] bg-[var(--color-glass)] backdrop-blur-xl">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        {/* Left: Logo + Nav links */}
                        <div className="flex items-center gap-8">
                            <Link
                                to="/dashboard"
                                className="flex items-center gap-3 transition-all duration-[var(--transition-smooth)] hover:drop-shadow-[0_0_12px_var(--color-primary-glow)]"
                            >
                                <img
                                    src={branding.logo_url}
                                    alt={branding.app_name}
                                    className="h-8 w-8"
                                />
                                <span className="text-lg font-semibold text-[var(--color-text-primary)]">
                                    {branding.app_name}
                                </span>
                            </Link>

                            {/* Desktop nav */}
                            <div className="hidden items-center gap-1 md:flex">
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.to}
                                        to={link.to}
                                        className={clsx(
                                            'relative px-3 py-2 text-sm font-medium transition-all duration-[var(--transition-base)]',
                                            'after:absolute after:bottom-0 after:left-1/2 after:h-0.5 after:-translate-x-1/2',
                                            'after:rounded-full after:bg-[var(--color-primary)] after:transition-all after:duration-[var(--transition-smooth)]',
                                            isActive(link.to)
                                                ? 'text-[var(--color-primary)] after:w-full'
                                                : 'text-[var(--color-text-secondary)] after:w-0 hover:text-[var(--color-text-primary)] hover:after:w-full',
                                        )}
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        {/* Right: User menu */}
                        <div className="flex items-center gap-4">
                            {/* Desktop user menu */}
                            <div className="relative hidden md:block" ref={menuRef}>
                                <button
                                    type="button"
                                    onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                                    className="flex items-center gap-2 rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:text-[var(--color-text-primary)]"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-primary)] text-sm font-bold text-white ring-2 ring-[var(--color-primary-glow)]">
                                        {user?.name.charAt(0).toUpperCase()}
                                    </div>
                                    <span>{user?.name}</span>
                                    <svg
                                        className={clsx(
                                            'h-4 w-4 transition-transform duration-[var(--transition-base)]',
                                            isUserMenuOpen && 'rotate-180',
                                        )}
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <AnimatePresence>
                                    {isUserMenuOpen && (
                                        <m.div
                                            initial={{ opacity: 0, y: -8 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -8 }}
                                            transition={{ duration: 0.15 }}
                                            className="absolute right-0 mt-2 w-48 overflow-hidden rounded-[var(--radius)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] py-1 shadow-[var(--shadow-lg)] backdrop-blur-xl"
                                        >
                                            <Link
                                                to="/profile"
                                                onClick={closeUserMenu}
                                                className="block px-4 py-2 text-sm text-[var(--color-text-secondary)] transition-all duration-[var(--transition-fast)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                            >
                                                {t('nav.profile')}
                                            </Link>
                                            {user?.is_admin && (
                                                <a
                                                    href="/admin"
                                                    className="block px-4 py-2 text-sm text-[var(--color-text-secondary)] transition-all duration-[var(--transition-fast)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                                >
                                                    {t('nav.settings')}
                                                </a>
                                            )}
                                            <div className="flex items-center justify-between px-4 py-2">
                                                <span className="text-xs text-[var(--color-text-muted)]">{t('profile.locale')}</span>
                                                <div className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => { void i18n.changeLanguage('en'); closeUserMenu(); }}
                                                        className={clsx(
                                                            'rounded px-2 py-0.5 text-xs font-medium transition-all duration-[var(--transition-fast)]',
                                                            i18n.language.startsWith('en')
                                                                ? 'bg-[var(--color-primary)] text-white'
                                                                : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                                        )}
                                                    >
                                                        EN
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => { void i18n.changeLanguage('fr'); closeUserMenu(); }}
                                                        className={clsx(
                                                            'rounded px-2 py-0.5 text-xs font-medium transition-all duration-[var(--transition-fast)]',
                                                            i18n.language.startsWith('fr')
                                                                ? 'bg-[var(--color-primary)] text-white'
                                                                : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                                        )}
                                                    >
                                                        FR
                                                    </button>
                                                </div>
                                            </div>
                                            <hr className="my-1 border-[var(--color-border)]" />
                                            <button
                                                type="button"
                                                onClick={handleLogout}
                                                className="block w-full px-4 py-2 text-left text-sm text-[var(--color-text-secondary)] transition-all duration-[var(--transition-fast)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                            >
                                                {t('nav.logout')}
                                            </button>
                                        </m.div>
                                    )}
                                </AnimatePresence>
                            </div>

                            {/* Mobile menu button */}
                            <button
                                type="button"
                                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                                className="rounded-[var(--radius)] p-2 text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)] md:hidden"
                            >
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    {isMobileMenuOpen ? (
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    ) : (
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                    )}
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile menu */}
                <AnimatePresence>
                    {isMobileMenuOpen && (
                        <m.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            transition={{ duration: 0.2 }}
                            className="overflow-hidden border-t border-[var(--color-border)] md:hidden"
                        >
                            <div className="space-y-1 px-4 py-3">
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.to}
                                        to={link.to}
                                        className={clsx(
                                            'block rounded-[var(--radius)] px-3 py-2 text-sm font-medium transition-all duration-[var(--transition-base)]',
                                            isActive(link.to)
                                                ? 'bg-[var(--color-surface-hover)] text-[var(--color-primary)]'
                                                : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
                                        )}
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                                <hr className="border-[var(--color-border)]" />
                                <Link
                                    to="/profile"
                                    className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                >
                                    {t('nav.profile')}
                                </Link>
                                {user?.is_admin && (
                                    <a
                                        href="/admin"
                                        className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                    >
                                        {t('nav.settings')}
                                    </a>
                                )}
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    className="block w-full rounded-[var(--radius)] px-3 py-2 text-left text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                >
                                    {t('nav.logout')}
                                </button>
                            </div>
                        </m.div>
                    )}
                </AnimatePresence>
            </nav>

            <AnimatedBackground />

            {/* Main content */}
            <main className="relative z-10 px-6 py-8 lg:px-10">
                <Outlet />
            </main>
        </div>
    );
}
