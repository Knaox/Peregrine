import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { ApiError } from '@/services/api';
import { twoFactorChallenge } from '@/services/authApi';
import { TwoFactorChallengeForm } from '@/components/auth/TwoFactorChallengeForm';

/**
 * 2FA challenge page — reached after either a password login (authStore holds
 * the pending challenge id) or an OAuth callback (backend redirects here with
 * ?id=... in the URL). We prefer the URL token when present so the OAuth
 * round-trip works even though the store is empty on a fresh page load.
 */
export function TwoFactorChallengePage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const branding = useBranding();
    const [searchParams] = useSearchParams();
    const { pendingChallengeId, submitChallenge } = useAuthStore();
    const [error, setError] = useState('');

    const challengeId = useMemo(
        () => searchParams.get('id') ?? pendingChallengeId,
        [searchParams, pendingChallengeId],
    );

    useEffect(() => {
        if (challengeId === null) {
            navigate('/login', { replace: true });
        }
    }, [challengeId, navigate]);

    if (challengeId === null) {
        return null;
    }

    const handle = async (code: string): Promise<void> => {
        setError('');
        try {
            // When the id came from the URL, authStore doesn't hold it — call
            // the endpoint directly and reload the app so the new session is
            // picked up by every hook on the next mount.
            if (pendingChallengeId === null) {
                await twoFactorChallenge(challengeId, code);
                window.location.href = '/dashboard';
                return;
            }
            const redirectTo = await submitChallenge(code);
            navigate(redirectTo, { replace: true });
        } catch (err) {
            if (err instanceof ApiError) {
                if (err.status === 410) {
                    setError(t('auth.2fa.challenge.expired'));
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
