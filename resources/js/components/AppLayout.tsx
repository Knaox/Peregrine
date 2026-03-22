import { useState } from 'react';
import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';

export function AppLayout() {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const branding = useBranding();
    const location = useLocation();
    const navigate = useNavigate();
    const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const navLinks = [
        { to: '/dashboard', label: t('nav.dashboard') },
        { to: '/servers', label: t('nav.servers') },
    ];

    const isActive = (path: string) => location.pathname === path;

    return (
        <div className="min-h-screen bg-[var(--color-background)] text-[var(--color-text-primary)]">
            {/* Top navbar */}
            <nav className="border-b border-[var(--color-border)] bg-[var(--color-surface)]">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        {/* Left: Logo + Nav links */}
                        <div className="flex items-center gap-8">
                            <Link to="/dashboard" className="flex items-center gap-3">
                                <img
                                    src={branding.logo_url}
                                    alt={branding.app_name}
                                    className="h-8 w-8"
                                />
                                <span className="text-lg font-semibold">
                                    {branding.app_name}
                                </span>
                            </Link>

                            {/* Desktop nav */}
                            <div className="hidden md:flex items-center gap-1">
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.to}
                                        to={link.to}
                                        className={`rounded-[var(--radius)] px-3 py-2 text-sm font-medium transition-colors ${
                                            isActive(link.to)
                                                ? 'bg-[var(--color-surface-hover)] text-[var(--color-text-primary)]'
                                                : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]'
                                        }`}
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        {/* Right: User menu */}
                        <div className="flex items-center gap-4">
                            {/* Desktop user menu */}
                            <div className="relative hidden md:block">
                                <button
                                    type="button"
                                    onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                                    className="flex items-center gap-2 rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-colors hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-primary)] text-sm font-bold text-[var(--color-text-primary)]">
                                        {user?.name.charAt(0).toUpperCase()}
                                    </div>
                                    <span>{user?.name}</span>
                                    <svg
                                        className={`h-4 w-4 transition-transform ${isUserMenuOpen ? 'rotate-180' : ''}`}
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {isUserMenuOpen && (
                                    <div className="absolute right-0 mt-2 w-48 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] py-1 shadow-lg">
                                        <Link
                                            to="/profile"
                                            onClick={() => setIsUserMenuOpen(false)}
                                            className="block px-4 py-2 text-sm text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                        >
                                            {t('nav.profile')}
                                        </Link>
                                        {user?.is_admin && (
                                            <a
                                                href="/admin"
                                                className="block px-4 py-2 text-sm text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                            >
                                                {t('nav.settings')}
                                            </a>
                                        )}
                                        <hr className="my-1 border-[var(--color-border)]" />
                                        <button
                                            type="button"
                                            onClick={handleLogout}
                                            className="block w-full px-4 py-2 text-left text-sm text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                        >
                                            {t('nav.logout')}
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Mobile menu button */}
                            <button
                                type="button"
                                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                                className="md:hidden rounded-[var(--radius)] p-2 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
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
                {isMobileMenuOpen && (
                    <div className="border-t border-[var(--color-border)] md:hidden">
                        <div className="space-y-1 px-4 py-3">
                            {navLinks.map((link) => (
                                <Link
                                    key={link.to}
                                    to={link.to}
                                    onClick={() => setIsMobileMenuOpen(false)}
                                    className={`block rounded-[var(--radius)] px-3 py-2 text-sm font-medium ${
                                        isActive(link.to)
                                            ? 'bg-[var(--color-surface-hover)] text-[var(--color-text-primary)]'
                                            : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]'
                                    }`}
                                >
                                    {link.label}
                                </Link>
                            ))}
                            <hr className="border-[var(--color-border)]" />
                            <Link
                                to="/profile"
                                onClick={() => setIsMobileMenuOpen(false)}
                                className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                            >
                                {t('nav.profile')}
                            </Link>
                            {user?.is_admin && (
                                <a
                                    href="/admin"
                                    className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                >
                                    {t('nav.settings')}
                                </a>
                            )}
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="block w-full rounded-[var(--radius)] px-3 py-2 text-left text-sm font-medium text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                            >
                                {t('nav.logout')}
                            </button>
                        </div>
                    </div>
                )}
            </nav>

            {/* Main content */}
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Outlet />
            </main>
        </div>
    );
}
