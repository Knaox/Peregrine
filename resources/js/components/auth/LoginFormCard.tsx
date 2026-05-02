import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useAuthProviders } from '@/hooks/useAuthProviders';
import { ApiError } from '@/services/api';
import { SocialLoginButtons } from '@/components/auth/SocialLoginButtons';

interface LoginFormCardProps {
    /** Visual chrome around the form. Defaults to a glass card with blur. */
    variant?: 'glass' | 'solid' | 'flush';
    className?: string;
}

/**
 * Self-contained login form: social buttons + local form + register link.
 * Reused across all 4 LoginTemplate components — only the surrounding
 * background/layout differs per template.
 */
export function LoginFormCard({ variant = 'glass', className }: LoginFormCardProps) {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const { login } = useAuthStore();
    const providers = useAuthProviders();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [error, setError] = useState('');
    const [providerError, setProviderError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [focusedField, setFocusedField] = useState<string | null>(null);

    const localEnabled = providers.data?.local_enabled ?? true;
    const localRegistrationEnabled = providers.data?.local_registration_enabled ?? true;
    const canonicalProvider = providers.data?.canonical_provider ?? null;
    const canonicalRegisterUrl = providers.data?.canonical_register_url ?? null;
    const enabledProviders = providers.data?.providers ?? [];
    const canRegisterLocal = localEnabled && localRegistrationEnabled;
    const canRegisterCanonical = canonicalRegisterUrl !== null;
    const canonicalProviderLabel =
        canonicalProvider !== null ? t(`auth.providers.${canonicalProvider}`) : '';

    useEffect(() => {
        const errorKey = searchParams.get('error');
        if (errorKey === null) return;
        const translated = t(errorKey, { provider: canonicalProviderLabel });
        setProviderError(
            translated === errorKey ? t('auth.social.oauth_failed') : translated,
        );
        const next = new URLSearchParams(searchParams);
        next.delete('error');
        setSearchParams(next, { replace: true });
    }, [searchParams, setSearchParams, t, canonicalProviderLabel]);

    const handleSubmit = async (e: FormEvent): Promise<void> => {
        e.preventDefault();
        setError('');
        setIsSubmitting(true);
        try {
            const result = await login(email, password, remember);
            if (result.requires2fa === true) {
                navigate('/2fa/challenge');
                return;
            }
            navigate('/dashboard');
        } catch (err) {
            setError(err instanceof ApiError ? t('auth.login.error') : t('common.error'));
        } finally {
            setIsSubmitting(false);
        }
    };

    const cardClasses = clsx(
        'rounded-[var(--radius-xl)] p-6 sm:p-8',
        variant === 'glass' && 'themed-border border border-[var(--color-glass-border)]',
        variant === 'solid' && 'themed-border border border-[var(--color-border)] bg-[var(--color-surface)]',
        className,
    );
    const cardStyle =
        variant === 'glass'
            ? {
                  background: 'var(--color-glass)',
                  backdropFilter: 'var(--glass-blur)',
                  boxShadow: 'var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,0.05)',
              }
            : undefined;

    return (
        <div className={cardClasses} style={cardStyle}>
            <div className="space-y-4">
                <AnimatePresence>
                    {providerError && (
                        <m.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            className="overflow-hidden rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10"
                        >
                            <div className="flex items-start gap-2 px-4 py-2.5">
                                <span className="text-sm text-[var(--color-danger)] flex-1">
                                    {providerError}
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setProviderError('')}
                                    aria-label={t('common.close')}
                                    className="text-[var(--color-danger)]/70 hover:text-[var(--color-danger)] transition-colors cursor-pointer"
                                >
                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </m.div>
                    )}
                </AnimatePresence>

                {enabledProviders.length > 0 && <SocialLoginButtons providers={enabledProviders} />}

                {localEnabled && enabledProviders.length > 0 && (
                    <div className="flex items-center gap-3 py-1">
                        <div className="flex-1 h-px bg-[var(--color-border)]" />
                        <span className="text-xs uppercase tracking-wider text-[var(--color-text-muted)]">
                            {t('auth.login.or')}
                        </span>
                        <div className="flex-1 h-px bg-[var(--color-border)]" />
                    </div>
                )}

                {localEnabled && (
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
                            label={t('auth.login.email')}
                            onChange={setEmail}
                            focused={focusedField === 'email'}
                            onFocus={() => setFocusedField('email')}
                            onBlur={() => setFocusedField(null)}
                        />
                        <AuthField
                            id="password"
                            type="password"
                            value={password}
                            label={t('auth.login.password')}
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
                                className="h-4 w-4 rounded border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-primary)] cursor-pointer"
                            />
                            <span className="text-sm text-[var(--color-text-muted)] group-hover:text-[var(--color-text-secondary)] transition-colors">
                                {t('auth.login.remember')}
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
                            {isSubmitting ? t('common.loading') : t('auth.login.button')}
                        </button>
                    </form>
                )}
            </div>

            {(canRegisterLocal || canRegisterCanonical) && (
                <p className="mt-5 text-center text-sm text-[var(--color-text-muted)]">
                    {t('auth.login.no_account')}{' '}
                    {canRegisterCanonical ? (
                        <>
                            <a
                                href={canonicalRegisterUrl as string}
                                className="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors"
                            >
                                {t('auth.login.create_account_canonical', { provider: canonicalProviderLabel })}
                            </a>
                            {canRegisterLocal && (
                                <>
                                    {' · '}
                                    <Link
                                        to="/register"
                                        className="font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-primary)] transition-colors"
                                    >
                                        {t('auth.login.create_account_local')}
                                    </Link>
                                </>
                            )}
                        </>
                    ) : (
                        <Link
                            to="/register"
                            className="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors"
                        >
                            {t('auth.login.create_account')}
                        </Link>
                    )}
                </p>
            )}
        </div>
    );
}

function AuthField({
    id,
    type,
    value,
    label,
    error,
    onChange,
    focused,
    onFocus,
    onBlur,
}: {
    id: string;
    type: string;
    value: string;
    label: string;
    error?: string;
    onChange: (v: string) => void;
    focused: boolean;
    onFocus: () => void;
    onBlur: () => void;
}) {
    return (
        <div>
            <label
                htmlFor={id}
                className={clsx(
                    'mb-1.5 block text-xs font-medium uppercase tracking-wider transition-colors duration-150',
                    focused ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-muted)]',
                )}
            >
                {label}
            </label>
            <input
                id={id}
                type={type}
                value={value}
                required
                autoComplete={type === 'email' ? 'email' : type === 'password' ? 'current-password' : undefined}
                onChange={(e) => onChange(e.target.value)}
                onFocus={onFocus}
                onBlur={onBlur}
                className={clsx(
                    'w-full rounded-[var(--radius)] border px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
                    'bg-[var(--color-background)] transition-all duration-200',
                    'focus:outline-none focus:ring-1',
                    focused
                        ? 'border-[var(--color-primary)] ring-[var(--color-primary-glow)] shadow-[0_0_12px_var(--color-primary-glow)]'
                        : 'border-[var(--color-border)] ring-transparent hover:border-[var(--color-border-hover)]',
                )}
            />
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
