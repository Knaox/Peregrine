import { useState, useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { useProfile } from '@/hooks/useProfile';
import { useThemeModeStore, type ThemeMode } from '@/stores/themeModeStore';
import type { ProfileFormProps } from '@/components/profile/ProfileForm.props';
import type { ThemeModePreference } from '@/types/User';

const LOCALE_OPTIONS = [
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'Francais' },
] as const;

export function ProfileForm({ onSaved }: ProfileFormProps) {
    const { t, i18n } = useTranslation();
    const { profile, isLoading, updateProfile, isUpdating, isUpdateSuccess } = useProfile();

    const setThemeMode = useThemeModeStore((s) => s.setMode);
    const [name, setName] = useState('');
    const [locale, setLocale] = useState('en');
    const [themeMode, setThemeModeLocal] = useState<ThemeModePreference>('auto');

    useEffect(() => {
        if (profile) {
            setName(profile.name);
            setLocale(profile.locale);
            setThemeModeLocal(profile.theme_mode ?? 'auto');
        }
    }, [profile]);

    useEffect(() => {
        if (isUpdateSuccess) {
            onSaved?.();
        }
    }, [isUpdateSuccess, onSaved]);

    if (isLoading) {
        return (
            <div className="flex justify-center py-8">
                <Spinner size="lg" />
            </div>
        );
    }

    const hasChanges = profile
        ? name !== profile.name || locale !== profile.locale || themeMode !== (profile.theme_mode ?? 'auto')
        : false;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        void i18n.changeLanguage(locale);
        setThemeMode(themeMode);
        updateProfile({ name, locale, theme_mode: themeMode });
    };

    // Live preview: flip the UI as the user clicks a mode, without waiting
    // for the save. Revert gets picked up when the profile query refetches.
    const handleModePreview = (mode: ThemeMode): void => {
        setThemeModeLocal(mode);
        setThemeMode(mode);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            <h3 className="text-base font-semibold text-[var(--color-text-primary)]">
                {t('profile.info')}
            </h3>

            {isUpdateSuccess && (
                <Alert variant="success">{t('profile.saved')}</Alert>
            )}

            <Input
                label={t('profile.name')}
                value={name}
                onChange={(e) => setName(e.target.value)}
            />

            <Input
                label={t('profile.email')}
                value={profile?.email ?? ''}
                disabled
            />

            <div className="flex flex-col gap-1.5">
                <label
                    htmlFor="locale-select"
                    className="text-sm font-medium text-[var(--color-text-secondary)]"
                >
                    {t('profile.locale')}
                </label>
                <select
                    id="locale-select"
                    value={locale}
                    onChange={(e) => setLocale(e.target.value)}
                    className="w-full px-3 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-text-primary)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-all duration-[var(--transition-base)]"
                >
                    {LOCALE_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="flex flex-col gap-1.5">
                <span className="text-sm font-medium text-[var(--color-text-secondary)]">
                    {t('profile.theme_mode.label')}
                </span>
                <div className="flex gap-2" role="radiogroup" aria-label={t('profile.theme_mode.label')}>
                    {(['light', 'auto', 'dark'] as const).map((m) => {
                        const active = themeMode === m;
                        return (
                            <button
                                key={m}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                onClick={() => handleModePreview(m)}
                                className={clsx(
                                    'flex flex-1 items-center justify-center gap-2 rounded-[var(--radius)] border px-3 py-2.5 text-sm font-medium transition-all duration-[var(--transition-base)]',
                                    active
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-glow)] text-[var(--color-primary)]'
                                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:border-[var(--color-border-hover)] hover:text-[var(--color-text-primary)]',
                                )}
                            >
                                {m === 'light' && (
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <circle cx="12" cy="12" r="4" />
                                        <path strokeLinecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                                    </svg>
                                )}
                                {m === 'auto' && (
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <circle cx="12" cy="12" r="9" />
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v18" />
                                        <path fill="currentColor" d="M12 3a9 9 0 010 18z" />
                                    </svg>
                                )}
                                {m === 'dark' && (
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z" />
                                    </svg>
                                )}
                                {t(`profile.theme_mode.${m}`)}
                            </button>
                        );
                    })}
                </div>
                <p className="text-xs text-[var(--color-text-muted)]">{t('profile.theme_mode.hint')}</p>
            </div>

            <Button
                type="submit"
                variant="primary"
                isLoading={isUpdating}
                disabled={!hasChanges}
            >
                {t('profile.save')}
            </Button>
        </form>
    );
}
