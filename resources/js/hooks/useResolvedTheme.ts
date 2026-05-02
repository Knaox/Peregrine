import { useThemeContext } from '@/components/ThemeProvider';
import type { ThemeData } from '@/components/ThemeProvider';

/**
 * Reads the currently active theme — same payload that ThemeProvider
 * already resolved (preview-aware: postMessage in the studio iframe,
 * API everywhere else).
 *
 * Centralised through React Context so every consumer (useCardConfig,
 * useSidebarConfig, LoginPage, ...) sees the SAME state. This removes
 * the per-consumer postMessage bridge that previously duplicated
 * listeners and produced inconsistent update timing across components.
 */
export function useResolvedTheme(): ThemeData | null {
    return useThemeContext();
}
