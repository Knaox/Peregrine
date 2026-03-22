import { useState, useEffect, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { request } from '@/services/http';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Alert } from '@/components/ui/Alert';
import { Spinner } from '@/components/ui/Spinner';
import { useProfile } from '@/hooks/useProfile';

interface AuthModeResponse {
    mode: string;
}

function useAuthMode() {
    return useQuery({
        queryKey: ['settings', 'auth-mode'],
        queryFn: () => request<AuthModeResponse>('/api/settings/auth-mode'),
        staleTime: 300_000,
    });
}

export function PasswordForm() {
    const { t } = useTranslation();
    const { changePassword, isChangingPassword, isPasswordChanged, passwordError } = useProfile();
    const { data: authMode, isLoading: isAuthModeLoading } = useAuthMode();

    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [mismatch, setMismatch] = useState(false);

    useEffect(() => {
        if (isPasswordChanged) {
            setCurrentPassword('');
            setNewPassword('');
            setConfirmPassword('');
        }
    }, [isPasswordChanged]);

    if (isAuthModeLoading) {
        return (
            <div className="flex justify-center py-8">
                <Spinner size="md" />
            </div>
        );
    }

    if (authMode?.mode !== 'local') {
        return null;
    }

    const canSubmit = currentPassword.length > 0
        && newPassword.length > 0
        && confirmPassword.length > 0;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setMismatch(false);

        if (newPassword !== confirmPassword) {
            setMismatch(true);
            return;
        }

        changePassword({
            current_password: currentPassword,
            password: newPassword,
            password_confirmation: confirmPassword,
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-5">
            <h3 className="text-base font-semibold text-white">
                {t('profile.password.title')}
            </h3>

            {isPasswordChanged && (
                <Alert variant="success">{t('profile.password.changed')}</Alert>
            )}
            {passwordError && (
                <Alert variant="error">
                    {t('profile.password.error')}
                </Alert>
            )}
            {mismatch && (
                <Alert variant="error">
                    {t('profile.password.mismatch')}
                </Alert>
            )}

            <Input
                type="password"
                label={t('profile.password.current')}
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
            />
            <Input
                type="password"
                label={t('profile.password.new')}
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
            />
            <Input
                type="password"
                label={t('profile.password.confirm')}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
            />

            <Button
                type="submit"
                variant="primary"
                isLoading={isChangingPassword}
                disabled={!canSubmit}
            >
                {t('profile.password.title')}
            </Button>
        </form>
    );
}
