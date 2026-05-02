import { useState, type FormEvent } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { useAuthStore } from '@/stores/authStore';
import { useBranding } from '@/hooks/useBranding';
import { useInvitationPublic, useAcceptInvitation, useRegisterAndAccept } from '@/hooks/useAcceptInvitation';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import { LoginParticles } from '@/components/LoginParticles';

export function InviteAcceptPage() {
    const { t } = useTranslation();
    const { token } = useParams<{ token: string }>();
    const navigate = useNavigate();
    const { user, isAuthenticated, logout } = useAuthStore();
    const branding = useBranding();

    const { data: invitation, isLoading, isError } = useInvitationPublic(token ?? '');
    const acceptMutation = useAcceptInvitation();
    const registerMutation = useRegisterAndAccept();

    const [activeTab, setActiveTab] = useState<'register' | 'login'>('register');
    const [name, setName] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirm, setPasswordConfirm] = useState('');
    const [error, setError] = useState('');

    if (!token) return <ErrorState message={t('invitations.accept.invalid')} branding={branding} />;
    if (isLoading) return <LoadingState branding={branding} />;
    if (isError || !invitation) return <ErrorState message={t('invitations.accept.expired')} branding={branding} />;
    if (!invitation.is_active) return <ErrorState message={invitation.is_accepted ? t('invitations.accept.already_accepted') : t('invitations.accept.expired')} branding={branding} />;

    const emailMatch = isAuthenticated && user?.email?.toLowerCase() === invitation.email.toLowerCase();
    const emailMismatch = isAuthenticated && !emailMatch;

    const handleAccept = () => {
        setError('');
        acceptMutation.mutate(token, {
            onSuccess: (res) => navigate(`/servers/${res.server_id}`),
            onError: (err) => setError((err as { data?: { error?: string } }).data?.error ?? t('common.error')),
        });
    };

    const handleRegister = (e: FormEvent) => {
        e.preventDefault();
        setError('');
        registerMutation.mutate(
            { token, data: { name, email: invitation.email, password, password_confirmation: passwordConfirm } },
            {
                onSuccess: () => navigate('/dashboard'),
                onError: (err) => setError((err as { data?: { error?: string } }).data?.error ?? t('common.error')),
            },
        );
    };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4"
            style={{ background: 'var(--color-background)' }}>
            <div className="absolute inset-0" style={{
                backgroundImage: 'linear-gradient(-45deg, var(--color-background), #130d1e, var(--color-background), #1a0e24)',
                backgroundSize: '400% 400%', animation: 'gradient-shift 20s ease infinite',
            }} />
            <LoginParticles />

            <m.div initial={{ opacity: 0, y: 24 }} animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }} className="relative z-10 w-full max-w-md">

                {/* Logo */}
                <div className="mb-6 text-center">
                    <img src={branding.logo_url} alt={branding.app_name} className="mx-auto h-10 object-contain mb-3"
                        style={{ filter: 'drop-shadow(0 0 24px rgba(var(--color-primary-rgb), 0.4))' }} />
                </div>

                {/* Card */}
                <div className="rounded-[var(--radius-xl)] p-6 sm:p-8"
                    style={{ background: 'rgba(22, 19, 30, 0.85)', backdropFilter: 'var(--glass-blur)',
                        border: '1px solid rgba(255,255,255,0.06)', boxShadow: '0 20px 60px rgba(0,0,0,0.5)' }}>

                    <h1 className="text-lg font-bold text-[var(--color-text-primary)] mb-1">{t('invitations.accept.title')}</h1>
                    <p className="text-sm text-[var(--color-text-secondary)] mb-5">
                        {t('invitations.accept.invited_to', { server: invitation.server_name, inviter: invitation.inviter_name })}
                    </p>

                    {/* Permissions */}
                    {invitation.permissions.length > 0 && (
                        <div className="mb-5 rounded-[var(--radius)] p-3" style={{ background: 'rgba(255,255,255,0.04)', border: '1px solid rgba(255,255,255,0.06)' }}>
                            <p className="text-xs font-medium text-[var(--color-text-muted)] mb-2">{t('invitations.accept.permissions')}</p>
                            <div className="flex flex-wrap gap-1.5">
                                {invitation.permissions.map((p) => (
                                    <span key={p.key} className="rounded-[var(--radius-sm)] px-2 py-0.5 text-[11px] font-medium"
                                        style={{ background: 'rgba(var(--color-primary-rgb), 0.1)', color: 'var(--color-primary)' }}>
                                        {p.label}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Expiry */}
                    <p className="text-xs text-[var(--color-text-muted)] mb-5">
                        {t('invitations.accept.expires', { date: new Date(invitation.expires_at).toLocaleDateString() })}
                    </p>

                    {/* Error */}
                    {error && (
                        <div className="mb-4 rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-2.5 text-sm text-[var(--color-danger)]">
                            {error}
                        </div>
                    )}

                    {/* Case 1: Authenticated + email match → accept button */}
                    {emailMatch && (
                        <Button variant="primary" isLoading={acceptMutation.isPending} onClick={handleAccept} className="w-full">
                            {t('invitations.accept.button')}
                        </Button>
                    )}

                    {/* Case 2: Authenticated + wrong email */}
                    {emailMismatch && (
                        <div className="text-center space-y-3">
                            <p className="text-sm text-[var(--color-warning)]">
                                {t('invitations.accept.wrong_email', { current: user?.email, expected: invitation.email })}
                            </p>
                            <Button variant="secondary" onClick={() => { void logout(); }} className="w-full">
                                {t('invitations.accept.logout')}
                            </Button>
                        </div>
                    )}

                    {/* Case 3: Not authenticated */}
                    {!isAuthenticated && (
                        <div>
                            {/* Tabs */}
                            <div className="flex gap-2 mb-4">
                                <button type="button" onClick={() => setActiveTab('register')}
                                    className={clsx('flex-1 py-2 text-sm font-medium rounded-[var(--radius)] cursor-pointer transition-colors',
                                        activeTab === 'register' ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)]' : 'text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)]')}>
                                    {t('invitations.accept.create_account')}
                                </button>
                                <button type="button" onClick={() => setActiveTab('login')}
                                    className={clsx('flex-1 py-2 text-sm font-medium rounded-[var(--radius)] cursor-pointer transition-colors',
                                        activeTab === 'login' ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)]' : 'text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)]')}>
                                    {t('invitations.accept.login')}
                                </button>
                            </div>

                            {activeTab === 'register' && (
                                <form onSubmit={handleRegister} className="space-y-3">
                                    <InputField label={t('invitations.accept.email')} value={invitation.email} disabled />
                                    <InputField label={t('invitations.accept.name')} value={name} onChange={setName} required />
                                    <InputField label={t('invitations.accept.password')} type="password" value={password} onChange={setPassword} required />
                                    <InputField label={t('invitations.accept.password_confirm')} type="password" value={passwordConfirm} onChange={setPasswordConfirm} required />
                                    <Button type="submit" variant="primary" isLoading={registerMutation.isPending} className="w-full">
                                        {t('invitations.accept.register_and_accept')}
                                    </Button>
                                </form>
                            )}

                            {activeTab === 'login' && (
                                <div className="text-center space-y-3">
                                    <p className="text-sm text-[var(--color-text-secondary)]">{t('invitations.accept.login_hint')}</p>
                                    <Link to={`/login?redirect=/invite/${token}`}
                                        className="inline-block w-full rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white text-center transition-colors hover:bg-[var(--color-primary-hover)]">
                                        {t('invitations.accept.go_to_login')}
                                    </Link>

                                    {/* SSO placeholder — hidden behind feature flag */}
                                    {/* TODO: SSO flow — enable when features.sso is true */}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </m.div>
        </div>
    );
}

function InputField({ label, type = 'text', value, onChange, disabled, required }: {
    label: string; type?: string; value: string; onChange?: (v: string) => void; disabled?: boolean; required?: boolean;
}) {
    return (
        <div>
            <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-[var(--color-text-muted)]">{label}</label>
            <input type={type} value={value} required={required} disabled={disabled}
                onChange={onChange ? (e) => onChange(e.target.value) : undefined}
                className={clsx(
                    'w-full rounded-[var(--radius)] border px-4 py-2.5 text-sm text-[var(--color-text-primary)]',
                    'bg-[var(--color-background)] border-[var(--color-border)] transition-all duration-200',
                    'focus:outline-none focus:ring-1 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary-glow)]',
                    disabled && 'opacity-60 cursor-not-allowed',
                )} />
        </div>
    );
}

function LoadingState({ branding }: { branding: { logo_url: string; app_name: string } }) {
    return (
        <div className="flex min-h-screen items-center justify-center" style={{ background: 'var(--color-background)' }}>
            <div className="text-center">
                <img src={branding.logo_url} alt={branding.app_name} className="mx-auto h-10 mb-4 object-contain" />
                <Spinner size="lg" />
            </div>
        </div>
    );
}

function ErrorState({ message, branding }: { message: string; branding: { logo_url: string; app_name: string } }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen items-center justify-center px-4" style={{ background: 'var(--color-background)' }}>
            <div className="text-center max-w-sm">
                <img src={branding.logo_url} alt={branding.app_name} className="mx-auto h-10 mb-6 object-contain" />
                <p className="text-[var(--color-text-secondary)] mb-4">{message}</p>
                <Link to="/dashboard" className="text-sm text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors">
                    {t('invitations.accept.go_home')}
                </Link>
            </div>
        </div>
    );
}
