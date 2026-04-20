export interface PluginNavEntry {
    id: string;
    label: string;
    icon: string;
    route: string;
}

export interface PluginWidget {
    id: string;
    label: string;
    position: string;
}

export interface PluginServerSidebarEntry {
    id: string;
    label_key: string;
    icon: string;
    route_suffix: string;
    order?: number;
}

export interface PluginManifest {
    id: string;
    name: string;
    version: string;
    nav?: PluginNavEntry[];
    widgets?: PluginWidget[];
    server_sidebar_entries?: PluginServerSidebarEntry[];
    bundle_url?: string;
}

export interface PluginApiResponse {
    data: PluginManifest[];
}
