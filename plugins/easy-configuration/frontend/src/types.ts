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
    boost?: ParamBoost | null;
}

export interface ConfigFile {
    id: string;
    label: LocaleLabel | null;
    path: string;
    format: string;
    exists: boolean;
    sectioned: boolean;
    parameters: ConfigParam[];
}

export interface ConfigTemplate {
    id: string;
    name: LocaleLabel;
    description: LocaleLabel;
    boost_enabled: boolean;
    boost_blacklist: string[];
    files: ConfigFile[];
}

export interface ConfigPayload {
    templates: ConfigTemplate[];
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
