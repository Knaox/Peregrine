import { useState } from 'react';
import { NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { ServerStatusBadge } from '@/components/server/ServerStatusBadge';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

const linkBase = 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors';
const linkActive = 'bg-slate-700 text-orange-500';
const linkInactive = 'text-slate-400 hover:bg-slate-700/50';

export function ServerSidebar({ server }: ServerSidebarProps) {
    const { t } = useTranslation();
    const [mobileOpen, setMobileOpen] = useState(false);

    const links = [
        { to: `/servers/${server.id}`, label: t('servers.detail.overview'), icon: 'home', end: true },
        { to: `/servers/${server.id}/console`, label: t('servers.detail.console'), icon: 'terminal', end: false },
        { to: `/servers/${server.id}/files`, label: t('servers.detail.files'), icon: 'folder', end: false },
        { to: `/servers/${server.id}/sftp`, label: t('servers.detail.sftp'), icon: 'key', end: false },
    ];

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

    const nav = (
        <nav className="flex flex-col gap-1 p-3">
            {links.map((link) => (
                <NavLink
                    key={link.to}
                    to={link.to}
                    end={link.end}
                    onClick={() => setMobileOpen(false)}
                    className={({ isActive }) => clsx(linkBase, isActive ? linkActive : linkInactive)}
                >
                    {icons[link.icon]}
                    {link.label}
                </NavLink>
            ))}
        </nav>
    );

    return (
        <>
            {/* Mobile toggle */}
            <button
                type="button"
                onClick={() => setMobileOpen(!mobileOpen)}
                className="fixed left-4 top-20 z-40 rounded-lg bg-slate-800 p-2 text-slate-300 md:hidden"
            >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {/* Mobile overlay */}
            {mobileOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/50 md:hidden"
                    onClick={() => setMobileOpen(false)}
                    role="presentation"
                />
            )}

            {/* Sidebar */}
            <aside
                className={clsx(
                    'fixed left-0 top-16 z-30 h-[calc(100vh-4rem)] w-56 border-r border-slate-700 bg-slate-800 transition-transform md:static md:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="border-b border-slate-700 p-4">
                    <p className="truncate text-sm font-semibold text-white">{server.name}</p>
                    <div className="mt-1">
                        <ServerStatusBadge status={server.status} />
                    </div>
                </div>
                {nav}
            </aside>
        </>
    );
}
