import { useState } from 'react';
import { Outlet, Link, useNavigate, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { useResolvedTheme } from '@/hooks/useResolvedTheme';
import { AnimatedBackground } from '@/components/AnimatedBackground';
import { NavHeaderLinks } from '@/components/layout/NavHeaderLinks';
import { UserMenu } from '@/components/layout/UserMenu';
import { AppFooter } from '@/components/AppFooter';

export function AppLayout() {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const branding = useBranding();
    const navigate = useNavigate();
    const location = useLocation();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    // Reads via the centralised ThemeContext so the studio's preview iframe
    // sees postMessage-driven changes (the previous useQuery here pulled the
    // live API and ignored the postMessage payload entirely).
    const theme = useResolvedTheme();
    const footer = theme?.data.footer;
    const appPattern = theme?.data.app?.background_pattern ?? 'none';

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const links = branding.header_links ?? [];

    return (
        <div className="flex min-h-screen flex-col bg-[var(--color-background)] text-[var(--color-text-primary)]">
            {/* Enhanced navbar with glass effect.
                `app-nav` lets the data-header-sticky CSS rule toggle position. */}
            <nav className="app-nav themed-border-y sticky top-0 z-50 border-b border-[var(--color-border)]"
                style={{
                    background: 'var(--color-glass)',
                    backdropFilter: 'var(--glass-blur)',
                    boxShadow: 'var(--glass-highlight), var(--shadow-sm)',
                }}
            >
                <div
                    className="app-container app-header"
                    style={{ maxWidth: 'var(--layout-container-max)' }}
                >
                    <div
                        className="app-header-row flex items-center justify-between"
                        style={{ height: 'var(--layout-header-height)' }}
                    >
                        {/* Left: Logo + Nav */}
                        <div className="flex items-center gap-3 sm:gap-8">
                            <Link
                                to="/dashboard"
                                className="group flex items-center gap-2 sm:gap-3 transition-all duration-300"
                            >
                                <m.img
                                    src={branding.logo_url}
                                    alt={branding.app_name}
                                    className="w-auto"
                                    style={{
                                        height: branding.logo_height,
                                        maxHeight: 'calc(var(--layout-header-height) - 16px)',
                                        maxWidth: 200,
                                    }}
                                    whileHover={{ scale: 1.05 }}
                                    transition={{ type: 'spring', stiffness: 400, damping: 15 }}
                                />
                                {branding.show_app_name && (
                                    <span className="text-lg font-semibold text-[var(--color-text-primary)] transition-colors duration-200 group-hover:text-[var(--color-primary)]">
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
                                className="rounded-[var(--radius)] p-2 text-[var(--color-text-secondary)] transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)] md:hidden cursor-pointer"
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

                {/* Mobile menu — slide down */}
                <AnimatePresence>
                    {isMobileMenuOpen && (
                        <m.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            transition={{ duration: 0.25, ease: [0.4, 0, 0.2, 1] }}
                            className="overflow-hidden border-t border-[var(--color-border)]/50 md:hidden"
                        >
                            <div className="space-y-1 px-4 py-3">
                                <NavHeaderLinks links={links} mobile />
                                <hr className="border-[var(--color-border)]/50" />
                                <Link to="/profile" className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                                    {t('nav.profile')}
                                </Link>
                                {user?.is_admin && (
                                    <a href="/admin" className="block rounded-[var(--radius)] px-3 py-2 text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                                        {t('nav.settings_admin')}
                                    </a>
                                )}
                                <button type="button" onClick={handleLogout} className="block w-full cursor-pointer rounded-[var(--radius)] px-3 py-2 text-left text-sm font-medium text-[var(--color-text-secondary)] transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]">
                                    {t('nav.logout')}
                                </button>
                            </div>
                        </m.div>
                    )}
                </AnimatePresence>
            </nav>

            {/* When the admin picks a custom background pattern, swap the
                animated constellation for it — they conflict visually. */}
            {appPattern === 'none' ? (
                <AnimatedBackground />
            ) : (
                <div
                    aria-hidden
                    className={`pointer-events-none fixed inset-0 z-0 overflow-hidden bg-pattern-${appPattern}`}
                />
            )}

            {/* Page transition wrapper. `app-page` carries the responsive
                padding driven by --layout-page-* CSS vars. flex-1 pushes
                the optional footer to the bottom of the viewport. */}
            <m.main
                key={location.pathname}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: 'easeOut' }}
                className="relative z-10 app-container app-page flex-1"
                style={{ maxWidth: 'var(--layout-container-max)' }}
            >
                <Outlet />
            </m.main>

            {footer && <AppFooter footer={footer} />}
        </div>
    );
}
