import { useState } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { StatusDot } from '@/components/ui/StatusDot';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

const icons: Record<string, React.ReactNode> = {
    home: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
        </svg>
    ),
    terminal: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
    ),
    folder: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
        </svg>
    ),
    key: (
        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg>
    ),
};

interface SectionConfig {
    label: string;
    links: { to: string; label: string; icon: string; end: boolean }[];
}

export function ServerSidebar({ server }: ServerSidebarProps) {
    const { t } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const [mobileOpen, setMobileOpen] = useState(false);

    const sections: SectionConfig[] = [
        {
            label: t('servers.sidebar.principal'),
            links: [
                { to: `/servers/${server.id}`, label: t('servers.detail.overview'), icon: 'home', end: true },
                { to: `/servers/${server.id}/console`, label: t('servers.detail.console'), icon: 'terminal', end: false },
                { to: `/servers/${server.id}/files`, label: t('servers.detail.files'), icon: 'folder', end: false },
            ],
        },
        {
            label: t('servers.sidebar.management'),
            links: [
                { to: `/servers/${server.id}/sftp`, label: t('servers.detail.sftp'), icon: 'key', end: false },
            ],
        },
    ];

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    const navContent = (
        <div className="flex h-full flex-col">
            {/* Server header */}
            <div className="border-b border-[var(--color-glass-border)] p-4">
                <div className="flex items-center gap-3">
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="h-10 w-10 rounded-[var(--radius)] object-cover"
                        />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center rounded-[var(--radius)] bg-[var(--color-surface-hover)]">
                            <span className="text-xs font-bold text-[var(--color-text-muted)]">
                                {server.egg?.name?.charAt(0) ?? '?'}
                            </span>
                        </div>
                    )}
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <StatusDot status={server.status} size="sm" />
                            <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">
                                {server.name}
                            </p>
                        </div>
                        <p className="truncate text-[0.7rem] text-[var(--color-text-muted)]">
                            #{server.id}
                        </p>
                    </div>
                </div>
            </div>

            {/* Nav sections */}
            <nav className="flex-1 overflow-y-auto p-3 space-y-4">
                {sections.map((section) => (
                    <div key={section.label}>
                        <div className="flex items-center gap-2 px-3 mb-2">
                            <span className="text-[0.65rem] uppercase tracking-[0.15em] text-[var(--color-text-muted)] font-semibold">
                                {section.label}
                            </span>
                            <div className="h-px flex-1 bg-[var(--color-border)]" />
                        </div>
                        <div className="flex flex-col gap-0.5">
                            {section.links.map((link) => (
                                <NavLink
                                    key={link.to}
                                    to={link.to}
                                    end={link.end}
                                    onClick={() => setMobileOpen(false)}
                                    className={({ isActive }) => clsx(
                                        'flex items-center gap-3 rounded-[var(--radius)] px-3 py-2 text-sm font-medium',
                                        'transition-all duration-[var(--transition-base)]',
                                        isActive
                                            ? 'border-l-[3px] border-[var(--color-primary)] bg-[var(--color-primary)]/5 text-[var(--color-primary)]'
                                            : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]',
                                    )}
                                >
                                    {icons[link.icon]}
                                    {link.label}
                                </NavLink>
                            ))}
                        </div>
                    </div>
                ))}
            </nav>

            {/* Bottom: user info */}
            {user && (
                <div className="border-t border-[var(--color-glass-border)] p-3">
                    <div className="flex items-center gap-3 rounded-[var(--radius)] px-2 py-2">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-[var(--color-primary)] text-xs font-bold text-white ring-2 ring-[var(--color-primary-glow)]">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-medium text-[var(--color-text-primary)]">
                                {user.name}
                            </p>
                            <p className="truncate text-[0.65rem] text-[var(--color-text-muted)]">
                                {user.email}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={handleLogout}
                            title={t('nav.logout')}
                            className="text-[var(--color-text-muted)] transition-colors duration-[var(--transition-fast)] hover:text-[var(--color-danger)]"
                        >
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
            <button
                type="button"
                onClick={() => setMobileOpen(!mobileOpen)}
                className={clsx(
                    'fixed left-4 top-20 z-40 rounded-[var(--radius)]',
                    'bg-[var(--color-glass)] backdrop-blur-xl border border-[var(--color-glass-border)]',
                    'p-2 text-[var(--color-text-secondary)] md:hidden',
                    'transition-all duration-[var(--transition-base)]',
                    'hover:bg-[var(--color-surface-hover)]',
                )}
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {/* Mobile overlay */}
            <AnimatePresence>
                {mobileOpen && (
                    <m.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm md:hidden"
                        onClick={() => setMobileOpen(false)}
                        role="presentation"
                    />
                )}
            </AnimatePresence>

            {/* Sidebar */}
            <aside
                className={clsx(
                    'fixed left-0 top-16 z-30 h-[calc(100vh-4rem)] w-56',
                    'backdrop-blur-xl bg-[var(--color-glass)] border-r border-[var(--color-glass-border)]',
                    'transition-transform duration-[var(--transition-smooth)]',
                    'md:static md:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                {navContent}
            </aside>
        </>
    );
}
