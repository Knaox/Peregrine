import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

/**
 * Friendly fallback shown when an admin opens /theme-studio on a screen
 * narrower than 1200 px. The studio is split-screen (editor panel +
 * preview iframe) and unusable below that width — rather than rendering
 * a broken layout, we redirect them with a clear explanation + escape
 * link back to the main dashboard.
 *
 * Extracted from `ThemeStudioPage` to keep that file under the project's
 * 300-line cap.
 */
export function ThemeStudioMobileGuard() {
    const { t } = useTranslation();
    return (
        <div className="flex min-h-screen items-center justify-center bg-[var(--color-background)] px-4 text-[var(--color-text-primary)]">
            <div className="max-w-md text-center space-y-3">
                <h1 className="text-lg font-semibold">
                    {t('theme_studio.mobile_not_supported_title', 'Theme Studio requires a larger screen')}
                </h1>
                <p className="text-sm text-[var(--color-text-muted)]">
                    {t(
                        'theme_studio.mobile_not_supported_body',
                        'Open this page on a tablet landscape, laptop or desktop with at least 1200 px width.',
                    )}
                </p>
                <Link
                    to="/dashboard"
                    className="inline-flex rounded-[var(--radius)] bg-[var(--color-primary)] px-4 py-2 text-sm text-white"
                >
                    {t('theme_studio.back_to_dashboard', 'Back to Dashboard')}
                </Link>
            </div>
        </div>
    );
}
