import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '@/stores/authStore';
import {
    useTwoFactorDisable,
    useTwoFactorRegenerateRecoveryCodes,
} from '@/hooks/useTwoFactor';
import { TwoFactorSetup } from '@/components/auth/TwoFactorSetup';
import { RecoveryCodesDisplay } from '@/components/auth/RecoveryCodesDisplay';

/**
 * User-facing 2FA management sub-page. Reached from Profile. The user can:
 * - Enable 2FA (QR + code confirm → recovery codes display once)
 * - Disable 2FA (password or current code confirm)
 * - Regenerate recovery codes
 *
 * The authStore user.two_factor_confirmed_at field (not yet exposed in the
 * User type but present on the backend) is the source of truth; for Étape B
 * we rely on a local `enabled` flag driven by setup/disable callbacks. A
 * follow-up session will surface the column on UserResource.
 */
export function SecurityPage() {
    const { t } = useTranslation();
    const { user, loadUser } = useAuthStore();
    const disable = useTwoFactorDisable();
    const regenerate = useTwoFactorRegenerateRecoveryCodes();

    const [showSetup, setShowSetup] = useState(false);
    const [regeneratedCodes, setRegeneratedCodes] = useState<string[] | null>(null);
    const [disableError, setDisableError] = useState('');
    const [password, setPassword] = useState('');

    if (user === null) {
        return null;
    }

    // TODO (étape B polish): expose two_factor_confirmed_at on UserResource
    // and drive enabled state from it instead of a local toggle.
    const enabled = user.has_two_factor ?? false;

    const handleDisable = async (): Promise<void> => {
        setDisableError('');
        disable.mutate(
            { password },
            {
                onSuccess: () => {
                    setPassword('');
                    void loadUser();
                },
                onError: () => setDisableError(t('auth.2fa.disable.error')),
            },
        );
    };

    const handleRegenerate = (): void => {
        if (!window.confirm(t('auth.2fa.recovery.regenerate_confirm'))) return;
        regenerate.mutate(undefined, {
            onSuccess: (data) => setRegeneratedCodes(data.recovery_codes),
        });
    };

    return (
        <div className="max-w-2xl space-y-6 p-6">
            <header>
                <h1 className="text-2xl font-semibold text-[var(--color-text-primary)]">
                    {t('settings.security.title')}
                </h1>
                <p className="mt-1 text-sm text-[var(--color-text-muted)]">{t('settings.security.subtitle')}</p>
            </header>

            <section className="rounded-[var(--radius-xl)] border border-[var(--color-border)] bg-[var(--color-surface)] p-6 space-y-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-base font-semibold text-[var(--color-text-primary)]">
                            {t('settings.security.two_factor_section_title')}
                        </h2>
                        <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                            {t('settings.security.two_factor_section_description')}
                        </p>
                    </div>
                    <span
                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                            enabled
                                ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]'
                                : 'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]'
                        }`}
                    >
                        {enabled ? t('auth.2fa.status.enabled') : t('auth.2fa.status.disabled')}
                    </span>
                </div>

                {regeneratedCodes !== null && (
                    <RecoveryCodesDisplay codes={regeneratedCodes} onAcknowledge={() => setRegeneratedCodes(null)} />
                )}

                {!enabled && !showSetup && (
                    <button
                        type="button"
                        onClick={() => setShowSetup(true)}
                        className="rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white cursor-pointer hover:bg-[var(--color-primary-hover)]"
                    >
                        {t('auth.2fa.setup.title')}
                    </button>
                )}

                {!enabled && showSetup && (
                    <TwoFactorSetup
                        onComplete={() => {
                            setShowSetup(false);
                            void loadUser();
                        }}
                    />
                )}

                {enabled && (
                    <div className="space-y-3 pt-2">
                        <button
                            type="button"
                            onClick={handleRegenerate}
                            disabled={regenerate.isPending}
                            className="rounded-[var(--radius)] border border-[var(--color-border)] px-4 py-2 text-sm font-medium text-[var(--color-text-secondary)] hover:border-[var(--color-border-hover)] cursor-pointer disabled:opacity-50"
                        >
                            {t('auth.2fa.recovery.regenerate_button')}
                        </button>

                        <div className="rounded-[var(--radius)] border border-[var(--color-border)] p-4 space-y-3">
                            <h3 className="text-sm font-semibold text-[var(--color-text-primary)]">
                                {t('auth.2fa.disable.title')}
                            </h3>
                            <p className="text-xs text-[var(--color-text-muted)]">
                                {t('auth.2fa.disable.description')}
                            </p>
                            {disableError !== '' && (
                                <div className="rounded-[var(--radius)] bg-[var(--color-danger)]/10 px-3 py-2 text-xs text-[var(--color-danger)]">
                                    {disableError}
                                </div>
                            )}
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder={t('auth.2fa.disable.password_label')}
                                className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-background)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:border-[var(--color-primary)]"
                            />
                            <button
                                type="button"
                                onClick={handleDisable}
                                disabled={disable.isPending || password === ''}
                                className="rounded-[var(--radius)] bg-[var(--color-danger)] px-4 py-2 text-sm font-semibold text-white cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {t('auth.2fa.disable.confirm_button')}
                            </button>
                        </div>
                    </div>
                )}
            </section>
        </div>
    );
}
