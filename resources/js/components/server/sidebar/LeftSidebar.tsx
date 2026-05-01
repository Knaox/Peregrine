import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { StatusDot } from '@/components/ui/StatusDot';
import { useCollapsedSidebar } from '@/hooks/useCollapsedSidebar';
import { NavLinks } from '@/components/server/sidebar/NavLinks';
import type { useSidebarConfig } from '@/hooks/useSidebarConfig';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

type LeftSidebarProps = ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> };

/**
 * Classic + Rail layouts.
 *
 * - Mobile (< md): collapsed by default, hamburger toggle opens a drawer.
 * - Desktop: permanent panel (224px Classic, 64px Rail).
 * - Collapse toggle (desktop only): user can temporarily switch Classic ↔ Rail
 *   via a chevron handle on the right edge — persisted in localStorage so it
 *   survives refreshes without changing the admin preset.
 */
export function LeftSidebar({ server, config }: LeftSidebarProps) {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const [mobileOpen, setMobileOpen] = useState(false);
    const { collapsed, toggle: toggleCollapsed } = useCollapsedSidebar();

    // Effective style: admin preset OR user's local collapse override.
    const effectiveStyle = collapsed ? 'compact' : config.style;
    const isRail = effectiveStyle === 'compact';

    const handleLogout = async (): Promise<void> => {
        await logout();
        navigate('/login');
    };

    const navContent = (
        <div className="flex h-full flex-col">
            {/* Back-to-dashboard */}
            <button
                type="button"
                onClick={() => navigate('/dashboard')}
                title={isRail ? t('servers.detail.back') : undefined}
                aria-label={t('servers.detail.back')}
                className={clsx(
                    'flex items-center min-h-[44px] cursor-pointer transition-all duration-150 hover:bg-[var(--surface-overlay-hover)]',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-inset',
                    isRail ? 'justify-center py-3 px-2' : 'gap-2 px-4 py-3 text-xs',
                )}
                style={{ color: 'var(--color-text-muted)' }}
            >
                <svg className="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {!isRail && t('servers.detail.back')}
            </button>

            {/* Server identity block */}
            <div className={clsx('py-4', isRail ? 'px-2' : 'px-4')} style={{ borderBottom: '1px solid var(--color-border)' }}>
                <div className={clsx('flex items-center', isRail ? 'justify-center' : 'gap-3')}>
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="h-10 w-10 object-cover"
                            style={{ borderRadius: 10 }}
                            title={isRail ? server.name : undefined}
                        />
                    ) : (
                        <div
                            className="flex h-10 w-10 items-center justify-center"
                            style={{ borderRadius: 10, background: 'var(--color-surface-hover)' }}
                            title={isRail ? server.name : undefined}
                        >
                            <span className="text-xs font-bold" style={{ color: 'var(--color-text-muted)' }}>
                                {server.egg?.name?.charAt(0) ?? '?'}
                            </span>
                        </div>
                    )}
                    {!isRail && (
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                {config.show_server_status && <StatusDot status={server.status} size="sm" />}
                                {config.show_server_name && (
                                    <p className="truncate text-sm font-semibold" style={{ color: 'var(--color-text-primary)' }}>{server.name}</p>
                                )}
                            </div>
                            <p className="truncate" style={{ fontSize: '0.7rem', color: 'var(--color-text-muted)' }}>#{server.id}</p>
                        </div>
                    )}
                </div>
            </div>

            {!isRail && (
                <div className="px-5 mt-6 mb-2">
                    <span style={{ fontSize: 10, letterSpacing: 2, textTransform: 'uppercase' as const, opacity: 0.6, color: 'var(--color-text-secondary)', fontWeight: 600 }}>
                        {t('servers.sidebar.principal')}
                    </span>
                </div>
            )}

            <nav
                role="navigation"
                aria-label={t('servers.sidebar.principal')}
                className={clsx('flex-1 overflow-y-auto space-y-1', isRail ? 'px-2 mt-3' : 'px-3')}
            >
                <NavLinks
                    entries={config.entries}
                    serverId={server.id}
                    style={effectiveStyle}
                    isTop={false}
                    onNavClick={() => setMobileOpen(false)}
                />
            </nav>

            {user && (
                <div className={clsx('mt-auto pb-3 pt-4', isRail ? 'px-2' : 'px-3')} style={{ borderTop: '1px solid var(--color-border)' }}>
                    <div className={clsx('flex items-center py-2', isRail ? 'justify-center' : 'gap-2.5 px-2')}>
                        <div
                            className={clsx('flex flex-shrink-0 items-center justify-center rounded-full font-bold text-white', isRail ? 'h-11 w-11 text-sm' : 'h-9 w-9 text-xs')}
                            title={isRail ? user.name : undefined}
                            style={{ background: 'var(--color-primary)', boxShadow: '0 0 16px var(--color-primary-glow)' }}
                        >
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        {!isRail && (
                            <>
                                <div className="min-w-0 flex-1">
                                    <p
                                        className="line-clamp-2 break-words text-xs font-medium leading-snug"
                                        title={user.name}
                                        style={{ color: 'var(--color-text-primary)' }}
                                    >
                                        {user.name}
                                    </p>
                                    <p
                                        className="truncate"
                                        title={user.email}
                                        style={{ fontSize: '0.65rem', color: 'var(--color-text-muted)' }}
                                    >
                                        {user.email}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    title={t('nav.logout')}
                                    aria-label={t('nav.logout')}
                                    className="flex h-11 w-11 items-center justify-center cursor-pointer transition-colors duration-150 hover:text-[var(--color-danger)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)]"
                                    style={{ borderRadius: 'var(--radius)', color: 'var(--color-text-muted)' }}
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </button>
                            </>
                        )}
                    </div>
                    {isRail && (
                        <button
                            type="button"
                            onClick={handleLogout}
                            title={t('nav.logout')}
                            aria-label={t('nav.logout')}
                            className="mt-2 flex min-h-[44px] w-full items-center justify-center cursor-pointer transition-colors duration-150 hover:text-[var(--color-danger)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)]"
                            style={{ borderRadius: 'var(--radius)', color: 'var(--color-text-muted)' }}
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    )}
                </div>
            )}
        </div>
    );

    return (
        <>
            {/* Mobile hamburger toggle */}
            <button
                type="button"
                onClick={() => setMobileOpen(!mobileOpen)}
                aria-label={mobileOpen ? t('servers.sidebar.close', { defaultValue: 'Close menu' }) : t('servers.sidebar.open', { defaultValue: 'Open menu' })}
                aria-expanded={mobileOpen}
                className="fixed left-3 top-3 z-40 h-11 w-11 flex items-center justify-center md:hidden cursor-pointer transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)]"
                style={{ borderRadius: 'var(--radius)', background: 'var(--color-glass)', backdropFilter: 'blur(12px)', border: '1px solid var(--color-glass-border)', color: 'var(--color-text-secondary)' }}
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    {mobileOpen
                        ? <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        : <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />}
                </svg>
            </button>

            <AnimatePresence>
                {mobileOpen && (
                    <m.div
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="fixed inset-0 z-30 backdrop-blur-sm md:hidden"
                        style={{ background: 'var(--modal-scrim)' }}
                        onClick={() => setMobileOpen(false)}
                        role="presentation"
                    />
                )}
            </AnimatePresence>

            {/* Desktop permanent sidebar — width driven by --sidebar-width-* CSS vars
                emitted by CssVariableBuilder so the studio can adjust them live.
                The `server-sidebar` class is a stable hook for app.css overrides
                (e.g. `data-sidebar-floating="true"` flips margin + radius + shadow). */}
            <aside
                className="server-sidebar themed-border-x relative flex-shrink-0 h-full hidden md:flex md:flex-col transition-[width] duration-200"
                style={{
                    width: isRail ? 'var(--sidebar-width-rail, 64px)' : 'var(--sidebar-width-classic, 224px)',
                    background: 'var(--color-glass)',
                    backdropFilter: 'blur(var(--sidebar-blur-intensity, 12px))',
                    borderRight: '1px solid var(--color-glass-border)',
                }}
            >
                {navContent}

                {/* Collapse / expand toggle — desktop only, hidden when the admin preset itself is 'compact' to avoid double-rail confusion */}
                {config.style !== 'compact' && (
                    <button
                        type="button"
                        onClick={toggleCollapsed}
                        title={collapsed ? t('servers.sidebar.expand', { defaultValue: 'Expand sidebar' }) : t('servers.sidebar.collapse', { defaultValue: 'Collapse sidebar' })}
                        aria-label={collapsed ? t('servers.sidebar.expand', { defaultValue: 'Expand sidebar' }) : t('servers.sidebar.collapse', { defaultValue: 'Collapse sidebar' })}
                        className="server-sidebar-collapse-toggle absolute -right-3 top-4 z-10 h-7 w-7 items-center justify-center rounded-full cursor-pointer transition-all duration-150 hover:scale-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] hidden md:flex"
                        style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border-hover)', boxShadow: 'var(--shadow-sm)', color: 'var(--color-text-secondary)' }}
                    >
                        <svg className={clsx('h-3.5 w-3.5 transition-transform duration-200', collapsed && 'rotate-180')} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                )}
            </aside>

            {/* Mobile drawer */}
            <AnimatePresence>
                {mobileOpen && (
                    <m.aside
                        initial={{ x: '-100%' }} animate={{ x: 0 }} exit={{ x: '-100%' }}
                        transition={{ duration: 0.3, ease: [0.4, 0, 0.2, 1] }}
                        className="server-sidebar-drawer fixed left-0 top-0 z-40 h-[100dvh] overflow-y-auto md:hidden"
                        style={{
                            width: 'min(var(--sidebar-width-mobile, 256px), 80vw)',
                            background: 'var(--color-glass)',
                            backdropFilter: 'blur(20px)',
                            borderRight: '1px solid var(--color-glass-border)',
                        }}
                    >
                        {navContent}
                    </m.aside>
                )}
            </AnimatePresence>
        </>
    );
}
