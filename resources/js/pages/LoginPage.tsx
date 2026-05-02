import { useResolvedTheme } from '@/hooks/useResolvedTheme';
import { LoginCenteredTemplate } from '@/components/auth/templates/LoginCenteredTemplate';
import { LoginSplitTemplate } from '@/components/auth/templates/LoginSplitTemplate';
import { LoginOverlayTemplate } from '@/components/auth/templates/LoginOverlayTemplate';
import { LoginMinimalTemplate } from '@/components/auth/templates/LoginMinimalTemplate';

const LOGIN_DEFAULTS = {
    template: 'centered' as const,
    background_image: '',
    background_blur: 0,
    background_pattern: 'gradient' as const,
    background_images: [] as string[],
    carousel_enabled: false,
    carousel_interval: 6000,
    carousel_random: true,
    background_opacity: 100,
};

/**
 * Login dispatcher — picks one of 4 visual templates based on the
 * `theme.data.login.template` admin setting. The default `centered`
 * template matches the original LoginPage so installs without the new
 * setting see no change.
 */
export function LoginPage() {
    const theme = useResolvedTheme();
    const login = theme?.data.login ?? LOGIN_DEFAULTS;

    switch (login.template) {
        case 'split':
            return (
                <LoginSplitTemplate
                    backgroundImage={login.background_image}
                    backgroundBlur={login.background_blur}
                    pattern={login.background_pattern}
                    backgroundImages={login.background_images}
                    carouselEnabled={login.carousel_enabled}
                    carouselInterval={login.carousel_interval}
                    carouselRandom={login.carousel_random}
                    backgroundOpacity={login.background_opacity}
                />
            );
        case 'overlay':
            return (
                <LoginOverlayTemplate
                    backgroundImage={login.background_image}
                    backgroundBlur={login.background_blur}
                    backgroundImages={login.background_images}
                    carouselEnabled={login.carousel_enabled}
                    carouselInterval={login.carousel_interval}
                    carouselRandom={login.carousel_random}
                    backgroundOpacity={login.background_opacity}
                />
            );
        case 'minimal':
            return <LoginMinimalTemplate pattern={login.background_pattern} />;
        case 'centered':
        default:
            return <LoginCenteredTemplate pattern={login.background_pattern} />;
    }
}
