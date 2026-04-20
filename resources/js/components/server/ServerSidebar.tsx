import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { StatusDot } from '@/components/ui/StatusDot';
import { useSidebarConfig } from '@/hooks/useSidebarConfig';
import { getIcon } from '@/utils/icons';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';
import type { SidebarEntry } from '@/hooks/useSidebarConfig';

function getLinkStyle(style: string, isActive: boolean, isTop: boolean): React.CSSProperties {
    if (isTop) {
        return isActive
            ? { borderBottom: '2px solid var(--color-primary)', color: 'var(--color-primary)', background: 'transparent' }
            : { borderBottom: '2px solid transparent', color: 'var(--color-text-secondary)' };
    }
    if (style === 'compact') {
        return isActive
            ? { borderRadius: 'var(--radius)', background: 'rgba(var(--color-primary-rgb), 0.15)', color: 'var(--color-primary)', boxShadow: '0 0 14px rgba(var(--color-primary-rgb), 0.2)' }
            : { borderRadius: 'var(--radius)', color: 'var(--color-text-secondary)' };
    }
    if (style === 'pills') {
        return isActive
            ? { borderRadius: '9999px', background: 'rgba(var(--color-primary-rgb), 0.15)', color: 'var(--color-primary)', boxShadow: '0 0 12px rgba(var(--color-primary-rgb), 0.1)' }
            : { borderRadius: '9999px', color: 'var(--color-text-secondary)' };
    }
    return isActive
        ? { borderLeft: '3px solid var(--color-primary)', background: 'rgba(var(--color-primary-rgb), 0.08)', boxShadow: 'inset 3px 0 12px -4px rgba(var(--color-primary-rgb), 0.3)', color: 'var(--color-primary)', borderRadius: '0 var(--radius) var(--radius) 0' }
        : { borderLeft: '3px solid transparent', color: 'var(--color-text-secondary)', borderRadius: '0 var(--radius) var(--radius) 0' };
}

function NavLinks({ entries, serverId, style, isTop, onNavClick }: {
    entries: SidebarEntry[]; serverId: number; style: string; isTop: boolean; onNavClick?: () => void;
}) {
    const { t } = useTranslation();
    const isRail = style === 'compact' && !isTop;
    return (
        <>
            {entries.map((entry, i) => (
                <m.div
                    key={entry.id}
                    initial={{ opacity: 0, x: isTop ? 0 : -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: i * 0.04, duration: 0.25 }}
                >
                    <NavLink
                        to={`/servers/${serverId}${entry.route_suffix}`}
                        end={entry.route_suffix === ''}
                        onClick={onNavClick}
                        title={isRail ? t(entry.label_key) : undefined}
                        aria-label={isRail ? t(entry.label_key) : undefined}
                        className={({ isActive }) => clsx(
                            'flex items-center text-sm font-medium transition-all duration-150',
                            isRail ? 'justify-center p-2.5' : 'gap-2.5',
                            !isRail && (isTop ? 'px-4 py-2.5' : 'px-3 py-2.5'),
                            !isActive && (style === 'pills' ? 'hover:bg-white/[0.06]' : 'hover:bg-white/[0.04]'),
                        )}
                        style={({ isActive }) => getLinkStyle(style, isActive, isTop)}
                    >
                        {getIcon(entry.icon)}
                        {!isRail && !isTop && t(entry.label_key)}
                        {isTop && t(entry.label_key)}
                    </NavLink>
                </m.div>
            ))}
        </>
    );
}

function TopTabs({ server, config }: ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> }) {
    return (
        <nav className="flex items-center gap-1 overflow-x-auto px-2"
            style={{ borderBottom: '1px solid var(--color-border)' }}>
            <NavLinks entries={config.entries} serverId={server.id} style={config.style} isTop />
        </nav>
    );
}

