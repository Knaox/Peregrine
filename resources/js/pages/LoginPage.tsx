import { useResolvedTheme } from '@/hooks/useResolvedTheme';
import { LoginCenteredTemplate } from '@/components/auth/templates/LoginCenteredTemplate';
import { LoginSplitTemplate } from '@/components/auth/templates/LoginSplitTemplate';
import { LoginOverlayTemplate } from '@/components/auth/templates/LoginOverlayTemplate';
import { LoginMinimalTemplate } from '@/components/auth/templates/LoginMinimalTemplate';

/**
 * Login dispatcher — picks one of 4 visual templates based on the
 * `theme.data.login.template` admin setting. The default `centered`
 * template matches the original LoginPage so installs without the new
 * setting see no change.
 *
 * Uses `useResolvedTheme()` so the studio's live preview (postMessage
 * payload) takes effect immediately while the iframe re-renders, with
 * the regular API as the fallback for the live app.
 */
export function LoginPage() {
    const theme = useResolvedTheme();

    const login = theme?.data.login ?? {
        template: 'centered' as const,
        background_image: '',
        background_blur: 0,
        background_pattern: 'gradient' as const,
    };

    switch (login.template) {
        case 'split':
            return (
                <LoginSplitTemplate
                    backgroundImage={login.background_image}
                    backgroundBlur={login.background_blur}
                    pattern={login.background_pattern}
                />
            );
        case 'overlay':
            return (
                <LoginOverlayTemplate
                    backgroundImage={login.background_image}
                    backgroundBlur={login.background_blur}
                />
            );
        case 'minimal':
            return <LoginMinimalTemplate pattern={login.background_pattern} />;
        case 'centered':
        default:
            return <LoginCenteredTemplate pattern={login.background_pattern} />;
    }
}
