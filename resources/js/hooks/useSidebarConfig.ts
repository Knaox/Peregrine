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
        { id: 'overview', label_key: 'server-shell:detail.overview', icon: 'home', enabled: true, route_suffix: '', order: 0 },
        { id: 'console', label_key: 'server-shell:detail.console', icon: 'terminal', enabled: true, route_suffix: '/console', order: 1 },
        { id: 'files', label_key: 'server-shell:detail.files', icon: 'folder', enabled: true, route_suffix: '/files', order: 2 },
        { id: 'databases', label_key: 'server-shell:detail.databases', icon: 'database', enabled: true, route_suffix: '/databases', order: 3 },
        { id: 'backups', label_key: 'server-shell:detail.backups', icon: 'archive', enabled: true, route_suffix: '/backups', order: 4 },
        { id: 'schedules', label_key: 'server-shell:detail.schedules', icon: 'clock', enabled: true, route_suffix: '/schedules', order: 5 },
        { id: 'network', label_key: 'server-shell:detail.network', icon: 'globe', enabled: true, route_suffix: '/network', order: 6 },
        { id: 'sftp', label_key: 'server-shell:detail.sftp', icon: 'key', enabled: true, route_suffix: '/sftp', order: 7 },
    ],
};

/**
 * Map legacy translation keys (pre-i18n-refactor format) to the new
 * namespaced shape so existing rows in `theme_settings.sidebar_config`
 * keep rendering translated labels without a forced DB migration. The
 * keys used to live in the default `translation` namespace as
 * `servers.detail.<id>`; they now live in `server-shell:detail.<id>`.
 *
 * The mapping is also future-proof against admins who hand-edited the
 * sidebar config JSON before the refactor — they get the new label
 * automatically next time their theme loads.
 */
const LEGACY_LABEL_KEY_MAP: Record<string, string> = {
    'servers.detail.overview': 'server-shell:detail.overview',
    'servers.detail.console': 'server-shell:detail.console',
    'servers.detail.files': 'server-shell:detail.files',
    'servers.detail.databases': 'server-shell:detail.databases',
    'servers.detail.backups': 'server-shell:detail.backups',
    'servers.detail.schedules': 'server-shell:detail.schedules',
    'servers.detail.network': 'server-shell:detail.network',
    'servers.detail.sftp': 'server-shell:detail.sftp',
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
        .map((e) => ({
            ...e,
            // Normalize: old rows or hand-edited JSON may have route_suffix=null
            route_suffix: e.route_suffix ?? '',
            // Migrate legacy label keys on read so persisted configs keep working
            label_key: LEGACY_LABEL_KEY_MAP[e.label_key] ?? e.label_key,
        }))
        .sort((a, b) => a.order - b.order);

    return { ...config, entries: sortedEntries };
}
