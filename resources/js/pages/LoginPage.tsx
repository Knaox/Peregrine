import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { ApiError } from '@/services/api';

export function LoginPage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const { login } = useAuthStore();
    const branding = useBranding();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [error, setError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const authMode = document.querySelector('meta[name="auth-mode"]')?.getAttribute('content') ?? 'local';
    const isOAuth = authMode === 'oauth';

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setError('');
        setIsSubmitting(true);

        try {
            await login(email, password, remember);
            navigate('/dashboard');
        } catch (err) {
            if (err instanceof ApiError) {
                setError(t('auth.login.error'));
            } else {
                setError(t('common.error'));
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleOAuthLogin = () => {
        window.location.href = '/api/oauth/redirect';
    };

    const inputClasses = clsx(
        'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
        'placeholder-[var(--color-text-muted)] transition-all duration-[var(--transition-base)]',
        'focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
    );

    return (
        <div
            className="flex min-h-screen items-center justify-center px-4 text-[var(--color-text-primary)]"
            style={{
                backgroundImage: 'linear-gradient(-45deg, var(--color-background), var(--color-surface), var(--color-background), var(--color-surface-elevated))',
                backgroundSize: '400% 400%',
                animation: 'gradient-shift 20s ease infinite',
            }}
        >
            <m.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }}
                className="w-full max-w-md"
            >
                {/* Logo and title */}
                <div className="mb-8 text-center">
                    <img
                        src={branding.logo_url}
                        alt={branding.app_name}
                        className="mx-auto mb-4 h-16 w-16 drop-shadow-[0_0_20px_var(--color-primary-glow)]"
                    />
                    <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
                        {branding.app_name}
                    </h1>
                    <p className="mt-2 text-[var(--color-text-secondary)]">
                        {t('auth.login.title')}
                    </p>
                </div>

                <div className="rounded-[var(--radius-lg)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] p-8 shadow-2xl backdrop-blur-xl">
                    {isOAuth ? (
                        <button
                            type="button"
                            onClick={handleOAuthLogin}
                            className={clsx(
                                'w-full rounded-[var(--radius)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] px-4 py-3 text-sm font-semibold text-[var(--color-text-primary)] backdrop-blur-xl',
                                'transition-all duration-[var(--transition-base)]',
                                'hover:border-[var(--color-border-hover)] hover:bg-[var(--color-surface-hover)]',
                                'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
                            )}
                        >
                            {t('auth.login.oauth_button', { provider: 'Shop' })}
                        </button>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <AnimatePresence>
                                {error && (
                                    <m.div
                                        initial={{ opacity: 0, x: -20 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        exit={{ opacity: 0, x: -20 }}
                                        transition={{ duration: 0.25 }}
                                        className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger-glow)] px-4 py-3 text-sm text-[var(--color-danger)]"
                                    >
                                        {error}
                                    </m.div>
                                )}
                            </AnimatePresence>

                            <div>
                                <label
                                    htmlFor="email"
                                    className="mb-1.5 block text-sm font-medium text-[var(--color-text-secondary)]"
                                >
                                    {t('auth.login.email')}
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    autoComplete="email"
                                    className={inputClasses}
                                />
                            </div>

                            <div>
                                <label
                                    htmlFor="password"
                                    className="mb-1.5 block text-sm font-medium text-[var(--color-text-secondary)]"
                                >
                                    {t('auth.login.password')}
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    required
                                    autoComplete="current-password"
                                    className={inputClasses}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={remember}
                                        onChange={(e) => setRemember(e.target.checked)}
                                        className="h-4 w-4 rounded border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]"
                                    />
                                    <span className="text-sm text-[var(--color-text-secondary)]">
                                        {t('auth.login.remember')}
                                    </span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className={clsx(
                                    'w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white',
                                    'transition-all duration-[var(--transition-base)]',
                                    'hover:bg-[var(--color-primary-hover)] hover:shadow-[var(--shadow-glow)] hover:scale-[1.01]',
                                    'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
                                    'disabled:cursor-not-allowed disabled:opacity-50',
                                )}
                            >
                                {isSubmitting ? t('common.loading') : t('auth.login.button')}
                            </button>
                        </form>
                    )}
                </div>

                {/* Link to register (only in local mode) */}
                {!isOAuth && (
                    <p className="mt-6 text-center text-sm text-[var(--color-text-secondary)]">
                        {t('auth.login.no_account')}{' '}
                        <Link
                            to="/register"
                            className="font-medium text-[var(--color-primary)] transition-colors duration-[var(--transition-base)] hover:text-[var(--color-primary-hover)]"
                        >
                            {t('auth.login.create_account')}
                        </Link>
                    </p>
                )}
            </m.div>
        </div>
    );
}
