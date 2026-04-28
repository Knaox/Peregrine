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
    /**
     * Optional egg whitelist. When set and non-empty, the entry is only
     * rendered for servers whose `egg_id` is included. When omitted or empty
     * the entry is shown on every server (legacy behaviour). Generic plugin-
     * system feature — no plugin is hardcoded to use it.
     */
    requires_egg_ids?: number[];
}

export interface PluginServerHomeSection {
    /** Stable identifier the plugin uses when calling registerServerHomeSection. */
    id: string;
    /** Render position relative to the core sections. Lower values render earlier. */
    order?: number;
    /** Optional Peregrine permission key (e.g. `startup.read`) gating visibility. Owners always see it. */
    required_permission?: string;
}

export interface PluginSettingsSchemaField {
    key: string;
    type: 'text' | 'number' | 'toggle' | 'select' | 'textarea';
    label: string;
    default?: unknown;
    options?: Record<string, string>;
}

export interface PluginManifest {
    id: string;
    name: string;
    version: string;
    nav?: PluginNavEntry[];
    widgets?: PluginWidget[];
    server_sidebar_entries?: PluginServerSidebarEntry[];
    server_home_sections?: PluginServerHomeSection[];
    settings_schema?: PluginSettingsSchemaField[];
    /**
     * Persisted admin-configured settings, keyed by `settings_schema.key`.
     * Plugin bundles read this to honour admin choices (toggles, selects).
     */
    settings?: Record<string, unknown>;
    bundle_url?: string;
}

export interface PluginApiResponse {
    data: PluginManifest[];
}
