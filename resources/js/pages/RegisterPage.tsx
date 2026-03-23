import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { ApiError } from '@/services/api';

export function RegisterPage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const { register } = useAuthStore();
    const branding = useBranding();

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [generalError, setGeneralError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const authMode = document.querySelector('meta[name="auth-mode"]')?.getAttribute('content') ?? 'local';
    const isOAuth = authMode === 'oauth';

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setGeneralError('');
        setIsSubmitting(true);

        try {
            await register({
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
            });
            navigate('/dashboard');
        } catch (err) {
            if (err instanceof ApiError && err.status === 422) {
                const validationErrors = err.data.errors as Record<string, string[]> | undefined;
                if (validationErrors) {
                    setErrors(validationErrors);
                } else {
                    setGeneralError(t('common.error'));
                }
            } else {
                setGeneralError(t('common.error'));
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const getFieldError = (field: string): string | undefined => {
        return errors[field]?.[0];
    };

    const inputClasses = clsx(
        'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
        'placeholder-[var(--color-text-muted)] transition-all duration-[var(--transition-base)]',
        'focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
    );

    if (isOAuth) {
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
                    <div className="mb-8 text-center">
                        <img
                            src={branding.logo_url}
                            alt={branding.app_name}
                            className="mx-auto mb-4 h-16 w-16"
                            style={{ filter: 'drop-shadow(0 0 12px var(--color-primary-glow))' }}
                        />
                        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
                            {branding.app_name}
                        </h1>
                    </div>

                    <div className="rounded-[var(--radius-lg)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] p-8 text-center shadow-2xl backdrop-blur-xl">
                        <p className="mb-6 text-[var(--color-text-secondary)]">
                            {t('auth.register.disabled')}
                        </p>
                        <Link
                            to="/login"
                            className={clsx(
                                'inline-block rounded-[var(--radius)] bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white',
                                'transition-all duration-[var(--transition-base)]',
                                'hover:bg-[var(--color-primary-hover)] hover:shadow-[var(--shadow-glow)] hover:scale-[1.01]',
                            )}
                        >
                            {t('auth.register.sign_in')}
                        </Link>
                    </div>
                </m.div>
            </div>
        );
    }

    return (
        <div
            className="flex min-h-screen items-center justify-center px-4 text-[var(--color-text-primary)]"
            style={{
                background: 'linear-gradient(135deg, var(--color-background) 0%, var(--color-surface) 50%, var(--color-background) 100%)',
                backgroundSize: '200% 200%',
                animation: 'ambient-shift 20s ease infinite',
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
                        {t('auth.register.title')}
                    </p>
                </div>

                <div className="rounded-[var(--radius-lg)] border border-[var(--color-glass-border)] bg-[var(--color-glass)] p-8 shadow-2xl backdrop-blur-xl">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <AnimatePresence>
                            {generalError && (
                                <m.div
                                    initial={{ opacity: 0, x: -20 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    exit={{ opacity: 0, x: -20 }}
                                    transition={{ duration: 0.25 }}
                                    className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger-glow)] px-4 py-3 text-sm text-[var(--color-danger)]"
                                >
                                    {generalError}
                                </m.div>
                            )}
                        </AnimatePresence>

                        <div>
                            <label
                                htmlFor="name"
                                className="mb-1.5 block text-sm font-medium text-[var(--color-text-secondary)]"
                            >
                                {t('auth.register.name')}
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                required
                                autoComplete="name"
                                className={inputClasses}
                            />
                            {getFieldError('name') && (
                                <p className="mt-1 text-xs text-[var(--color-danger)]">
                                    {getFieldError('name')}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="email"
                                className="mb-1.5 block text-sm font-medium text-[var(--color-text-secondary)]"
                            >
                                {t('auth.register.email')}
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
                            {getFieldError('email') && (
                                <p className="mt-1 text-xs text-[var(--color-danger)]">
                                    {getFieldError('email')}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="password"
                                className="mb-1.5 block text-sm font-medium text-[var(--color-text-secondary)]"
                            >
                                {t('auth.register.password')}
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                autoComplete="new-password"
                                className={inputClasses}
                            />
                            {getFieldError('password') && (
                                <p className="mt-1 text-xs text-[var(--color-danger)]">
                                    {getFieldError('password')}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="password_confirmation"
                                className="mb-1.5 block text-sm font-medium text-[var(--color-text-secondary)]"
                            >
                                {t('auth.register.password_confirmation')}
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                required
                                autoComplete="new-password"
                                className={inputClasses}
                            />
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
                            {isSubmitting ? t('common.loading') : t('auth.register.button')}
                        </button>
                    </form>
                </div>

                {/* Link to login */}
                <p className="mt-6 text-center text-sm text-[var(--color-text-secondary)]">
                    {t('auth.register.has_account')}{' '}
                    <Link
                        to="/login"
                        className="font-medium text-[var(--color-primary)] transition-colors duration-[var(--transition-base)] hover:text-[var(--color-primary-hover)]"
                    >
                        {t('auth.register.sign_in')}
                    </Link>
                </p>
            </m.div>
        </div>
    );
}
