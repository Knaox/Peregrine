import { useState, type FormEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { ApiError } from '@/services/api';
import { AuthField } from '@/components/auth/AuthField';
import { safeRedirectPath } from '@/utils/redirect';

interface LocalLoginFormProps {
    /** Render the "or" divider above the form (when OAuth buttons precede it). */
    showDivider: boolean;
}

/**
 * Email + password sign-in form with its own submit lifecycle (2FA-aware).
 * Split out of LoginFormCard so the card stays a thin orchestrator and both
 * files keep within the 300-line budget. Rendered inline in the classic
 * layout, and behind the "sign in locally" reveal in OAuth-first mode.
 */
export function LocalLoginForm({ showDivider }: LocalLoginFormProps) {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { login } = useAuthStore();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [error, setError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [focusedField, setFocusedField] = useState<string | null>(null);

    const handleSubmit = async (e: FormEvent): Promise<void> => {
        e.preventDefault();
        setError('');
        setIsSubmitting(true);
        try {
            // Honour `?redirect=` so an invitation link
            // (/login?redirect=/invite/{token}) returns the user to the invite
            // page to accept, instead of always dropping them on the dashboard.
            const redirectTo = safeRedirectPath(searchParams.get('redirect'));
            const result = await login(email, password, remember);
            if (result.requires2fa === true) {
                // Carry the target through the 2FA challenge so it survives.
                navigate(redirectTo ? `/2fa/challenge?redirect=${encodeURIComponent(redirectTo)}` : '/2fa/challenge');
                return;
            }
            navigate(redirectTo ?? '/dashboard');
        } catch (err) {
            setError(err instanceof ApiError ? t('auth-login:error') : t('common:error'));
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <>
            {showDivider && (
                <div className="flex items-center gap-3 py-1">
                    <div className="flex-1 h-px bg-[var(--color-border)]" />
                    <span className="text-xs uppercase tracking-wider text-[var(--color-text-muted)]">
                        {t('auth-login:or')}
                    </span>
                    <div className="flex-1 h-px bg-[var(--color-border)]" />
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                <AnimatePresence>
                    {error && (
                        <m.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            className="overflow-hidden rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-2.5 text-sm text-[var(--color-danger)]"
                        >
                            {error}
                        </m.div>
                    )}
                </AnimatePresence>

                <AuthField
                    id="email"
                    type="email"
                    value={email}
                    label={t('auth-login:email')}
                    onChange={setEmail}
                    focused={focusedField === 'email'}
                    onFocus={() => setFocusedField('email')}
                    onBlur={() => setFocusedField(null)}
                />
                <AuthField
                    id="password"
                    type="password"
                    value={password}
                    label={t('auth-login:password')}
                    onChange={setPassword}
                    focused={focusedField === 'password'}
                    onFocus={() => setFocusedField('password')}
                    onBlur={() => setFocusedField(null)}
                />

                <label className="flex items-center gap-2 cursor-pointer group pt-1">
                    <input
                        type="checkbox"
                        checked={remember}
                        onChange={(e) => setRemember(e.target.checked)}
                        className="h-5 w-5 sm:h-4 sm:w-4 rounded border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-primary)] cursor-pointer"
                    />
                    <span className="text-sm text-[var(--color-text-muted)] group-hover:text-[var(--color-text-secondary)] transition-colors">
                        {t('auth-login:remember')}
                    </span>
                </label>

                <button
                    type="submit"
                    disabled={isSubmitting}
                    className={clsx(
                        'w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-white cursor-pointer',
                        'shadow-[0_4px_20px_var(--color-primary-glow)]',
                        'transition-all duration-200',
                        'hover:bg-[var(--color-primary-hover)] hover:shadow-[0_8px_32px_var(--color-primary-glow)]',
                        'active:scale-[0.98]',
                        'disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100',
                    )}
                >
                    {isSubmitting ? t('common:loading') : t('auth-login:button')}
                </button>
            </form>
        </>
    );
}
