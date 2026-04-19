import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { GlassCard } from '@/components/ui/GlassCard';
import { ProfileForm } from '@/components/profile/ProfileForm';
import { PasswordForm } from '@/components/profile/PasswordForm';

export function ProfilePage() {
    const { t } = useTranslation();

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
                transition={{ delay: 0.25, duration: 0.4 }}
            >
                <GlassCard className="p-4 sm:p-6">
                    <PasswordForm />
                </GlassCard>
            </m.div>
        </m.div>
    );
}
