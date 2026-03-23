import { useState } from 'react';
import { Outlet, Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { AnimatedBackground } from '@/components/AnimatedBackground';
import { NavHeaderLinks } from '@/components/layout/NavHeaderLinks';
import { UserMenu } from '@/components/layout/UserMenu';
import { getHeaderIcon } from '@/utils/headerIcons';

export function AppLayout() {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const branding = useBranding();
    const navigate = useNavigate();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const links = branding.header_links ?? [];

    return (
        <div className="min-h-screen bg-[var(--color-background)] text-[var(--color-text-primary)]">
            <nav className="sticky top-0 z-50 border-b border-[var(--color-border)] bg-[var(--color-surface)]/90 backdrop-blur-xl">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        {/* Left: Logo + Nav */}
                        <div className="flex items-center gap-8">
                            <Link
                                to="/dashboard"
                                className="flex items-center gap-3 transition-all duration-[var(--transition-smooth)] hover:drop-shadow-[0_0_12px_var(--color-primary-glow)]"
                            >
                                <img
                                    src={branding.logo_url}
                                    alt={branding.app_name}
                                    className="w-auto"
                                    style={{ height: branding.logo_height, maxWidth: 200 }}
                                />
                                {branding.show_app_name && (
                                    <span className="text-lg font-semibold text-[var(--color-text-primary)]">
                                        {branding.app_name}
                                    </span>
                                )}
                            </Link>

                            <div className="hidden items-center gap-1 md:flex">
                                <NavHeaderLinks links={links} />
                            </div>
                        </div>

                        {/* Right: User menu + mobile toggle */}
                        <div className="flex items-center gap-4">
                            <UserMenu />
                            <button
                                type="button"
                                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                                className="rounded-[var(--radius)] p-2 text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)] md:hidden"
                            >
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    {isMobileMenuOpen
                                        ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                    }
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
                                <NavHeaderLinks links={links} mobile />
                                <hr className="border-[var(--color-border)]" />
                                <Link
                                    to="/profile"
                                    className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                                >
                                    {t('nav.profile')}
                                </Link>
                                {user?.is_admin && (
                                    <a href="/admin" className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                                        {t('nav.settings')}
                                    </a>
                                )}
                                <button type="button" onClick={handleLogout} className="block w-full rounded-[var(--radius)] px-3 py-2 text-left text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-[var(--transition-base)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                                    {t('nav.logout')}
                                </button>
                            </div>
                        </m.div>
                    )}
                </AnimatePresence>
            </nav>

            <AnimatedBackground />

            <main className="relative z-10 px-6 py-8 lg:px-10">
                <Outlet />
            </main>
        </div>
    );
}
