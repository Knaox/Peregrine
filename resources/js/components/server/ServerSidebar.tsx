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
    if (style === 'pills') {
        return isActive
            ? { borderRadius: '9999px', background: 'rgba(var(--color-primary-rgb), 0.15)', color: 'var(--color-primary)' }
            : { borderRadius: '9999px', color: 'var(--color-text-secondary)' };
    }
    // default + compact
    return isActive
        ? { borderLeft: '3px solid var(--color-primary)', background: 'rgba(var(--color-primary-rgb), 0.1)', boxShadow: 'inset 3px 0 12px -4px rgba(var(--color-primary-rgb), 0.3)', color: 'var(--color-primary)', borderRadius: '0 var(--radius) var(--radius) 0' }
        : { borderLeft: '3px solid transparent', color: 'var(--color-text-secondary)', borderRadius: '0 var(--radius) var(--radius) 0' };
}

function NavLinks({ entries, serverId, style, isTop, onNavClick }: {
    entries: SidebarEntry[];
    serverId: number;
    style: string;
    isTop: boolean;
    onNavClick?: () => void;
}) {
    const { t } = useTranslation();
    return (
        <>
            {entries.map((entry) => (
                <NavLink
                    key={entry.id}
                    to={`/servers/${serverId}${entry.route_suffix}`}
                    end={entry.route_suffix === ''}
                    onClick={onNavClick}
                    className={({ isActive }) => clsx(
                        'flex items-center gap-2.5 text-sm font-medium transition-all duration-150',
                        isTop ? 'px-4 py-2.5' : 'px-3 py-2.5',
                        !isActive && (style === 'pills' ? 'hover:bg-white/[0.06]' : 'hover:bg-white/[0.04]'),
                    )}
                    style={({ isActive }) => getLinkStyle(style, isActive, isTop)}
                >
                    {getIcon(entry.icon)}
                    {style !== 'compact' && t(entry.label_key)}
                </NavLink>
            ))}
        </>
    );
}

/* ---- TOP TABS MODE ---- */
function TopTabs({ server, config }: ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> }) {
    return (
        <nav
            className="flex items-center gap-1 overflow-x-auto px-2"
            style={{ borderBottom: '1px solid var(--color-border)' }}
        >
            <NavLinks
                entries={config.entries}
                serverId={server.id}
                style={config.style}
                isTop
            />
        </nav>
    );
}

/* ---- LEFT SIDEBAR MODE ---- */
function LeftSidebar({ server, config }: ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> }) {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const [mobileOpen, setMobileOpen] = useState(false);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const navContent = (
        <div className="flex h-full flex-col">
            {/* Back to dashboard */}
            <button
                type="button"
                onClick={() => navigate('/dashboard')}
                className="flex items-center gap-2 px-4 py-3 text-xs transition-all duration-150 hover:bg-white/[0.04]"
                style={{ color: 'var(--color-text-muted)' }}
            >
                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {t('servers.detail.back')}
            </button>

            {/* Server header */}
            <div className="px-4 py-4" style={{ borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
                <div className="flex items-center gap-3">
                    {server.egg?.banner_image ? (
                        <img src={server.egg.banner_image} alt={server.egg.name} className="h-10 w-10 object-cover" style={{ borderRadius: 10 }} />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center" style={{ borderRadius: 10, background: 'var(--color-surface-hover)' }}>
                            <span className="text-xs font-bold" style={{ color: 'var(--color-text-muted)' }}>{server.egg?.name?.charAt(0) ?? '?'}</span>
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            {config.show_server_status && <StatusDot status={server.status} size="sm" />}
                            {config.show_server_name && (
                                <p className="truncate text-sm font-semibold" style={{ color: 'var(--color-text-primary)' }}>{server.name}</p>
                            )}
                        </div>
                        <p className="truncate" style={{ fontSize: '0.7rem', color: 'var(--color-text-muted)' }}>#{server.id}</p>
                    </div>
                </div>
            </div>

            {/* Section label */}
            <div className="px-5 mt-6 mb-2">
                <span style={{ fontSize: 10, letterSpacing: 2, textTransform: 'uppercase' as const, opacity: 0.35, color: 'var(--color-text-secondary)', fontWeight: 600 }}>
                    {t('servers.sidebar.principal')}
                </span>
            </div>

            {/* Nav links */}
            <nav className="flex-1 overflow-y-auto px-3 space-y-0.5">
                <NavLinks entries={config.entries} serverId={server.id} style={config.style} isTop={false} onNavClick={() => setMobileOpen(false)} />
            </nav>

            {/* User info */}
            {user && (
                <div className="mt-auto px-3 pb-3 pt-4" style={{ borderTop: '1px solid rgba(255,255,255,0.06)' }}>
                    <div className="flex items-center gap-3 px-2 py-2">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style={{ background: 'var(--color-primary)', boxShadow: '0 0 12px var(--color-primary-glow)' }}>
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-medium" style={{ color: 'var(--color-text-primary)' }}>{user.name}</p>
                            <p className="truncate" style={{ fontSize: '0.65rem', color: 'var(--color-text-muted)' }}>{user.email}</p>
                        </div>
                        <button type="button" onClick={handleLogout} title={t('nav.logout')} className="transition-colors duration-150" style={{ color: 'var(--color-text-muted)' }}>
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );

    return (
        <>
            {/* Mobile toggle */}
            <button type="button" onClick={() => setMobileOpen(!mobileOpen)} className="fixed left-4 top-4 z-40 p-2 md:hidden transition-all duration-150" style={{ borderRadius: 'var(--radius)', background: 'rgba(0,0,0,0.4)', backdropFilter: 'blur(12px)', border: '1px solid rgba(255,255,255,0.06)', color: 'var(--color-text-secondary)' }}>
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <AnimatePresence>
                {mobileOpen && <m.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.2 }} className="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm md:hidden" onClick={() => setMobileOpen(false)} role="presentation" />}
            </AnimatePresence>
            <aside className="w-56 flex-shrink-0 h-full overflow-y-auto hidden md:flex md:flex-col" style={{ background: 'rgba(0,0,0,0.3)', borderRight: '1px solid rgba(255,255,255,0.06)' }}>
                {navContent}
            </aside>
            <AnimatePresence>
                {mobileOpen && (
                    <m.aside initial={{ x: '-100%' }} animate={{ x: 0 }} exit={{ x: '-100%' }} transition={{ duration: 0.25, ease: 'easeOut' }} className="fixed left-0 top-0 z-40 h-screen w-56 overflow-y-auto md:hidden" style={{ background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(16px)', borderRight: '1px solid rgba(255,255,255,0.06)' }}>
                        {navContent}
                    </m.aside>
                )}
            </AnimatePresence>
        </>
    );
}

/* ---- MAIN EXPORT ---- */
export function ServerSidebar({ server }: ServerSidebarProps) {
    const config = useSidebarConfig();

    if (config.position === 'top') {
        return <TopTabs server={server} config={config} />;
    }

    return <LeftSidebar server={server} config={config} />;
}
