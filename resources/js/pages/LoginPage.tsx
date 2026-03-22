import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
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

    // Check auth mode from meta tag
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
                    <p className="mt-2 text-slate-400">{t('auth.login.title')}</p>
                </div>

                <div className="rounded-xl border border-slate-700 bg-slate-800 p-8">
                    {isOAuth ? (
                        /* OAuth mode: single button */
                        <button
                            type="button"
                            onClick={handleOAuthLogin}
                            className="w-full rounded-lg bg-orange-500 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-slate-800"
                        >
                            {t('auth.login.oauth_button', { provider: 'Shop' })}
                        </button>
                    ) : (
                        /* Local mode: email/password form */
                        <form onSubmit={handleSubmit} className="space-y-5">
                            {error && (
                                <div className="rounded-lg border border-red-500/50 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                                    {error}
                                </div>
                            )}

                            <div>
                                <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-slate-300">
                                    {t('auth.login.email')}
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
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-slate-300">
                                    {t('auth.login.password')}
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    required
                                    autoComplete="current-password"
                                    className="w-full rounded-lg border border-slate-600 bg-slate-700 px-4 py-2.5 text-sm text-white placeholder-slate-400 transition-colors focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={remember}
                                        onChange={(e) => setRemember(e.target.checked)}
                                        className="h-4 w-4 rounded border-slate-600 bg-slate-700 text-orange-500 focus:ring-orange-500 focus:ring-offset-slate-800"
                                    />
                                    <span className="text-sm text-slate-300">
                                        {t('auth.login.remember')}
                                    </span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="w-full rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isSubmitting ? t('common.loading') : t('auth.login.button')}
                            </button>
                        </form>
                    )}
                </div>

                {/* Link to register (only in local mode) */}
                {!isOAuth && (
                    <p className="mt-6 text-center text-sm text-slate-400">
                        {t('auth.login.no_account')}{' '}
                        <Link
                            to="/register"
                            className="font-medium text-orange-500 hover:text-orange-400 transition-colors"
                        >
                            {t('auth.login.create_account')}
                        </Link>
                    </p>
                )}
            </div>
        </div>
    );
}
