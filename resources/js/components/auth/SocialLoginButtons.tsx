import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { AuthProvider, AuthProviderId } from '@/types/AuthProvider';
import { socialRedirectUrl } from '@/services/authApi';

interface SocialLoginButtonsProps {
    providers: AuthProvider[];
}

const ICON: Record<AuthProviderId, string> = {
    shop: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 4.6A1 1 0 005.6 19H19m-9-6a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 11-4 0 2 2 0 014 0z',
    google: 'M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27 3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10 5.35 0 9.25-3.67 9.25-9.09 0-1.15-.15-1.81-.15-1.81z',
    discord: 'M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028 14.09 14.09 0 001.226-1.994.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z',
    linkedin: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
    paymenter: 'M4 4h13a5 5 0 015 5v1a5 5 0 01-5 5h-8v5H4V4zm5 4v3h7a1 1 0 001-1v-1a1 1 0 00-1-1H9z',
};

const COLOR: Record<AuthProviderId, string> = {
    shop: 'var(--color-primary)',
    google: '#4285F4',
    discord: '#5865F2',
    linkedin: '#0A66C2',
    paymenter: '#6366F1',
};

/**
 * Renders one button per enabled OAuth provider in the order returned by
 * /api/auth/providers. Clicking a button navigates to the backend
 * /api/auth/social/{provider}/redirect endpoint, which in turn 302s the
 * browser to the provider's authorize URL.
 */
export function SocialLoginButtons({ providers }: SocialLoginButtonsProps) {
    const { t } = useTranslation();

    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2">
            {providers.map((p) => (
                <a
                    key={p.id}
                    href={socialRedirectUrl(p.id)}
                    className={clsx(
                        'group inline-flex w-full items-center justify-center gap-2',
                        'rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)]',
                        'px-4 py-2.5 text-sm font-medium text-[var(--color-text-primary)] cursor-pointer',
                        'transition-all duration-200',
                        'hover:border-[var(--color-primary)]/50 hover:shadow-[0_0_12px_var(--color-primary-glow)]',
                    )}
                >
                    {p.logo_url ? (
                        <img
                            src={p.logo_url}
                            alt=""
                            className="h-4 w-4 shrink-0 object-contain"
                            aria-hidden="true"
                        />
                    ) : (
                        <svg
                            className="h-4 w-4 shrink-0"
                            viewBox="0 0 24 24"
                            fill={COLOR[p.id]}
                            aria-hidden="true"
                        >
                            <path d={ICON[p.id]} />
                        </svg>
                    )}
                    {t('auth.login.oauth_button', { provider: t(`auth.providers.${p.id}`) })}
                </a>
            ))}
        </div>
    );
}
