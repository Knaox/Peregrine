import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';

interface TwoFactorChallengeFormProps {
    onSubmit: (code: string) => Promise<void>;
    /** Shown above the input — error message from the server, if any. */
    error?: string;
}

export function TwoFactorChallengeForm({ onSubmit, error }: TwoFactorChallengeFormProps) {
    const { t } = useTranslation();
    const [code, setCode] = useState('');
    const [useRecovery, setUseRecovery] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: FormEvent): Promise<void> => {
        e.preventDefault();
        if (isSubmitting || code.trim() === '') return;
        setIsSubmitting(true);
        try {
            await onSubmit(code.trim());
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {error !== undefined && error !== '' && (
                <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-2.5 text-sm text-[var(--color-danger)]">
                    {error}
                </div>
            )}

            <div>
                <label
                    htmlFor="challenge-code"
                    className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]"
                >
                    {useRecovery ? t('auth.2fa.challenge.recovery_label') : t('auth.2fa.challenge.code_label')}
                </label>
                <input
                    id="challenge-code"
                    type="text"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    inputMode={useRecovery ? 'text' : 'numeric'}
                    autoComplete="one-time-code"
                    maxLength={useRecovery ? 25 : 6}
                    required
                    autoFocus
                    className={clsx(
                        'w-full rounded-[var(--radius)] border px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
                        'bg-[var(--color-background)] transition-all duration-200',
                        'focus:outline-none focus:ring-1 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
                        'border-[var(--color-border)] ring-transparent hover:border-[var(--color-border-hover)]',
                    )}
                />
            </div>

            <button
                type="submit"
                disabled={isSubmitting || code.trim() === ''}
                className={clsx(
                    'w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-white cursor-pointer',
                    'shadow-[0_4px_20px_var(--color-primary-glow)] transition-all duration-200',
                    'hover:bg-[var(--color-primary-hover)]',
                    'active:scale-[0.98]',
                    'disabled:opacity-50 disabled:cursor-not-allowed',
                )}
            >
                {isSubmitting ? t('auth.2fa.challenge.submitting') : t('auth.2fa.challenge.submit')}
            </button>

            <button
                type="button"
                onClick={() => {
                    setUseRecovery((prev) => !prev);
                    setCode('');
                }}
                className="w-full text-center text-xs text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors cursor-pointer"
            >
                {useRecovery ? t('auth.2fa.challenge.code_label') : t('auth.2fa.challenge.use_recovery')}
            </button>
        </form>
    );
}
