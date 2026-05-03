import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useBranding } from '@/hooks/useBranding';
import { LoginFormCard } from '@/components/auth/LoginFormCard';
import { LoginBackgroundLayer } from '@/components/auth/LoginBackgroundLayer';
import { LoginBackgroundCarousel } from '@/components/auth/LoginBackgroundCarousel';
import type { LoginBackgroundPattern } from '@/components/ThemeProvider';

interface LoginSplitTemplateProps {
    backgroundImage: string;
    backgroundBlur: number;
    pattern: LoginBackgroundPattern;
    backgroundImages?: string[];
    carouselEnabled?: boolean;
    carouselInterval?: number;
    carouselRandom?: boolean;
    backgroundOpacity?: number;
}

/**
 * Split-screen template — form on the left half (with optional pattern),
 * image (or carousel) on the right. Mobile collapses to the centered form
 * (image hidden) so it stays usable.
 */
export function LoginSplitTemplate({
    backgroundImage,
    backgroundBlur,
    pattern,
    backgroundImages = [],
    carouselEnabled = false,
    carouselInterval = 6000,
    carouselRandom = true,
    backgroundOpacity = 100,
}: LoginSplitTemplateProps) {
    const { t } = useTranslation();
    const branding = useBranding();
    const blur = Math.max(0, Math.min(24, backgroundBlur));
    const useCarousel = carouselEnabled && backgroundImages.length > 0;

    return (
        <div
            className="relative flex min-h-screen flex-col md:flex-row"
            style={{ background: 'var(--color-background)' }}
        >
            {/* Left — form half. Pattern is contained to this half so the
                image side keeps its natural look. */}
            <div className="relative flex flex-1 items-center justify-center overflow-hidden px-6 py-10 md:px-10">
                <LoginBackgroundLayer pattern={pattern} />
                <m.div
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.4, ease: [0.4, 0, 0.2, 1] }}
                    className="relative z-10 w-full max-w-sm sm:max-w-md"
                >
                    <div className="mb-6">
                        <div className="mb-3 flex items-center gap-3">
                            <img
                                src={branding.logo_url}
                                alt={branding.app_name}
                                className="object-contain"
                                style={{
                                    height: branding.logo_height ?? 40,
                                    maxHeight: 48,
                                    maxWidth: 160,
                                }}
                            />
                            {branding.show_app_name !== false && (
                                <span className="text-lg font-semibold text-[var(--color-text-primary)]">
                                    {branding.app_name}
                                </span>
                            )}
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight text-[var(--color-text-primary)]">
                            {t('auth.login.title')}
                        </h1>
                    </div>
                    <LoginFormCard variant="flush" className="!p-0" />
                </m.div>
            </div>

            {/* Right — image / carousel (hidden on mobile). The brand gradient
                is layered BEHIND the image so a broken / 404 image gracefully
                falls back to the gradient instead of showing as solid black. */}
            <div className="relative hidden flex-1 overflow-hidden md:block">
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
                            opacity: backgroundImage ? backgroundOpacity / 100 : 1,
                        }}
                        aria-hidden
                    />
                )}
                <div
                    className="absolute inset-0"
                    style={{
                        background:
                            'linear-gradient(135deg, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0.05) 100%)',
                    }}
                />
            </div>
        </div>
    );
}
