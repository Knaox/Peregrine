import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useBranding } from '@/hooks/useBranding';
import { LoginFormCard } from '@/components/auth/LoginFormCard';
import { LoginBackgroundLayer } from '@/components/auth/LoginBackgroundLayer';
import type { LoginBackgroundPattern } from '@/components/ThemeProvider';

interface LoginMinimalTemplateProps {
    pattern: LoginBackgroundPattern;
}

/**
 * Minimal template — solid (or patterned) background, logo + form, no
 * decorative glow or particles. For setups that want a quiet, professional
 * first impression. Patterns still apply if the admin selects one.
 */
export function LoginMinimalTemplate({ pattern }: LoginMinimalTemplateProps) {
    const { t } = useTranslation();
    const branding = useBranding();

    return (
        <div
            className="relative flex min-h-screen items-center justify-center overflow-hidden px-4"
            style={{ background: 'var(--color-background)' }}
        >
            <LoginBackgroundLayer pattern={pattern} />
            <m.div
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.35 }}
                className="relative z-10 w-full max-w-sm"
            >
                <div className="mb-8 text-center">
                    <div className="flex justify-center mb-4">
                        <img
                            src={branding.logo_url}
                            alt={branding.app_name}
                            className="object-contain"
                            style={{
                                height: branding.logo_height ?? 44,
                                maxHeight: 56,
                                maxWidth: 200,
                            }}
                        />
                    </div>
                    {branding.show_app_name !== false && (
                        <h1 className="text-xl font-semibold text-[var(--color-text-primary)]">
                            {branding.app_name}
                        </h1>
                    )}
                    <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                        {t('auth.login.title')}
                    </p>
                </div>

                <LoginFormCard variant="solid" />
            </m.div>
        </div>
    );
}
