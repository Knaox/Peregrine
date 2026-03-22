import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
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

    // Check auth mode from meta tag
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

    if (isOAuth) {
        return (
            <div className="min-h-screen bg-slate-900 text-white flex items-center justify-center px-4">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <img
                            src={branding.logo_url}
                            alt={branding.app_name}
                            className="mx-auto h-16 w-16 mb-4"
                        />
                        <h1 className="text-2xl font-bold">{branding.app_name}</h1>
                    </div>

                    <div className="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                        <p className="text-slate-300 mb-6">
                            {t('auth.register.disabled')}
                        </p>
                        <Link
                            to="/login"
                            className="inline-block rounded-lg bg-orange-500 px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600"
                        >
                            {t('auth.register.sign_in')}
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-slate-900 text-white flex items-center justify-center px-4">
            <div className="w-full max-w-md">
                {/* Logo and title */}
                <div className="mb-8 text-center">
                    <img
                        src={branding.logo_url}
                        alt={branding.app_name}
                        className="mx-auto h-16 w-16 mb-4"
                    />
                    <h1 className="text-2xl font-bold">{branding.app_name}</h1>
                    <p className="mt-2 text-slate-400">{t('auth.register.title')}</p>
                </div>

                <div className="rounded-xl border border-slate-700 bg-slate-800 p-8">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        {generalError && (
                            <div className="rounded-lg border border-red-500/50 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                                {generalError}
                            </div>
                        )}

                        <div>
                            <label htmlFor="name" className="mb-1.5 block text-sm font-medium text-slate-300">
                                {t('auth.register.name')}
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                required
                                autoComplete="name"
                                className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2.5 text-sm text-white placeholder-slate-400 transition-colors focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
                            />
                            {getFieldError('name') && (
                                <p className="mt-1 text-xs text-red-400">{getFieldError('name')}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-slate-300">
                                {t('auth.register.email')}
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                autoComplete="email"
                                className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2.5 text-sm text-white placeholder-slate-400 transition-colors focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
                            />
                            {getFieldError('email') && (
                                <p className="mt-1 text-xs text-red-400">{getFieldError('email')}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-slate-300">
                                {t('auth.register.password')}
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                autoComplete="new-password"
                                className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2.5 text-sm text-white placeholder-slate-400 transition-colors focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
                            />
                            {getFieldError('password') && (
                                <p className="mt-1 text-xs text-red-400">{getFieldError('password')}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium text-slate-300">
                                {t('auth.register.password_confirmation')}
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                required
                                autoComplete="new-password"
                                className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2.5 text-sm text-white placeholder-slate-400 transition-colors focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={isSubmitting}
                            className="w-full rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isSubmitting ? t('common.loading') : t('auth.register.button')}
                        </button>
                    </form>
                </div>

                {/* Link to login */}
                <p className="mt-6 text-center text-sm text-slate-400">
                    {t('auth.register.has_account')}{' '}
                    <Link
                        to="/login"
                        className="font-medium text-orange-500 hover:text-orange-400 transition-colors"
                    >
                        {t('auth.register.sign_in')}
                    </Link>
                </p>
            </div>
        </div>
    );
}