function DockBar({ server, config }: ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> }) {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();

    const handleLogout = async () => { await logout(); navigate('/login'); };

    return (
        <>
            {/* Top-left back pill — the dock is at the bottom, so the back affordance lives in the corner. */}
            <div className="fixed left-3 top-3 z-40 flex items-center gap-2">
                <button type="button" onClick={() => navigate('/dashboard')}
                    title={t('servers.detail.back')}
                    aria-label={t('servers.detail.back')}
                    className="flex items-center gap-2 px-3 py-2 text-xs cursor-pointer transition-all duration-150"
                    style={{
                        borderRadius: '9999px',
                        background: 'rgba(0,0,0,0.5)',
                        backdropFilter: 'blur(14px) saturate(180%)',
                        border: '1px solid rgba(255,255,255,0.08)',
                        color: 'var(--color-text-secondary)',
                    }}>
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span className="hidden sm:inline">{t('servers.detail.back')}</span>
                </button>
                <div className="flex items-center gap-2 px-3 py-2"
                    style={{
                        borderRadius: '9999px',
                        background: 'rgba(0,0,0,0.5)',
                        backdropFilter: 'blur(14px) saturate(180%)',
                        border: '1px solid rgba(255,255,255,0.08)',
                    }}>
                    {config.show_server_status && <StatusDot status={server.status} size="sm" />}
                    <p className="truncate max-w-[180px] text-xs font-semibold" style={{ color: 'var(--color-text-primary)' }}>
                        {config.show_server_name === false ? `#${server.id}` : server.name}
                    </p>
                </div>
            </div>

            {/* Floating dock pinned to bottom-center. */}
            <m.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.4, 0, 0.2, 1] }}
                className="fixed inset-x-0 bottom-4 z-40 flex justify-center pointer-events-none px-3"
            >
                <nav
                    className="pointer-events-auto flex items-center gap-1 overflow-x-auto px-3 py-2"
                    style={{
                        borderRadius: '9999px',
                        background: 'rgba(0,0,0,0.55)',
                        backdropFilter: 'blur(18px) saturate(180%)',
                        border: '1px solid rgba(255,255,255,0.08)',
                        boxShadow: '0 16px 48px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.06)',
                    }}
                >
                    {config.entries.map((entry, i) => (
                        <m.div
                            key={entry.id}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: i * 0.03, duration: 0.25 }}
                        >
                            <NavLink
                                to={`/servers/${server.id}${entry.route_suffix}`}
                                end={entry.route_suffix === ''}
                                title={t(entry.label_key)}
                                aria-label={t(entry.label_key)}
                                className={({ isActive }) => clsx(
                                    'flex items-center justify-center p-2.5 transition-all duration-200',
                                    'hover:scale-110',
                                    isActive && 'scale-110',
                                )}
                                style={({ isActive }) => isActive
                                    ? { borderRadius: '9999px', background: 'rgba(var(--color-primary-rgb), 0.18)', color: 'var(--color-primary)', boxShadow: '0 0 18px rgba(var(--color-primary-rgb), 0.35)' }
                                    : { borderRadius: '9999px', color: 'var(--color-text-secondary)' }}
                            >
                                {getIcon(entry.icon)}
                            </NavLink>
                        </m.div>
                    ))}

                    {user && (
                        <>
                            <div className="mx-1 h-6 w-px" style={{ background: 'rgba(255,255,255,0.1)' }} />
                            <button type="button" onClick={handleLogout}
                                title={t('nav.logout')}
                                aria-label={t('nav.logout')}
                                className="flex items-center justify-center p-2.5 cursor-pointer transition-all duration-200 hover:scale-110 hover:text-[var(--color-danger)]"
                                style={{ borderRadius: '9999px', color: 'var(--color-text-muted)' }}>
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </>
                    )}
                </nav>
            </m.div>
        </>
    );
}

