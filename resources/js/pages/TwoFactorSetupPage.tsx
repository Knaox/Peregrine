import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { TwoFactorSetup } from '@/components/auth/TwoFactorSetup';

/**
 * Full-screen 2FA setup page at /2fa/setup.
 *
 * Used in two flows:
 *   1. User-initiated (optional): link from the profile, onboarding prompt.
 *   2. Admin-enforced: when RequireTwoFactor middleware returns 403 with
 *      setup_url, the frontend interceptor can navigate here. The query
 *      string ?enforced=1 surfaces a "your admin requires 2FA" banner and
 *      removes the "skip for now" escape hatch.
 *
 * The /settings/security subpage keeps its in-profile management controls —
 * this page is the standalone, focused setup experience.
 */
export function TwoFactorSetupPage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const branding = useBranding();
    const { user, loadUser } = useAuthStore();
    const [params] = useSearchParams();
    const enforced = params.get('enforced') === '1';

    // Already configured → straight to the dashboard.
    useEffect(() => {
        if (user?.has_two_factor === true) {
            navigate('/dashboard', { replace: true });
        }
    }, [user, navigate]);

    const handleComplete = (): void => {
        void loadUser();
        navigate('/dashboard', { replace: true });
    };

    if (user === null) {
        return null;
    }

    return (
        <div
            className="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12"
            style={{ background: 'var(--color-background)' }}
        >
            <m.div
                initial={{ opacity: 0, y: 24 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4 }}
                className="relative z-10 w-full max-w-md"
            >
                <div className="mb-6 text-center">
                    <img
                        src={branding.logo_url}
                        alt={branding.app_name}
                        className="mx-auto mb-4 object-contain"
                        style={{ height: branding.logo_height ?? 48, maxHeight: 64 }}
                    />
                    <h1 className="text-xl font-semibold text-[var(--color-text-primary)]">
                        {t('auth.2fa.setup_page.title')}
                    </h1>
                    <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                        {t('auth.2fa.setup_page.subtitle')}
                    </p>
                </div>

                {enforced && (
                    <div className="mb-4 rounded-[var(--radius)] border border-[var(--color-warning)]/30 bg-[var(--color-warning)]/10 px-4 py-3 text-sm text-[var(--color-warning)]">
                        {t('auth.2fa.setup_page.enforced_banner')}
                    </div>
                )}

                <div
                    className="rounded-[var(--radius-xl)] p-6 sm:p-8"
                    style={{
                        background: 'var(--color-glass)',
                        backdropFilter: 'blur(24px) saturate(180%)',
                        border: '1px solid var(--color-glass-border)',
                    }}
                >
                    <TwoFactorSetup onComplete={handleComplete} />
                </div>

                {!enforced && (
                    <div className="mt-4 text-center">
                        <button
                            type="button"
                            onClick={() => navigate('/dashboard', { replace: true })}
                            className="text-sm text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors cursor-pointer"
                        >
                            {t('auth.2fa.setup_page.skip_for_now')}
                        </button>
                    </div>
                )}
            </m.div>
        </div>
    );
}
