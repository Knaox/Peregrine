import { useQuery } from '@tanstack/react-query';
import { request } from '@/services/http';

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

interface ThemeResponse {
    sidebar_config: SidebarConfig;
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

export function useSidebarConfig(): SidebarConfig {
    const { data } = useQuery({
        queryKey: ['theme'],
        queryFn: () => request<ThemeResponse>('/api/settings/theme'),
        staleTime: 60 * 60 * 1000,
    });

    if (!data?.sidebar_config) return DEFAULTS;

    const config = data.sidebar_config;
    const sortedEntries = [...config.entries]
        .filter((e) => e.enabled)
        .sort((a, b) => a.order - b.order);

    return { ...config, entries: sortedEntries };
}
