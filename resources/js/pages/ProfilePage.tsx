import { useTranslation } from 'react-i18next';
import { Card } from '@/components/ui/Card';
import { ProfileForm } from '@/components/profile/ProfileForm';
import { PasswordForm } from '@/components/profile/PasswordForm';

export function ProfilePage() {
    const { t } = useTranslation();

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            <h1 className="text-2xl font-bold text-white">
                {t('profile.title')}
            </h1>
            <Card>
                <div className="p-6">
                    <ProfileForm />
                </div>
            </Card>
            <Card>
                <div className="p-6">
                    <PasswordForm />
                </div>
            </Card>
        </div>
    );
}
