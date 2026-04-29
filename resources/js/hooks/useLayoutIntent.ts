import { useQuery } from '@tanstack/react-query';
import { request } from '@/services/http';
import type { ThemeData } from '@/components/ThemeProvider';

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
 * Reuses the `['theme']` query key so the read deduplicates with
 * ThemeProvider — zero extra network call.
 */
export function useLayoutIntent(scene: 'console' | 'files' | 'dashboard'): LayoutIntent {
    const { data } = useQuery({
        queryKey: ['theme'],
        queryFn: () => request<ThemeData>('/api/settings/theme'),
        staleTime: 60 * 60 * 1000,
    });
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
