import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useThemeModeStore, type ThemeMode } from '@/stores/themeModeStore';
import { updateProfile } from '@/services/userApi';

interface SidebarUserMenuProps {
    /** Menu placement — controls where the popover anchors. */
    align?: 'top' | 'bottom';
}

/**
 * Compact avatar-trigger user menu used in the Dock and TopTabs layouts where
 * there's no room for the full user pill.
 *
 * Mirrors the dashboard UserMenu so that the same affordances are available
 * from a server page: Profile, Settings (admin), Language toggle, Theme mode
 * toggle, and Logout. Optimistic UI for locale/theme changes — UI updates
 * instantly, the backend persist follows.
 */
export function SidebarUserMenu({ align = 'bottom' }: SidebarUserMenuProps) {
    const { t, i18n } = useTranslation();
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();
    const themeMode = useThemeModeStore((s) => s.mode);
    const setThemeMode = useThemeModeStore((s) => s.setMode);
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    const close = useCallback(() => setOpen(false), []);

    useEffect(() => {
        const handler = (e: MouseEvent): void => {
            if (ref.current && !ref.current.contains(e.target as Node)) close();
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, [close]);

    if (!user) return null;

    const handleLogout = async (): Promise<void> => {
        close();
        await logout();
        navigate('/login');
    };

    const handleModeChange = async (mode: ThemeMode): Promise<void> => {
        setThemeMode(mode);
        try {
            await updateProfile({ theme_mode: mode });
        } catch {
            // Keep local change even if save fails.
        }
    };

    const handleLocaleChange = async (lang: string): Promise<void> => {
        await i18n.changeLanguage(lang);
        try {
            await updateProfile({ locale: lang });
        } catch {
            // Same fallback policy: keep local change.
        }
    };

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                title={user.name}
                aria-label={t('nav.user_menu', { defaultValue: 'User menu' })}
                aria-expanded={open}
                aria-haspopup="menu"
                className={clsx(
                    'flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold text-white cursor-pointer transition-all duration-150 hover:scale-105',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                )}
                style={{ background: 'var(--color-primary)', boxShadow: '0 0 16px var(--color-primary-glow)' }}
            >
                {user.name.charAt(0).toUpperCase()}
            </button>

            <AnimatePresence>
                {open && (
                    <m.div
                        role="menu"
                        initial={{ opacity: 0, y: align === 'top' ? 8 : -8, scale: 0.96 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: align === 'top' ? 8 : -8, scale: 0.96 }}
                        transition={{ duration: 0.18, ease: [0.4, 0, 0.2, 1] }}
                        className={clsx(
                            'absolute right-0 w-60 overflow-hidden rounded-[var(--radius)] py-1 z-50',
                            align === 'top' ? 'bottom-full mb-2' : 'top-full mt-2',
                        )}
                        style={{
                            background: 'var(--color-glass)',
                            backdropFilter: 'blur(16px)',
                            border: '1px solid var(--color-glass-border)',
                            boxShadow: 'var(--shadow-lg)',
                        }}
                    >
                        <div className="px-3 py-2">
                            <p className="truncate text-xs font-medium" style={{ color: 'var(--color-text-primary)' }}>{user.name}</p>
                            <p className="truncate" style={{ fontSize: '0.65rem', color: 'var(--color-text-muted)' }}>{user.email}</p>
                        </div>
                        <hr className="border-[var(--color-border)]" />

                        <Link
                            to="/profile"
                            onClick={close}
                            role="menuitem"
                            className="block px-3 py-2 text-sm text-[var(--color-text-secondary)] hover:bg-[var(--surface-overlay-hover)] hover:text-[var(--color-text-primary)]"
                        >
                            {t('nav.profile')}
                        </Link>

                        {user.is_admin && (
                            <a
                                href="/admin"
                                role="menuitem"
                                className="block px-3 py-2 text-sm text-[var(--color-text-secondary)] hover:bg-[var(--surface-overlay-hover)] hover:text-[var(--color-text-primary)]"
                            >
                                {t('nav.settings_admin')}
                            </a>
                        )}

                        <div className="flex items-center justify-between px-3 py-2">
                            <span className="text-xs text-[var(--color-text-muted)]">{t('profile.locale')}</span>
                            <div className="flex gap-1">
                                {['en', 'fr'].map((lang) => (
                                    <button
                                        key={lang}
                                        type="button"
                                        onClick={() => { void handleLocaleChange(lang); }}
                                        className={clsx(
                                            'rounded px-2 py-0.5 text-xs font-medium transition-colors duration-150',
                                            i18n.language.startsWith(lang)
                                                ? 'bg-[var(--color-primary)] text-white'
                                                : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                        )}
                                    >
                                        {lang.toUpperCase()}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="flex items-center justify-between px-3 py-2">
                            <span className="text-xs text-[var(--color-text-muted)]">{t('profile.theme_mode.label')}</span>
                            <div className="flex gap-1" role="radiogroup" aria-label={t('profile.theme_mode.label')}>
                                {(['light', 'auto', 'dark'] as const).map((mode) => {
                                    const active = themeMode === mode;
                                    const labelKey = `profile.theme_mode.${mode}` as const;
                                    return (
                                        <button
                                            key={mode}
                                            type="button"
                                            role="radio"
                                            aria-checked={active}
                                            aria-label={t(labelKey)}
                                            title={t(labelKey)}
                                            onClick={() => { void handleModeChange(mode); }}
                                            className={clsx(
                                                'flex h-6 w-6 items-center justify-center rounded transition-colors duration-150',
                                                active
                                                    ? 'bg-[var(--color-primary)] text-white'
                                                    : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]',
                                            )}
                                        >
                                            {mode === 'light' && (
                                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <circle cx="12" cy="12" r="4" />
                                                    <path strokeLinecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                                                </svg>
                                            )}
                                            {mode === 'auto' && (
                                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <circle cx="12" cy="12" r="9" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v18" />
                                                    <path fill="currentColor" d="M12 3a9 9 0 010 18z" />
                                                </svg>
                                            )}
                                            {mode === 'dark' && (
                                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z" />
                                                </svg>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <hr className="my-1 border-[var(--color-border)]" />
                        <button
                            type="button"
                            onClick={handleLogout}
                            role="menuitem"
                            className="block w-full px-3 py-2 text-left text-sm text-[var(--color-text-secondary)] hover:bg-[var(--surface-overlay-hover)] hover:text-[var(--color-danger)]"
                        >
                            {t('nav.logout')}
                        </button>
                    </m.div>
                )}
            </AnimatePresence>
        </div>
    );
}
