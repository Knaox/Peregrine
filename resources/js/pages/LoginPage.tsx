import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { ApiError } from '@/services/api';
import { LoginParticles } from '@/components/LoginParticles';

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
    const [focusedField, setFocusedField] = useState<string | null>(null);

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
            setError(err instanceof ApiError ? t('auth.login.error') : t('common.error'));
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleOAuthLogin = () => { window.location.href = '/api/oauth/redirect'; };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4">
            {/* Animated gradient background */}
            <div
                className="absolute inset-0"
                style={{
                    backgroundImage: 'linear-gradient(-45deg, var(--color-background), #130d1e, var(--color-background), #1a0e24)',
                    backgroundSize: '400% 400%',
                    animation: 'gradient-shift 20s ease infinite',
                }}
            />

            {/* Floating particles */}
            <LoginParticles />

            {/* Central glow */}
            <div className="absolute inset-0 pointer-events-none">
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[600px] w-[600px] rounded-full opacity-30"
                    style={{ background: 'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.2) 0%, transparent 70%)', filter: 'blur(60px)' }}
                />
            </div>

            <m.div
                initial={{ opacity: 0, y: 30, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ duration: 0.6, ease: [0.34, 1.56, 0.64, 1] }}
                className="relative z-10 w-full max-w-md"
            >
                {/* Logo and title */}
                <m.div
                    initial={{ opacity: 0, y: -20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.2, duration: 0.5 }}
                    className="mb-8 text-center"
                >
                    <m.img
                        src={branding.logo_url}
                        alt={branding.app_name}
                        className="mx-auto mb-5 h-20 w-20"
                        animate={{ filter: ['drop-shadow(0 0 20px rgba(var(--color-primary-rgb), 0.3))', 'drop-shadow(0 0 40px rgba(var(--color-primary-rgb), 0.5))', 'drop-shadow(0 0 20px rgba(var(--color-primary-rgb), 0.3))'] }}
                        transition={{ duration: 3, repeat: Infinity, ease: 'easeInOut' }}
                    />
                    <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">
                        {branding.app_name}
                    </h1>
                    <p className="mt-2 text-[var(--color-text-secondary)]">{t('auth.login.title')}</p>
                </m.div>

                {/* Glass card */}
                <m.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.3, duration: 0.5 }}
                    className="overflow-hidden rounded-[var(--radius-xl)] border border-[var(--color-glass-border)] p-8 shadow-2xl"
                    style={{ background: 'var(--color-glass)', backdropFilter: 'blur(20px) saturate(180%)', boxShadow: 'var(--glass-highlight-strong), 0 25px 50px rgba(0,0,0,0.4)' }}
                >
                    {isOAuth ? (
                        <m.button
                            type="button"
                            onClick={handleOAuthLogin}
                            whileHover={{ scale: 1.02 }}
                            whileTap={{ scale: 0.98 }}
                            className={clsx(
                                'w-full rounded-[var(--radius)] border border-[var(--color-glass-border)] bg-[var(--color-surface)] px-4 py-3.5 text-sm font-semibold text-[var(--color-text-primary)]',
                                'transition-all duration-[var(--transition-base)]',
                                'hover:border-[var(--color-primary)]/50 hover:shadow-[var(--shadow-glow)]',
                            )}
                        >
                            {t('auth.login.oauth_button', { provider: 'Shop' })}
                        </m.button>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <AnimatePresence>
                                {error && (
                                    <m.div
                                        initial={{ opacity: 0, y: -10, height: 0 }}
                                        animate={{ opacity: 1, y: 0, height: 'auto' }}
                                        exit={{ opacity: 0, y: -10, height: 0 }}
                                        className="overflow-hidden rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]"
                                    >
                                        {error}
                                    </m.div>
                                )}
                            </AnimatePresence>

                            <LoginField
                                id="email" type="email" value={email} label={t('auth.login.email')}
                                onChange={setEmail} focused={focusedField === 'email'}
                                onFocus={() => setFocusedField('email')} onBlur={() => setFocusedField(null)}
                            />
                            <LoginField
                                id="password" type="password" value={password} label={t('auth.login.password')}
                                onChange={setPassword} focused={focusedField === 'password'}
                                onFocus={() => setFocusedField('password')} onBlur={() => setFocusedField(null)}
                            />

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 cursor-pointer group">
                                    <input
                                        type="checkbox" checked={remember}
                                        onChange={(e) => setRemember(e.target.checked)}
                                        className="h-4 w-4 rounded border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-primary)] focus:ring-[var(--color-primary-glow)] cursor-pointer"
                                    />
                                    <span className="text-sm text-[var(--color-text-secondary)] transition-colors duration-150 group-hover:text-[var(--color-text-primary)]">
                                        {t('auth.login.remember')}
                                    </span>
                                </label>
                            </div>

                            <m.button
                                type="submit" disabled={isSubmitting}
                                whileHover={{ scale: 1.02 }} whileTap={{ scale: 0.97 }}
                                className={clsx(
                                    'relative w-full overflow-hidden rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-white',
                                    'shadow-[0_4px_16px_var(--color-primary-glow)]',
                                    'transition-all duration-[var(--transition-base)]',
                                    'hover:bg-[var(--color-primary-hover)] hover:shadow-[0_8px_32px_var(--color-primary-glow)]',
                                    'disabled:opacity-50 disabled:cursor-not-allowed',
                                )}
                            >
                                {isSubmitting ? t('common.loading') : t('auth.login.button')}
                            </m.button>
                        </form>
                    )}
                </m.div>

                {!isOAuth && (
                    <m.p
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.5 }}
                        className="mt-6 text-center text-sm text-[var(--color-text-secondary)]"
                    >
                        {t('auth.login.no_account')}{' '}
                        <Link to="/register" className="font-medium text-[var(--color-primary)] transition-colors duration-[var(--transition-base)] hover:text-[var(--color-primary-hover)]">
                            {t('auth.login.create_account')}
                        </Link>
                    </m.p>
                )}
            </m.div>
        </div>
    );
}

/* Animated input field with floating label effect */
function LoginField({ id, type, value, label, onChange, focused, onFocus, onBlur }: {
    id: string; type: string; value: string; label: string;
    onChange: (v: string) => void; focused: boolean;
    onFocus: () => void; onBlur: () => void;
}) {
    return (
        <div className="relative">
            <label htmlFor={id} className={clsx(
                'mb-1.5 block text-sm font-medium transition-colors duration-200',
                focused ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-secondary)]',
            )}>
                {label}
            </label>
            <input
                id={id} type={type} value={value} required
                autoComplete={type === 'email' ? 'email' : 'current-password'}
                onChange={(e) => onChange(e.target.value)}
                onFocus={onFocus} onBlur={onBlur}
                className={clsx(
                    'w-full rounded-[var(--radius)] border bg-[var(--color-surface)] px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
                    'placeholder-[var(--color-text-muted)] transition-all duration-[var(--transition-base)]',
                    'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-glow)]',
                    focused
                        ? 'border-[var(--color-primary)] shadow-[0_0_16px_var(--color-primary-glow)]'
                        : 'border-[var(--color-border)] hover:border-[var(--color-border-hover)]',
                )}
            />
        </div>
    );
}
