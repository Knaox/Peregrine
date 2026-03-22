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
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="max-w-2xl mx-auto space-y-6"
        >
            <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
                {t('profile.title')}
            </h1>
            <GlassCard className="p-6">
                <ProfileForm />
            </GlassCard>
            <GlassCard className="p-6">
                <PasswordForm />
            </GlassCard>
        </m.div>
    );
}
