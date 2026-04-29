import { useResolvedTheme } from '@/hooks/useResolvedTheme';

export interface CardConfig {
    layout: string;
    columns: { desktop: number; tablet: number; mobile: number };
    show_egg_icon: boolean;
    show_egg_name: boolean;
    show_plan_name: boolean;
    show_status_badge: boolean;
    show_stats_bars: boolean;
    show_quick_actions: boolean;
    show_ip_port: boolean;
    show_uptime: boolean;
    card_style: string;
    sort_default: string;
    group_by: string;
}

const DEFAULTS: CardConfig = {
    layout: 'grid',
    columns: { desktop: 3, tablet: 2, mobile: 1 },
    show_egg_icon: true,
    show_egg_name: true,
    show_plan_name: true,
    show_status_badge: true,
    show_stats_bars: true,
    show_quick_actions: true,
    show_ip_port: false,
    show_uptime: false,
    card_style: 'glass',
    sort_default: 'name',
    group_by: 'none',
};

/**
 * Card config consumer. Goes through `useResolvedTheme()` so the Theme
 * Studio's preview iframe sees postMessage-driven changes — not just the
 * cached API response (which was stale in preview mode).
 */
export function useCardConfig(): CardConfig {
    const theme = useResolvedTheme();
    return theme?.card_config ?? DEFAULTS;
}
