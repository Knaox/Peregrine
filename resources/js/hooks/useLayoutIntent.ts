import { useResolvedTheme } from '@/hooks/useResolvedTheme';

export type LayoutIntent = {
    /** Page wants to break out of the container max-width. */
    fullwidth: boolean;
    /** Dashboard wants the grid to expand to 4 columns on ultra-wide. */
    expanded: boolean;
};

const NEUTRAL: LayoutIntent = { fullwidth: false, expanded: false };

/**
 * Per-page layout intent derived from the admin's `theme_page_*` settings.
 * Each scene reads only the toggle that applies to it.
 *
 * Goes through `useResolvedTheme` (the single source of truth exposed by
 * ThemeProvider) so the studio preview iframe sees the draft toggles via
 * postMessage instead of the cached `/api/settings/theme` response.
 */
export function useLayoutIntent(scene: 'console' | 'files' | 'dashboard'): LayoutIntent {
    const data = useResolvedTheme();
    const overrides = data?.data.page_overrides;
    if (!overrides) return NEUTRAL;

    switch (scene) {
        case 'console':
            return { fullwidth: overrides.console_fullwidth, expanded: false };
        case 'files':
            return { fullwidth: overrides.files_fullwidth, expanded: false };
        case 'dashboard':
            return { fullwidth: false, expanded: overrides.dashboard_expanded };
        default:
            return NEUTRAL;
    }
}
