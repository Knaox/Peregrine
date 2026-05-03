import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { GlassCard } from '@/components/ui/GlassCard';
import { ProfileForm } from '@/components/profile/ProfileForm';
import { PasswordForm } from '@/components/profile/PasswordForm';

export function ProfilePage() {
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const has2fa = user?.has_two_factor === true;

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.4, 0, 0.2, 1] }}
            className="max-w-2xl mx-auto space-y-4 sm:space-y-6"
        >
            <m.h1
                initial={{ opacity: 0, x: -15 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.1, duration: 0.35 }}
                className="text-2xl font-bold text-[var(--color-text-primary)]"
            >
                {t('profile.title')}
            </m.h1>
            <m.div
                initial={{ opacity: 0, y: 15 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.15, duration: 0.4 }}
            >
                <GlassCard className="p-4 sm:p-6">
                    <ProfileForm />
                </GlassCard>
            </m.div>
            <m.div
                initial={{ opacity: 0, y: 15 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2, duration: 0.4 }}
            >
                <GlassCard className="p-4 sm:p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-base font-semibold text-[var(--color-text-primary)]">
                                {t('profile.security_card.title')}
                            </h2>
                            <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                                {has2fa
                                    ? t('profile.security_card.has_2fa')
                                    : t('profile.security_card.no_2fa')}
                            </p>
                            <div className="mt-3">
                                <span
                                    className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        has2fa
                                            ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]'
                                            : 'bg-[var(--color-warning)]/15 text-[var(--color-warning)]'
                                    }`}
                                >
                                    <svg className="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.5}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d={has2fa ? 'M5 13l4 4L19 7' : 'M12 9v4m0 4h.01M4.93 4.93l14.14 14.14'} />
                                    </svg>
                                    {has2fa ? t('auth.2fa.status.enabled') : t('auth.2fa.status.disabled')}
                                </span>
                            </div>
                        </div>
                        <Link
                            to="/settings/security"
                            className="shrink-0 rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 sm:py-2 text-sm font-semibold text-white cursor-pointer hover:bg-[var(--color-primary-hover)] whitespace-nowrap"
                        >
                            {has2fa
                                ? t('profile.security_card.manage_cta')
                                : t('profile.security_card.setup_cta')}
                        </Link>
                    </div>
                </GlassCard>
            </m.div>
            <m.div
                initial={{ opacity: 0, y: 15 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.25, duration: 0.4 }}
            >
                <GlassCard className="p-4 sm:p-6">
                    <PasswordForm />
                </GlassCard>
            </m.div>
        </m.div>
    );
}