function LeftSidebar({ server, config }: ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> }) {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const [mobileOpen, setMobileOpen] = useState(false);
    const isRail = config.style === 'compact';

    const handleLogout = async () => { await logout(); navigate('/login'); };

    const navContent = (
        <div className="flex h-full flex-col">
            <button type="button" onClick={() => navigate('/dashboard')}
                title={isRail ? t('servers.detail.back') : undefined}
                className={clsx(
                    'flex items-center text-xs cursor-pointer transition-all duration-150 hover:bg-white/[0.04]',
                    isRail ? 'justify-center py-3' : 'gap-2 px-4 py-3',
                )}
                style={{ color: 'var(--color-text-muted)' }}>
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {!isRail && t('servers.detail.back')}
            </button>

            <div className={clsx('py-4', isRail ? 'px-2' : 'px-4')} style={{ borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                <div className={clsx('flex items-center', isRail ? 'justify-center' : 'gap-3')}>
                    {server.egg?.banner_image ? (
                        <img src={server.egg.banner_image} alt={server.egg.name} className="h-10 w-10 object-cover" style={{ borderRadius: 10 }} />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center" style={{ borderRadius: 10, background: 'var(--color-surface-hover)' }}>
                            <span className="text-xs font-bold" style={{ color: 'var(--color-text-muted)' }}>{server.egg?.name?.charAt(0) ?? '?'}</span>
                        </div>
                    )}
                    {!isRail && (
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                {config.show_server_status && <StatusDot status={server.status} size="sm" />}
                                {config.show_server_name && <p className="truncate text-sm font-semibold" style={{ color: 'var(--color-text-primary)' }}>{server.name}</p>}
                            </div>
                            <p className="truncate" style={{ fontSize: '0.7rem', color: 'var(--color-text-muted)' }}>#{server.id}</p>
                        </div>
                    )}
                </div>
            </div>

            {!isRail && (
                <div className="px-5 mt-6 mb-2">
                    <span style={{ fontSize: 10, letterSpacing: 2, textTransform: 'uppercase' as const, opacity: 0.35, color: 'var(--color-text-secondary)', fontWeight: 600 }}>
                        {t('servers.sidebar.principal')}
                    </span>
                </div>
            )}

            <nav className={clsx('flex-1 overflow-y-auto space-y-0.5', isRail ? 'px-2 mt-3' : 'px-3')}>
                <NavLinks entries={config.entries} serverId={server.id} style={config.style} isTop={false} onNavClick={() => setMobileOpen(false)} />
            </nav>

            {user && (
                <div className={clsx('mt-auto pb-3 pt-4', isRail ? 'px-2' : 'px-3')} style={{ borderTop: '1px solid rgba(255,255,255,0.06)' }}>
                    <div className={clsx('flex items-center py-2', isRail ? 'justify-center' : 'gap-3 px-2')}>
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                            title={isRail ? user.name : undefined}
                            style={{ background: 'var(--color-primary)', boxShadow: '0 0 16px var(--color-primary-glow)' }}>
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        {!isRail && (
                            <>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-xs font-medium" style={{ color: 'var(--color-text-primary)' }}>{user.name}</p>
                                    <p className="truncate" style={{ fontSize: '0.65rem', color: 'var(--color-text-muted)' }}>{user.email}</p>
                                </div>
                                <button type="button" onClick={handleLogout} title={t('nav.logout')}
                                    className="cursor-pointer transition-colors duration-150 hover:text-[var(--color-danger)]"
                                    style={{ color: 'var(--color-text-muted)' }}>
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                </button>
                            </>
                        )}
                    </div>
                    {isRail && (
                        <button type="button" onClick={handleLogout} title={t('nav.logout')}
                            className="mt-2 flex w-full items-center justify-center p-2 cursor-pointer transition-colors duration-150 hover:text-[var(--color-danger)]"
                            style={{ borderRadius: 'var(--radius)', color: 'var(--color-text-muted)' }}>
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
            <button type="button" onClick={() => setMobileOpen(!mobileOpen)}
                className="fixed left-3 top-3 z-40 p-2.5 md:hidden cursor-pointer transition-all duration-150"
                style={{ borderRadius: 'var(--radius)', background: 'rgba(0,0,0,0.6)', backdropFilter: 'blur(12px)', border: '1px solid rgba(255,255,255,0.1)', color: 'var(--color-text-secondary)' }}>
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    {mobileOpen
                        ? <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                        : <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    }
                </svg>
            </button>
            <AnimatePresence>
                {mobileOpen && <m.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.2 }} className="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm md:hidden" onClick={() => setMobileOpen(false)} role="presentation" />}
            </AnimatePresence>
            <aside className={clsx('flex-shrink-0 h-full overflow-y-auto hidden md:flex md:flex-col', isRail ? 'w-16' : 'w-56')}
                style={{ background: 'rgba(0,0,0,0.3)', backdropFilter: 'blur(12px)', borderRight: '1px solid rgba(255,255,255,0.06)' }}>
                {navContent}
            </aside>
            <AnimatePresence>
                {mobileOpen && (
                    <m.aside initial={{ x: '-100%' }} animate={{ x: 0 }} exit={{ x: '-100%' }} transition={{ duration: 0.3, ease: [0.4, 0, 0.2, 1] }}
                        className="fixed left-0 top-0 z-40 h-[100dvh] w-[min(16rem,80vw)] overflow-y-auto md:hidden"
                        style={{ background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(20px)', borderRight: '1px solid rgba(255,255,255,0.06)' }}>
                        {navContent}
                    </m.aside>
                )}
            </AnimatePresence>
        </>
    );
}

export function ServerSidebar({ server, sidebarConfig }: ServerSidebarProps) {
    const defaultConfig = useSidebarConfig();
    const config = sidebarConfig ?? defaultConfig;
    if (config.position === 'dock') return <DockBar server={server} config={config} />;
    if (config.position === 'top') return <TopTabs server={server} config={config} />;
    return <LeftSidebar server={server} config={config} />;
}
