import { useState } from 'react';
import { Outlet, Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useBranding } from '@/hooks/useBranding';
import { useResolvedTheme } from '@/hooks/useResolvedTheme';
import { useAuthStore } from '@/stores/authStore';
import { AnimatedBackground } from '@/components/AnimatedBackground';
import { AppFooter } from '@/components/AppFooter';
import { WorkspaceRail } from '@/components/layout/WorkspaceRail';

/**
 * Alternative app shell — left vertical rail instead of a top nav. The
 * rail (72 px wide) stacks: small logo at the top, nav icons in the
 * middle (built from `branding.header_links` + an admin link when
 * relevant), and the UserMenu trigger at the bottom. The main content
 * spans the remaining width.
 *
 * Toggled via `theme.data.app.shell_variant === 'workspace'`. Lives
 * side-by-side with AppLayout (which keeps the legacy top-nav) so the
 * admin can flip between the two without breaking existing screens.
 */
export function WorkspaceShell() {
    const { t } = useTranslation();
    const branding = useBranding();
    const location = useLocation();
    const { user } = useAuthStore();
    const theme = useResolvedTheme();
    const footer = theme?.data.footer;
    const appPattern = theme?.data.app?.background_pattern ?? 'none';
    const [isMobileNavOpen, setIsMobileNavOpen] = useState(false);

    const links = branding.header_links ?? [];
    // Rail width is admin-tunable via /theme-studio (60..120 px). The
    // default 72 matches the original hardcoded width — existing
    // workspace installs see no shift after the setting was introduced.
    const railWidth = theme?.data.app?.rail_width ?? 72;

    return (
        <div className="relative flex min-h-screen bg-[var(--color-background)] text-[var(--color-text-primary)]">
            {appPattern === 'none' ? (
                <AnimatedBackground />
            ) : (
                <div
                    aria-hidden
                    className={`pointer-events-none fixed inset-0 z-0 overflow-hidden bg-pattern-${appPattern}`}
                />
            )}

            {/* Desktop rail — fixed left, full height, glass surface */}
            <aside
                className="workspace-rail hidden md:flex fixed inset-y-0 left-0 z-40 flex-col items-center border-r border-[var(--color-border)]/60"
                style={{
                    width: `${railWidth}px`,
                    background: 'var(--color-glass)',
                    backdropFilter: 'var(--glass-blur)',
                    boxShadow: 'var(--glass-highlight), var(--shadow-sm)',
                }}
                aria-label={t('nav.primary', 'Primary navigation')}
            >
                <WorkspaceRail links={links} isAdmin={user?.is_admin ?? false} />
            </aside>

            {/* Mobile top bar — small, brand + burger. The full nav slides in. */}
            <div className="md:hidden fixed top-0 inset-x-0 z-40 flex items-center justify-between border-b border-[var(--color-border)]/60 px-4 py-3"
                style={{ background: 'var(--color-glass)', backdropFilter: 'var(--glass-blur)', paddingTop: 'calc(0.75rem + env(safe-area-inset-top, 0px))' }}
            >
                <Link to="/dashboard" className="flex items-center gap-2">
                    <img src={branding.logo_url} alt={branding.app_name} className="h-7 w-auto" />
                    {branding.show_app_name && (
                        <span className="text-sm font-semibold">{branding.app_name}</span>
                    )}
                </Link>
                <button
                    type="button"
                    onClick={() => setIsMobileNavOpen(!isMobileNavOpen)}
                    aria-label={isMobileNavOpen ? t('nav.close', 'Close menu') : t('nav.open', 'Open menu')}
                    className="rounded-[var(--radius)] p-2 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] cursor-pointer"
                >
                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        {isMobileNavOpen ? (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        ) : (
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        )}
                    </svg>
                </button>
            </div>

            <AnimatePresence>
                {isMobileNavOpen && (
                    <>
                        <m.div
                            key="ws-mobile-scrim"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 0.15 }}
                            className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm md:hidden"
                            onClick={() => setIsMobileNavOpen(false)}
                            aria-hidden
                        />
                        <m.aside
                            key="ws-mobile-panel"
                            initial={{ x: '-100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '-100%' }}
                            transition={{ type: 'spring', stiffness: 320, damping: 32 }}
                            className="fixed inset-y-0 left-0 z-50 flex w-[min(85vw,288px)] flex-col border-r border-[var(--color-border)] bg-[var(--color-surface)] md:hidden"
                            role="dialog"
                            aria-label={t('nav.primary', 'Primary navigation')}
                        >
                            <WorkspaceRail
                                links={links}
                                isAdmin={user?.is_admin ?? false}
                                expanded
                                onNavigate={() => setIsMobileNavOpen(false)}
                            />
                        </m.aside>
                    </>
                )}
            </AnimatePresence>

            {/* Inline style block — applies rail-aware padding-left on
                desktop without going through a CSS variable. Switching the
                rail width in the studio updates this on the next render. */}
            <style>{`@media (min-width: 768px) { .workspace-content { padding-left: ${railWidth}px; } }`}</style>

            {/* Content shifted right by the rail width on desktop */}
            <div className="workspace-content relative z-10 flex flex-1 flex-col">
                <m.main
                    key={location.pathname}
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.3, ease: 'easeOut' }}
                    className="app-page flex-1 mx-auto w-full pt-14 md:pt-0"
                    style={{ maxWidth: 'var(--layout-container-max)' }}
                >
                    <Outlet />
                </m.main>

                {footer && <AppFooter footer={footer} />}
            </div>
        </div>
    );
}
