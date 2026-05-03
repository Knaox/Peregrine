import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';

interface ThemeStudioErrorScreenProps {
    /** Optional raw error message bubbled up from the failed /state fetch
     *  (network error, 500, etc.). Shown in a small monospaced line so
     *  the admin can paste it into a bug report. Hidden when null. */
    loadError: string | null;
    onRetry: () => void;
}

/**
 * Friendly fallback shown when the initial `/api/admin/theme/state`
 * fetch fails (network down, 500, expired session, …). Offers a
 * "back to dashboard" escape hatch + a "retry" button that re-fires
 * the query. Extracted from `ThemeStudioPage` to keep that file under
 * the project's 300-line cap.
 */
export function ThemeStudioErrorScreen({ loadError, onRetry }: ThemeStudioErrorScreenProps) {
    const { t } = useTranslation();
    return (
        <div className="flex h-screen items-center justify-center bg-[var(--color-background)] px-4 text-[var(--color-text-primary)]">
            <div className="max-w-md text-center space-y-3">
                <h1 className="text-lg font-semibold">
                    {t('theme_studio.error.title', 'Theme Studio could not load')}
                </h1>
                <p className="text-sm text-[var(--color-text-muted)]">
                    {t(
                        'theme_studio.error.body',
                        'The server returned an error fetching your theme settings. Check that you are still signed in as an admin and try again.',
                    )}
                </p>
                {loadError && (
                    <p className="text-xs font-mono text-[var(--color-text-muted)]/70">{loadError}</p>
                )}
                <div className="flex justify-center gap-2 pt-2">
                    <Link
                        to="/dashboard"
                        className="inline-flex items-center rounded-[var(--radius)] border border-[var(--color-border-hover)] bg-[var(--color-surface)] px-4 py-2 text-sm hover:bg-[var(--color-surface-hover)]"
                    >
                        {t('theme_studio.error.back', 'Back to dashboard')}
                    </Link>
                    <Button variant="primary" size="sm" onClick={onRetry}>
                        {t('theme_studio.error.retry', 'Retry')}
                    </Button>
                </div>
            </div>
        </div>
    );
}
