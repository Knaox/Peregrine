import { useResolvedTheme } from '@/hooks/useResolvedTheme';

export interface SidebarEntry {
    id: string;
    label_key: string;
    icon: string;
    enabled: boolean;
    route_suffix: string;
    order: number;
}

export interface SidebarConfig {
    position: string;
    style: string;
    show_server_status: boolean;
    show_server_name: boolean;
    entries: SidebarEntry[];
}

const DEFAULTS: SidebarConfig = {
    position: 'left',
    style: 'default',
    show_server_status: true,
    show_server_name: true,
    entries: [
        { id: 'overview', label_key: 'servers.detail.overview', icon: 'home', enabled: true, route_suffix: '', order: 0 },
        { id: 'console', label_key: 'servers.detail.console', icon: 'terminal', enabled: true, route_suffix: '/console', order: 1 },
        { id: 'files', label_key: 'servers.detail.files', icon: 'folder', enabled: true, route_suffix: '/files', order: 2 },
        { id: 'databases', label_key: 'servers.detail.databases', icon: 'database', enabled: true, route_suffix: '/databases', order: 3 },
        { id: 'backups', label_key: 'servers.detail.backups', icon: 'archive', enabled: true, route_suffix: '/backups', order: 4 },
        { id: 'schedules', label_key: 'servers.detail.schedules', icon: 'clock', enabled: true, route_suffix: '/schedules', order: 5 },
        { id: 'network', label_key: 'servers.detail.network', icon: 'globe', enabled: true, route_suffix: '/network', order: 6 },
        { id: 'sftp', label_key: 'servers.detail.sftp', icon: 'key', enabled: true, route_suffix: '/sftp', order: 7 },
    ],
};

/**
 * Sidebar config consumer. Goes through `useResolvedTheme()` so the
 * Theme Studio's preview iframe reflects postMessage-driven changes
 * (entry reorder, on/off toggles, position/style swaps) — not just the
 * cached API response (which was stale in preview mode).
 */
export function useSidebarConfig(): SidebarConfig {
    const theme = useResolvedTheme();
    if (!theme?.sidebar_config) return DEFAULTS;

    const config = theme.sidebar_config;
    const sortedEntries = [...config.entries]
        .filter((e) => e.enabled)
        // Normalize: old rows or hand-edited JSON may have route_suffix=null
        .map((e) => ({ ...e, route_suffix: e.route_suffix ?? '' }))
        .sort((a, b) => a.order - b.order);

    return { ...config, entries: sortedEntries };
}
