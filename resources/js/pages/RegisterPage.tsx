import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { ApiError } from '@/services/api';
import { LoginParticles } from '@/components/LoginParticles';
import { useAuthProviders } from '@/hooks/useAuthProviders';

export function RegisterPage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const { register } = useAuthStore();
    const branding = useBranding();
    const providers = useAuthProviders();

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [generalError, setGeneralError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [focusedField, setFocusedField] = useState<string | null>(null);

    // Registration closes when the admin toggles off local registration. When
    // that happens BUT a canonical IdP register URL is configured (Shop or
    // Paymenter), we show a CTA to sign up there instead of the dead-end
    // "disabled" screen.
    const registrationDisabled = providers.data !== undefined
        && ! providers.data.local_registration_enabled;
    const canonicalProvider = providers.data?.canonical_provider ?? null;
    const canonicalRegisterUrl = providers.data?.canonical_register_url ?? null;
    const canonicalProviderLabel = canonicalProvider !== null
        ? t(`auth.providers.${canonicalProvider}`)
        : '';

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setGeneralError('');
        setIsSubmitting(true);
        try {
            await register({ name, email, password, password_confirmation: passwordConfirmation });
            navigate('/dashboard');
        } catch (err) {
            if (err instanceof ApiError && err.status === 422) {
                const ve = err.data.errors as Record<string, string[]> | undefined;
                if (ve) setErrors(ve); else setGeneralError(t('common.error'));
            } else {
                setGeneralError(t('common.error'));
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const fieldError = (f: string) => errors[f]?.[0];

    if (registrationDisabled) {
        return (
            <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4"
                style={{ background: 'var(--color-background)' }}>
                <div className="absolute inset-0" style={{
                    backgroundImage: 'linear-gradient(-45deg, var(--color-background), var(--color-surface), var(--color-background), var(--color-surface-elevated))',
                    backgroundSize: '400% 400%', animation: 'gradient-shift 20s ease infinite',
                }} />
                <LoginParticles />
                <m.div initial={{ opacity: 0, y: 24 }} animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.5 }} className="relative z-10 w-full max-w-sm text-center">
                    <img src={branding.logo_url} alt={branding.app_name} className="mx-auto mb-4 object-contain"
                        style={{ height: 48, maxWidth: 200, filter: 'drop-shadow(0 0 24px rgba(var(--color-primary-rgb), 0.4))' }} />
                    <h1 className="text-xl font-semibold text-[var(--color-text-primary)] mb-4">{branding.app_name}</h1>
                    <div className="rounded-[var(--radius-xl)] p-6" style={{
                        background: 'var(--color-glass)', backdropFilter: 'blur(24px) saturate(180%)',
                        border: '1px solid var(--color-glass-border)', boxShadow: 'var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,0.05)',
                    }}>
                        {canonicalRegisterUrl ? (
                            <>
                                <p className="mb-5 text-sm text-[var(--color-text-muted)]">
                                    {t('auth.register.redirect_canonical', { provider: canonicalProviderLabel })}
                                </p>
                                <a
                                    href={canonicalRegisterUrl}
                                    className="inline-block rounded-[var(--radius)] bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white shadow-[0_4px_20px_var(--color-primary-glow)] transition-all duration-200 hover:bg-[var(--color-primary-hover)] hover:shadow-[0_8px_32px_var(--color-primary-glow)]"
                                >
                                    {t('auth.register.create_on_canonical', { provider: canonicalProviderLabel })}
                                </a>
                                <p className="mt-4 text-xs">
                                    <Link
                                        to="/login"
                                        className="text-[var(--color-text-secondary)] hover:text-[var(--color-primary)] transition-colors"
                                    >
                                        {t('auth.register.sign_in')}
                                    </Link>
                                </p>
                            </>
                        ) : (
                            <>
                                <p className="mb-5 text-sm text-[var(--color-text-muted)]">
                                    {t('auth.register.disabled')}
                                </p>
                                <Link
                                    to="/login"
                                    className="inline-block rounded-[var(--radius)] bg-[var(--color-primary)] px-6 py-2.5 text-sm font-semibold text-white shadow-[0_4px_20px_var(--color-primary-glow)] transition-all duration-200 hover:bg-[var(--color-primary-hover)] hover:shadow-[0_8px_32px_var(--color-primary-glow)]"
                                >
                                    {t('auth.register.sign_in')}
                                </Link>
                            </>
                        )}
                    </div>
                </m.div>
            </div>
        );
    }

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4"
            style={{ background: 'var(--color-background)' }}>
            <div className="absolute inset-0" style={{
                backgroundImage: 'linear-gradient(-45deg, var(--color-background), #130d1e, var(--color-background), #1a0e24)',
                backgroundSize: '400% 400%', animation: 'gradient-shift 20s ease infinite',
            }} />
            <LoginParticles />
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full pointer-events-none"
                style={{ background: 'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.15) 0%, transparent 70%)', filter: 'blur(60px)' }} />

            <m.div initial={{ opacity: 0, y: 24 }} animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: [0.4, 0, 0.2, 1] }}
                className="relative z-10 w-full max-w-sm">

                <m.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }}
                    transition={{ delay: 0.15 }} className="mb-6 text-center">
                    <div className="flex justify-center mb-4">
                        <img src={branding.logo_url} alt={branding.app_name}
                            className="object-contain"
                            style={{ height: branding.logo_height ?? 48, maxHeight: 64, maxWidth: 220, filter: 'drop-shadow(0 0 24px rgba(var(--color-primary-rgb), 0.4))' }} />
                    </div>
                    {branding.show_app_name !== false && (
                        <h1 className="text-xl font-semibold text-[var(--color-text-primary)]">{branding.app_name}</h1>
                    )}
                    <p className="mt-1 text-sm text-[var(--color-text-muted)]">{t('auth.register.title')}</p>
                </m.div>

                <m.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.25 }}
                    className="rounded-[var(--radius-xl)] p-6 sm:p-8"
                    style={{ background: 'var(--color-glass)', backdropFilter: 'blur(24px) saturate(180%)',
                        border: '1px solid var(--color-glass-border)', boxShadow: 'var(--shadow-lg), inset 0 1px 0 rgba(255,255,255,0.05)' }}>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <AnimatePresence>
                            {generalError && (
                                <m.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }}
                                    exit={{ opacity: 0, height: 0 }}
                                    className="overflow-hidden rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-2.5 text-sm text-[var(--color-danger)]">
                                    {generalError}
                                </m.div>
                            )}
                        </AnimatePresence>

                        <RegField id="name" type="text" value={name} label={t('auth.register.name')} error={fieldError('name')}
                            onChange={setName} focused={focusedField === 'name'} auto="name"
                            onFocus={() => setFocusedField('name')} onBlur={() => setFocusedField(null)} />
                        <RegField id="email" type="email" value={email} label={t('auth.register.email')} error={fieldError('email')}
                            onChange={setEmail} focused={focusedField === 'email'} auto="email"
                            onFocus={() => setFocusedField('email')} onBlur={() => setFocusedField(null)} />
                        <RegField id="password" type="password" value={password} label={t('auth.register.password')} error={fieldError('password')}
                            onChange={setPassword} focused={focusedField === 'password'} auto="new-password"
                            onFocus={() => setFocusedField('password')} onBlur={() => setFocusedField(null)} />
                        <RegField id="password_confirmation" type="password" value={passwordConfirmation}
                            label={t('auth.register.password_confirmation')}
                            onChange={setPasswordConfirmation} focused={focusedField === 'password_confirmation'} auto="new-password"
                            onFocus={() => setFocusedField('password_confirmation')} onBlur={() => setFocusedField(null)} />

                        <button type="submit" disabled={isSubmitting}
                            className={clsx(
                                'w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-3 text-sm font-semibold text-white cursor-pointer',
                                'shadow-[0_4px_20px_var(--color-primary-glow)]',
                                'transition-all duration-200',
                                'hover:bg-[var(--color-primary-hover)] hover:shadow-[0_8px_32px_var(--color-primary-glow)]',
                                'active:scale-[0.98]',
                                'disabled:opacity-50 disabled:cursor-not-allowed',
                            )}>
                            {isSubmitting ? t('common.loading') : t('auth.register.button')}
                        </button>
                    </form>
                </m.div>

                <p className="mt-5 text-center text-sm text-[var(--color-text-muted)]">
                    {t('auth.register.has_account')}{' '}
                    <Link to="/login" className="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors">
                        {t('auth.register.sign_in')}
                    </Link>
                </p>
            </m.div>
        </div>
    );
}

function RegField({ id, type, value, label, error, onChange, focused, onFocus, onBlur, auto }: {
    id: string; type: string; value: string; label: string; error?: string; auto?: string;
    onChange: (v: string) => void; focused: boolean; onFocus: () => void; onBlur: () => void;
}) {
    return (
        <div>
            <label htmlFor={id} className={clsx(
                'mb-1.5 block text-xs font-medium uppercase tracking-wider transition-colors duration-150',
                focused ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-muted)]',
            )}>{label}</label>
            <input id={id} type={type} value={value} required autoComplete={auto}
                onChange={(e) => onChange(e.target.value)} onFocus={onFocus} onBlur={onBlur}
                className={clsx(
                    'w-full rounded-[var(--radius)] border px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
                    'bg-[var(--color-background)] transition-all duration-200',
                    'focus:outline-none focus:ring-1',
                    focused
                        ? 'border-[var(--color-primary)] ring-[var(--color-primary-glow)] shadow-[0_0_12px_var(--color-primary-glow)]'
                        : 'border-[var(--color-border)] ring-transparent hover:border-[var(--color-border-hover)]',
                )} />
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
