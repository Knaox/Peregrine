import { useState, useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { useProfile } from '@/hooks/useProfile';
import type { ProfileFormProps } from '@/components/profile/ProfileForm.props';

const LOCALE_OPTIONS = [
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'Francais' },
] as const;

export function ProfileForm({ onSaved }: ProfileFormProps) {
    const { t, i18n } = useTranslation();
    const { profile, isLoading, updateProfile, isUpdating, isUpdateSuccess } = useProfile();

    const [name, setName] = useState('');
    const [locale, setLocale] = useState('en');

    useEffect(() => {
        if (profile) {
            setName(profile.name);
            setLocale(profile.locale);
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
        ? name !== profile.name || locale !== profile.locale
        : false;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        void i18n.changeLanguage(locale);
        updateProfile({ name, locale });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            <h3 className="text-base font-semibold text-white">
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
                    className="text-sm font-medium text-slate-300"
                >
                    {t('profile.locale')}
                </label>
                <select
                    id="locale-select"
                    value={locale}
                    onChange={(e) => setLocale(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                >
                    {LOCALE_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
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
