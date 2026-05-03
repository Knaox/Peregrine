import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useThemeModeStore, type ThemeMode } from '@/stores/themeModeStore';
import { useBranding } from '@/hooks/useBranding';
import { updateProfile } from '@/services/userApi';
import { getHeaderIcon } from '@/utils/headerIcons';
import type { HeaderLink } from '@/types/Branding';

interface WorkspaceRailProps {
    links: HeaderLink[];
    isAdmin: boolean;
    /** When true, render the rail in expanded form (mobile slide-in panel)
     *  with labels visible next to icons. Default false = icon-only rail. */
    expanded?: boolean;
    onNavigate?: () => void;
}

/**
 * Vertical rail content: logo top, nav icons middle, user avatar bottom
 * (with the same locale / theme / logout dropdown as UserMenu, repositioned
 * to flyout to the right of the avatar).
 *
 * The rail consumes branding.header_links to populate the icon nav and
 * appends a built-in "Dashboard" anchor at the start, plus an admin
 * shortcut at the bottom when relevant.
 */
export function WorkspaceRail({ links, isAdmin, expanded = false, onNavigate }: WorkspaceRailProps) {
    const { t, i18n } = useTranslation();
    const branding = useBranding();
    const location = useLocation();
    const navigate = useNavigate();
    const { user, logout } = useAuthStore();
    const themeMode = useThemeModeStore((s) => s.mode);
    const setThemeMode = useThemeModeStore((s) => s.setMode);
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const lang = i18n.language.split('-')[0] ?? 'en';

    const close = useCallback(() => setIsMenuOpen(false), []);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) close();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [close]);

    useEffect(() => { close(); }, [location.pathname, close]);

    const handleLocale = async (l: string) => {
        await i18n.changeLanguage(l);
        try { await updateProfile({ locale: l }); } catch { /* keep optimistic flip */ }
        close();
    };
    const handleMode = async (mode: ThemeMode) => {
        setThemeMode(mode);
        try { await updateProfile({ theme_mode: mode }); } catch { /* keep optimistic flip */ }
    };
    const handleLogout = async () => { await logout(); navigate('/login'); };

    return (
        <>
            <Link
                to="/dashboard"
                onClick={onNavigate}
                aria-label={branding.app_name}
                className={clsx(
                    'group mt-4 flex items-center gap-3 rounded-[var(--radius-md)] transition-transform hover:scale-105',
                    expanded ? 'w-full px-4 py-2' : 'h-10 w-10 justify-center',
                )}
            >
                <img src={branding.logo_url} alt="" aria-hidden className="h-7 w-7 object-contain" />
                {expanded && branding.show_app_name && (
                    <span className="text-sm font-semibold">{branding.app_name}</span>
                )}
            </Link>

            <nav className={clsx('mt-6 flex flex-1 flex-col gap-1.5', expanded ? 'w-full px-3' : 'items-center')}
                aria-label={t('nav.primary', 'Primary navigation')}
            >
                <RailItem
                    to="/dashboard"
                    icon={getHeaderIcon('home')}
                    label={t('nav.dashboard', 'Dashboard')}
                    expanded={expanded}
                    isActive={location.pathname === '/dashboard'}
                    onClick={onNavigate}
                />
                {links.map((link, idx) => {
                    if (link.url.startsWith('/')) {
                        return (
                            <RailItem
                                key={idx}
                                to={link.url}
                                icon={getHeaderIcon(link.icon)}
                                label={resolveLabel(link, lang)}
                                expanded={expanded}
                                isActive={location.pathname === link.url}
                                onClick={onNavigate}
                            />
                        );
                    }
                    return (
                        <RailExternal
                            key={idx}
                            href={link.url}
                            icon={getHeaderIcon(link.icon)}
                            label={resolveLabel(link, lang)}
                            expanded={expanded}
                            newTab={link.new_tab ?? false}
                        />
                    );
                })}
            </nav>

            <div className={clsx('mb-4 mt-auto', expanded ? 'w-full px-3' : 'flex flex-col items-center gap-2')}>
                {isAdmin && (
                    <RailExternal
                        href="/admin"
                        icon={SettingsIcon}
                        label={t('nav.settings_admin')}
                        expanded={expanded}
                        newTab={false}
                    />
                )}

                <div className="relative" ref={menuRef}>
                    <button
                        type="button"
                        onClick={() => setIsMenuOpen(!isMenuOpen)}
                        aria-label={user?.name ?? 'User'}
                        className={clsx(
                            'group flex items-center gap-3 rounded-[var(--radius-md)] transition-all hover:bg-[var(--color-surface-hover)] min-h-[44px] min-w-[44px]',
                            expanded ? 'w-full px-2 py-2' : 'h-11 w-11 justify-center',
                            isMenuOpen && 'bg-[var(--color-surface-hover)]',
                        )}
                    >
                        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--color-primary)] text-sm font-bold text-white ring-2 ring-[var(--color-primary-glow)]">
                            {(user?.name ?? '?').charAt(0).toUpperCase()}
                        </span>
                        {expanded && (
                            <span className="truncate text-sm font-medium text-[var(--color-text-primary)]">{user?.name}</span>
                        )}
                    </button>

                    <AnimatePresence>
                        {isMenuOpen && (
                            <m.div
                                initial={{ opacity: 0, x: -8 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: -8 }}
                                transition={{ duration: 0.15 }}
                                className={clsx(
                                    'absolute z-50 w-56 max-w-[calc(100vw-2rem)] overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] py-1 shadow-[var(--shadow-lg)] backdrop-blur-xl',
                                    expanded ? 'bottom-full mb-2 left-0' : 'bottom-0 left-full ml-2',
                                )}
                                role="menu"
                            >
                                <Link to="/profile" onClick={close} className="block px-4 py-2 text-sm hover:bg-[var(--color-surface-hover)]">
                                    {t('nav.profile')}
                                </Link>
                                <div className="flex items-center justify-between px-4 py-2">
                                    <span className="text-xs text-[var(--color-text-muted)]">{t('profile.locale')}</span>
                                    <div className="flex gap-1">
                                        {['en', 'fr'].map((l) => (
                                            <button
                                                key={l}
                                                type="button"
                                                onClick={() => { void handleLocale(l); }}
                                                className={clsx(
                                                    'rounded px-2 py-0.5 text-xs font-medium',
                                                    i18n.language.startsWith(l)
                                                        ? 'bg-[var(--color-primary)] text-white'
                                                        : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                                )}
                                            >
                                                {l.toUpperCase()}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <div className="flex items-center justify-between px-4 py-2">
                                    <span className="text-xs text-[var(--color-text-muted)]">{t('profile.theme_mode.label')}</span>
                                    <div className="flex gap-1">
                                        {(['light', 'auto', 'dark'] as const).map((m) => (
                                            <button
                                                key={m}
                                                type="button"
                                                aria-label={t(`profile.theme_mode.${m}`)}
                                                onClick={() => { void handleMode(m); }}
                                                className={clsx(
                                                    'flex h-6 w-6 items-center justify-center rounded',
                                                    themeMode === m ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-text-secondary)]',
                                                )}
                                            >
                                                {m === 'light' ? '☀' : m === 'auto' ? '◐' : '☾'}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <hr className="my-1 border-[var(--color-border)]" />
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    className="block w-full px-4 py-2 text-left text-sm text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)] cursor-pointer"
                                >
                                    {t('nav.logout')}
                                </button>
                            </m.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </>
    );
}

interface RailItemProps {
    to: string;
    icon: React.ReactNode;
    label: string;
    expanded: boolean;
    isActive: boolean;
    onClick?: () => void;
}

function RailItem({ to, icon, label, expanded, isActive, onClick }: RailItemProps) {
    return (
        <Link
            to={to}
            onClick={onClick}
            aria-label={label}
            title={expanded ? undefined : label}
            className={clsx(
                'group relative flex items-center gap-3 rounded-[var(--radius-md)] transition-all',
                expanded ? 'w-full px-3 py-2' : 'h-10 w-10 justify-center',
                isActive
                    ? 'bg-[var(--color-primary-glow)]/15 text-[var(--color-primary)]'
                    : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
            )}
        >
            <span className="flex h-5 w-5 items-center justify-center">{icon}</span>
            {expanded && <span className="truncate text-sm font-medium">{label}</span>}
            {isActive && !expanded && (
                <span aria-hidden className="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-r-full bg-[var(--color-primary)]" />
            )}
        </Link>
    );
}

function RailExternal({ href, icon, label, expanded, newTab }: { href: string; icon: React.ReactNode; label: string; expanded: boolean; newTab: boolean }) {
    return (
        <a
            href={href}
            target={newTab ? '_blank' : '_self'}
            rel={newTab ? 'noopener noreferrer' : undefined}
            aria-label={label}
            title={expanded ? undefined : label}
            className={clsx(
                'group flex items-center gap-3 rounded-[var(--radius-md)] text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]',
                expanded ? 'w-full px-3 py-2' : 'h-10 w-10 justify-center',
            )}
        >
            <span className="flex h-5 w-5 items-center justify-center">{icon}</span>
            {expanded && <span className="truncate text-sm font-medium">{label}</span>}
        </a>
    );
}

function resolveLabel(link: HeaderLink, lang: string): string {
    const langKey = `label_${lang}` as keyof HeaderLink;
    const translated = link[langKey];
    if (typeof translated === 'string' && translated) return translated;
    return link.label;
}

const SettingsIcon = (
    <svg className="h-full w-full" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.6}>
        <circle cx="12" cy="12" r="3" />
        <path strokeLinecap="round" strokeLinejoin="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" />
    </svg>
);
