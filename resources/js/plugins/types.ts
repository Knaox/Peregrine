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
     * Optional Peregrine permission key (e.g. `minecraftmods.read`) gating
     * visibility. Owners always see the entry; subusers need this grant.
     * Mirrors `PluginServerHomeSection.required_permission`.
     */
    required_permission?: string;
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
    /**
     * Where to render relative to the core stats. `'before_stats'` puts the
     * section above the stats (prominent); omitted = grouped after the core
     * sections (the default). Generic — no plugin is hardcoded in core.
     */
    placement?: 'before_stats';
    /** Optional Peregrine permission key (e.g. `startup.read`) gating visibility. Owners always see it. */
    required_permission?: string;
    /**
     * Optional egg whitelist — same semantics as `PluginServerSidebarEntry`.
     * When set and non-empty, the section is only rendered for servers whose
     * `egg_id` is included. When omitted or empty, the section renders on
     * every server. Useful for plugins that only have data for specific eggs
     * (e.g. egg-config-editor populates this dynamically from DB so the card
     * doesn't even mount on servers whose egg has no declared config files).
     */
    requires_egg_ids?: number[];
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
    /**
     * Short content-aware hash of the plugin's `frontend/i18n/*.json` files,
     * computed server-side from their mtimes. Used as an extra cache-bust
     * query param on the i18n endpoint so an operator can ship translation
     * fixes by editing the JSON alone — without bumping `plugin.json` and
     * without lowering the 1-hour `Cache-Control: max-age` set by the
     * plugin i18n controller. `null` when the plugin ships no i18n folder.
     */
    i18n_etag?: string | null;
}

export interface PluginApiResponse {
    data: PluginManifest[];
}
