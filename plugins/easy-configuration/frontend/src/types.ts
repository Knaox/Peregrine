/** Localised label/description, e.g. `{ fr: "Difficulté", en: "Difficulty" }`. */
export type LocaleLabel = Record<string, string>;

export type DisplayType =
    | 'boolean'
    | 'slider'
    | 'select'
    | 'multiselect'
    | 'text'
    | 'number'
    | 'textarea'
    | 'color';

/** A select/multiselect option. */
export interface ParamOption {
    value: string;
    label?: LocaleLabel;
}

/** Display-type-specific configuration block from the template. */
export interface ParamConfig {
    min?: number;
    max?: number;
    step?: number;
    suffix?: string;
    float?: boolean;
    options?: ParamOption[];
    separator?: string;
    true_value?: string;
    false_value?: string;
    regex?: string;
    max_length?: number;
    format?: string;
    default?: string | number | boolean;
}

/** Boost overlay state for a single parameter (populated from P8 onwards). */
export interface ParamBoost {
    id: number;
    status: 'pending' | 'active';
    multiplier: number;
    /** When true the parameter is divided by the multiplier (deboost) instead of multiplied. */
    invert?: boolean;
    effective_value: string;
    start_at: string;
    end_at: string;
}

export interface ConfigParam {
    key: string;
    section: string | null;
    display_type: DisplayType;
    config: ParamConfig;
    label: LocaleLabel | null;
    description: LocaleLabel | null;
    value: string;
    inferred: boolean;
    /** 0-based occurrence index for a key that repeats in the file (e.g. ARK ConfigOverride* lines). */
    occurrence?: number;
    /** When set, the parameter is linked to this Pelican env variable. */
    env_var?: string | null;
    boost?: ParamBoost | null;
}

export interface ConfigFile {
    id: string;
    label: LocaleLabel | null;
    path: string;
    format: string;
    exists: boolean;
    sectioned: boolean;
    /** Friendly FR/EN names per native section (ini/toml), keyed by raw name. */
    section_labels?: Record<string, LocaleLabel> | null;
    parameters: ConfigParam[];
}

export interface ConfigTemplate {
    id: string;
    name: LocaleLabel;
    description: LocaleLabel;
    boost_enabled: boolean;
    boost_blacklist: string[];
    /** Player editor layout: 1 (default), 2 or 3 columns. */
    columns?: number;
    files: ConfigFile[];
}

/** Caller capabilities surfaced by the backend so the editor can gate the UI. */
export interface ConfigPermissions {
    write: boolean;
    copy: boolean;
    boost: boolean;
    /** Admin-only: may annotate a discovered parameter into the template. */
    manage_templates?: boolean;
}

export interface ConfigPayload {
    templates: ConfigTemplate[];
    /** Absent for older backends → treated as full access (owner/admin). */
    permissions?: ConfigPermissions;
}

/** Server power state as reported by Pelican. */
export type ServerState = 'running' | 'starting' | 'stopping' | 'offline' | string;

/** A row from the `easy_config_templates` cache (admin listing). */
export interface TemplateRow {
    template_id: string;
    version: string;
    name: LocaleLabel;
    description: LocaleLabel | null;
    author: string | null;
    target_eggs: number[];
    boost_enabled: boolean;
    boost_blacklist: string[];
    file_count: number;
    is_valid: boolean;
    last_error: string | null;
}

/** An egg in the admin template editor picker. */
export interface EggOption {
    id: number;
    name: string;
    banner_image: string | null;
}

/** A server in the "import a config file" picker. */
export interface ServerOption {
    id: number;
    name: string;
    egg_id: number | null;
    egg_name: string | null;
}

/** One entry in a server directory listing (file browser). */
export interface ServerFileEntry {
    name: string;
    mode: string;
    size: number;
    is_file: boolean;
    is_symlink: boolean;
    is_directory: boolean;
    modified_at: number | string;
    mimetype?: string;
}
