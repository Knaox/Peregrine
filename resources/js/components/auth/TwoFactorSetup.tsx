import { useEffect, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { useTwoFactorConfirm, useTwoFactorSetup } from '@/hooks/useTwoFactor';
import { RecoveryCodesDisplay } from '@/components/auth/RecoveryCodesDisplay';

type Step = 'loading' | 'display_qr' | 'show_recovery' | 'done';

interface TwoFactorSetupProps {
    /** Called after the user acknowledges their recovery codes. */
    onComplete: () => void;
}

export function TwoFactorSetup({ onComplete }: TwoFactorSetupProps) {
    const { t } = useTranslation();
    const setup = useTwoFactorSetup();
    const confirm = useTwoFactorConfirm();

    const [step, setStep] = useState<Step>('loading');
    const [secret, setSecret] = useState('');
    const [qrSvg, setQrSvg] = useState('');
    const [code, setCode] = useState('');
    const [error, setError] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);

    useEffect(() => {
        if (step !== 'loading') return;
        setup.mutate(undefined, {
            onSuccess: (data) => {
                setSecret(data.secret);
                setQrSvg(data.qr_svg_base64);
                setStep('display_qr');
            },
            onError: () => setError(t('common.error')),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleConfirm = (e: FormEvent): void => {
        e.preventDefault();
        setError('');
        if (code.trim().length !== 6) return;
        confirm.mutate(
            { secret, code: code.trim() },
            {
                onSuccess: (data) => {
                    setRecoveryCodes(data.recovery_codes);
                    setStep('show_recovery');
                },
                onError: () => setError(t('auth.2fa.setup.error_invalid')),
            },
        );
    };

    if (step === 'loading') {
        return <p className="text-sm text-[var(--color-text-muted)]">{t('common.loading')}</p>;
    }

    if (step === 'show_recovery') {
        return (
            <RecoveryCodesDisplay
                codes={recoveryCodes}
                onAcknowledge={() => {
                    setStep('done');
                    onComplete();
                }}
            />
        );
    }

    return (
        <div className="space-y-4">
            <p className="text-sm text-[var(--color-text-muted)]">{t('auth.2fa.setup.intro')}</p>

            <div className="flex justify-center rounded-[var(--radius)] border border-[var(--color-border)] bg-white p-4">
                {/* QR image — backend sends a data:image/svg+xml base64 URI. */}
                <img src={qrSvg} alt="2FA QR code" className="h-48 w-48" />
            </div>

            <details className="rounded-[var(--radius)] border border-[var(--color-border)] p-3 text-sm">
                <summary className="cursor-pointer text-[var(--color-text-muted)]">
                    {t('auth.2fa.setup.manual_entry_label')}
                </summary>
                <code className="mt-2 block break-all text-xs text-[var(--color-text-primary)]">{secret}</code>
            </details>

            <form onSubmit={handleConfirm} className="space-y-3">
                {error !== '' && (
                    <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-3 py-2 text-sm text-[var(--color-danger)]">
                        {error}
                    </div>
                )}

                <div>
                    <label
                        htmlFor="setup-code"
                        className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]"
                    >
                        {t('auth.2fa.setup.code_label')}
                    </label>
                    <input
                        id="setup-code"
                        type="text"
                        inputMode="numeric"
                        maxLength={6}
                        value={code}
                        onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                        className="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-background)] px-4 py-2.5 text-sm text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]"
                    />
                </div>

                <button
                    type="submit"
                    disabled={confirm.isPending || code.length !== 6}
                    className={clsx(
                        'w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-white cursor-pointer',
                        'shadow-[0_4px_20px_var(--color-primary-glow)] transition-all duration-200',
                        'hover:bg-[var(--color-primary-hover)] disabled:opacity-50 disabled:cursor-not-allowed',
                    )}
                >
                    {confirm.isPending ? t('auth.2fa.setup.verifying') : t('auth.2fa.setup.verify_button')}
                </button>
            </form>
        </div>
    );
}
