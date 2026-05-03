import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useBranding } from '@/hooks/useBranding';
import { LoginFormCard } from '@/components/auth/LoginFormCard';
import { LoginBackgroundCarousel } from '@/components/auth/LoginBackgroundCarousel';

interface LoginOverlayTemplateProps {
    backgroundImage: string;
    backgroundBlur: number;
    backgroundImages?: string[];
    carouselEnabled?: boolean;
    carouselInterval?: number;
    carouselRandom?: boolean;
    backgroundOpacity?: number;
}

/**
 * Fullscreen image template — background image (or carousel) fills the
 * viewport, the form floats over it as a glass card. Falls back to a brand
 * gradient if neither single image nor carousel is set.
 */
export function LoginOverlayTemplate({
    backgroundImage,
    backgroundBlur,
    backgroundImages = [],
    carouselEnabled = false,
    carouselInterval = 6000,
    carouselRandom = true,
    backgroundOpacity = 100,
}: LoginOverlayTemplateProps) {
    const { t } = useTranslation();
    const branding = useBranding();
    const blur = Math.max(0, Math.min(24, backgroundBlur));
    const useCarousel = carouselEnabled && backgroundImages.length > 0;

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4">
            {useCarousel ? (
                <LoginBackgroundCarousel
                    images={backgroundImages}
                    interval={carouselInterval}
                    random={carouselRandom}
                    blur={blur}
                    opacity={backgroundOpacity}
                />
            ) : (
                <div
                    className="absolute inset-0"
                    style={{
                        background: backgroundImage
                            ? `url("${backgroundImage}") center/cover no-repeat, linear-gradient(135deg, var(--color-primary), var(--color-secondary))`
                            : 'linear-gradient(135deg, var(--color-primary), var(--color-secondary))',
                        filter: blur > 0 ? `blur(${blur}px)` : undefined,
                        transform: blur > 0 ? 'scale(1.05)' : undefined,
                        opacity: backgroundImage ? backgroundOpacity / 100 : 1,
                    }}
                    aria-hidden
                />
            )}
            {/* Strong scrim so the form stays legible on any image */}
            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(180deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.6) 100%)',
                }}
                aria-hidden
            />

            <m.div
                initial={{ opacity: 0, y: 24, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ duration: 0.45, ease: [0.4, 0, 0.2, 1] }}
                className="relative z-10 w-full max-w-sm sm:max-w-md"
            >
                <div className="mb-6 text-center">
                    <div className="flex justify-center mb-3">
                        <img
                            src={branding.logo_url}
                            alt={branding.app_name}
                            className="object-contain"
                            style={{
                                height: branding.logo_height ?? 44,
                                maxHeight: 56,
                                maxWidth: 200,
                                filter: 'drop-shadow(0 4px 12px rgba(0,0,0,0.4))',
                            }}
                        />
                    </div>
                    {branding.show_app_name !== false && (
                        <h1 className="text-xl font-semibold text-white drop-shadow-md">
                            {branding.app_name}
                        </h1>
                    )}
                    <p className="mt-1 text-sm text-white/70">{t('auth.login.title')}</p>
                </div>

                <LoginFormCard variant="glass" />
            </m.div>
        </div>
    );
}
