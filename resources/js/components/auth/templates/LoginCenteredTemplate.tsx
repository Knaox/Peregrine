import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useBranding } from '@/hooks/useBranding';
import { LoginParticles } from '@/components/LoginParticles';
import { LoginFormCard } from '@/components/auth/LoginFormCard';
import { LoginBackgroundLayer } from '@/components/auth/LoginBackgroundLayer';
import type { LoginBackgroundPattern } from '@/components/ThemeProvider';

interface LoginCenteredTemplateProps {
    pattern: LoginBackgroundPattern;
}

/**
 * Default login template — pattern background, glow halo, glass card
 * centered on screen. Particles overlay only on the `gradient` pattern
 * (the stack would feel cluttered on top of mesh/aurora/orbs).
 */
export function LoginCenteredTemplate({ pattern }: LoginCenteredTemplateProps) {
    const { t } = useTranslation();
    const branding = useBranding();
    const showParticles = pattern === 'gradient' || pattern === 'none';

    return (
        <div
            className="relative flex min-h-screen items-center justify-center overflow-hidden px-4"
            style={{ background: 'var(--color-background)' }}
        >
            <LoginBackgroundLayer pattern={pattern} />
            {showParticles && <LoginParticles />}
            <div
                className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full pointer-events-none"
                style={{
                    background:
                        'radial-gradient(circle, rgba(var(--color-primary-rgb), 0.15) 0%, transparent 70%)',
                    filter: 'blur(60px)',
                }}
            />

            <m.div
                initial={{ opacity: 0, y: 24 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: [0.4, 0, 0.2, 1] }}
                className="relative z-10 w-full max-w-sm"
            >
                <m.div
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ delay: 0.15, duration: 0.4 }}
                    className="mb-6 text-center"
                >
                    <div className="flex justify-center mb-4">
                        <img
                            src={branding.logo_url}
                            alt={branding.app_name}
                            className="object-contain"
                            style={{
                                height: branding.logo_height ?? 48,
                                maxHeight: 64,
                                maxWidth: 220,
                                filter: 'drop-shadow(0 0 24px rgba(var(--color-primary-rgb), 0.4))',
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
                </m.div>

                <m.div
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.25, duration: 0.4 }}
                >
                    <LoginFormCard variant="glass" />
                </m.div>
            </m.div>
        </div>
    );
}
