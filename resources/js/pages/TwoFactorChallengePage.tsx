import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { ApiError } from '@/services/api';
import { TwoFactorChallengeForm } from '@/components/auth/TwoFactorChallengeForm';

/**
 * Post-password 2FA challenge page.
 *
 * The user lands here after a successful password login when their account
 * has 2FA enabled. The authStore holds the pendingChallengeId from the login
 * response; on submit we call `submitChallenge` which validates, logs the
 * user in server-side, and returns the redirect target.
 */
export function TwoFactorChallengePage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const branding = useBranding();
    const { pendingChallengeId, submitChallenge } = useAuthStore();
    const [error, setError] = useState('');

    if (pendingChallengeId === null) {
        // No pending challenge — the user landed here directly (refresh, bookmark).
        // Bounce to login.
        navigate('/login', { replace: true });
        return null;
    }

    const handle = async (code: string): Promise<void> => {
        setError('');
        try {
            const redirectTo = await submitChallenge(code);
            navigate(redirectTo, { replace: true });
        } catch (err) {
            if (err instanceof ApiError) {
                if (err.status === 410) {
                    setError(t('auth.2fa.challenge.expired'));
                    // Force re-login once the user acknowledges.
                    setTimeout(() => navigate('/login', { replace: true }), 2500);
                    return;
                }
                if (err.status === 429) {
                    setError(t('auth.2fa.challenge.expired'));
                    setTimeout(() => navigate('/login', { replace: true }), 2500);
                    return;
                }
                setError(t('auth.2fa.challenge.invalid'));
                return;
            }
            setError(t('common.error'));
        }
    };

    return (
        <div
            className="relative flex min-h-screen items-center justify-center overflow-hidden px-4"
            style={{ background: 'var(--color-background)' }}
        >
            <m.div
                initial={{ opacity: 0, y: 24 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4 }}
                className="relative z-10 w-full max-w-sm"
            >
                <div className="mb-6 text-center">
                    <img
                        src={branding.logo_url}
                        alt={branding.app_name}
                        className="mx-auto mb-4 object-contain"
                        style={{ height: branding.logo_height ?? 48, maxHeight: 64 }}
                    />
                    <h1 className="text-xl font-semibold text-[var(--color-text-primary)]">
                        {t('auth.2fa.challenge.title')}
                    </h1>
                    <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                        {t('auth.2fa.challenge.description')}
                    </p>
                </div>

                <div
                    className="rounded-[var(--radius-xl)] p-6 sm:p-8"
                    style={{
                        background: 'var(--color-glass)',
                        backdropFilter: 'blur(24px) saturate(180%)',
                        border: '1px solid var(--color-glass-border)',
                    }}
                >
                    <TwoFactorChallengeForm onSubmit={handle} error={error} />
                </div>
            </m.div>
        </div>
    );
}
