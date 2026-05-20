import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import clsx from 'clsx';
import { useAuthProviders } from '@/hooks/useAuthProviders';
import { useResolvedTheme } from '@/hooks/useResolvedTheme';
import { SocialLoginButtons } from '@/components/auth/SocialLoginButtons';
import { LocalLoginForm } from '@/components/auth/LocalLoginForm';
import { useNamespace } from '@/i18n/useNamespace';

interface LoginFormCardProps {
    /** Visual chrome around the form. Defaults to a glass card with blur. */
    variant?: 'glass' | 'solid' | 'flush';
    className?: string;
}

/**
 * Login card orchestrator: social buttons + local form + register link.
 * Reused across all 4 LoginTemplate components — only the surrounding
 * background/layout differs per template.
 *
 * "Nice OAuth" mode (theme.data.login.oauth_first): the card leads with the
 * OAuth providers and tucks the local email/password form behind a "sign in
 * locally" text link, so shop-sourced users who never set a local password
 * aren't nudged into a dead-end form. The register link stays visible. The
 * mode degrades to the classic combined layout whenever there is no OAuth
 * provider to lead with, or local login is disabled — never stranding a user.
 */
export function LoginFormCard({ variant = 'glass', className }: LoginFormCardProps) {
    useNamespace(["auth-social"] as const);
    const { t } = useTranslation();
    const [searchParams, setSearchParams] = useSearchParams();
    const providers = useAuthProviders();
    const theme = useResolvedTheme();

    const [providerError, setProviderError] = useState('');
    // OAuth-first progressive disclosure. `showLocal` reveals the email/password
    // form; `localExpanded` drops the clip-mask once the open animation settles
    // so the inputs' focus glow isn't cropped by overflow-hidden.
    const [showLocal, setShowLocal] = useState(false);
    const [localExpanded, setLocalExpanded] = useState(false);

    const localEnabled = providers.data?.local_enabled ?? true;
    const localRegistrationEnabled = providers.data?.local_registration_enabled ?? true;
    const canonicalProvider = providers.data?.canonical_provider ?? null;
    const canonicalRegisterUrl = providers.data?.canonical_register_url ?? null;
    const enabledProviders = providers.data?.providers ?? [];
    const canRegisterLocal = localEnabled && localRegistrationEnabled;
    const canRegisterCanonical = canonicalRegisterUrl !== null;
    const canonicalProviderLabel =
        canonicalProvider !== null ? t(`auth-social:providers.${canonicalProvider}`) : '';

    // Engage "Nice OAuth" only when the admin enabled it AND there is at least
    // one OAuth provider to lead with AND local login is available. Otherwise
    // hiding the form would leave users with no way in, so we fall back to the
    // classic combined layout.
    const oauthFirst =
        (theme?.data.login?.oauth_first ?? false) && enabledProviders.length > 0 && localEnabled;
    // Local form is shown outright in classic mode; in OAuth-first mode it waits
    // until the user opts into it via the "sign in locally" link.
    const localVisible = localEnabled && (!oauthFirst || showLocal);
    const showDivider = enabledProviders.length > 0;

    useEffect(() => {
        const errorKey = searchParams.get('error');
        if (errorKey === null) return;
        const translated = t(errorKey, { provider: canonicalProviderLabel });
        setProviderError(
            translated === errorKey ? t('auth-social:oauth_failed') : translated,
        );
        const next = new URLSearchParams(searchParams);
        next.delete('error');
        setSearchParams(next, { replace: true });
    }, [searchParams, setSearchParams, t, canonicalProviderLabel]);

    const cardClasses = clsx(
        'rounded-[var(--radius-xl)] p-4 sm:p-6 md:p-8',
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
                                    aria-label={t('common:close')}
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

                {/* Classic layout — OAuth (if any) and the local form together. */}
                {!oauthFirst && localVisible && <LocalLoginForm showDivider={showDivider} />}

                {/* "Nice OAuth" — OAuth leads; the local form hides behind a link. */}
                {oauthFirst && (
                    <AnimatePresence initial={false} mode="wait">
                        {showLocal ? (
                            <m.div
                                key="local"
                                initial={{ opacity: 0, height: 0 }}
                                animate={{ opacity: 1, height: 'auto' }}
                                transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
                                onAnimationComplete={() => setLocalExpanded(true)}
                                className={clsx('space-y-4', !localExpanded && 'overflow-hidden')}
                            >
                                <LocalLoginForm showDivider={showDivider} />
                            </m.div>
                        ) : (
                            <m.div
                                key="reveal"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{ duration: 0.15 }}
                            >
                                <button
                                    type="button"
                                    onClick={() => setShowLocal(true)}
                                    className="group flex w-full items-center justify-center gap-1.5 py-3 text-sm font-medium text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-primary)] cursor-pointer"
                                >
                                    <span className="underline decoration-[var(--color-border)] decoration-1 underline-offset-4 transition-colors group-hover:decoration-[var(--color-primary)]">
                                        {t('auth-login:use_local')}
                                    </span>
                                    <svg
                                        className="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-y-0.5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth={2}
                                        aria-hidden="true"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </m.div>
                        )}
                    </AnimatePresence>
                )}
            </div>

            {(canRegisterLocal || canRegisterCanonical) && (
                <p className="mt-5 text-center text-sm text-[var(--color-text-muted)]">
                    {t('auth-login:no_account')}{' '}
                    {canRegisterCanonical ? (
                        <>
                            <a
                                href={canonicalRegisterUrl as string}
                                className="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors"
                            >
                                {t('auth-login:create_account_canonical', { provider: canonicalProviderLabel })}
                            </a>
                            {canRegisterLocal && (
                                <>
                                    {' · '}
                                    <Link
                                        to="/register"
                                        className="font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-primary)] transition-colors"
                                    >
                                        {t('auth-login:create_account_local')}
                                    </Link>
                                </>
                            )}
                        </>
                    ) : (
                        <Link
                            to="/register"
                            className="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)] transition-colors"
                        >
                            {t('auth-login:create_account')}
                        </Link>
                    )}
                </p>
            )}
        </div>
    );
}
