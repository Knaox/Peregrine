/**
 * Pure, immutable helpers to read/edit a single parameter definition inside a
 * template `file` object (the JSON the admin edits), for the visual editor.
 * Mirrors the clone-in / clone-out style of `templateFiles.ts`. A parameter
 * lives at `file.parameters[key]` (flat) or `file.parameters[section][key]`
 * (sectioned). Setters that empty out `label` / `description` / `config` drop
 * the key entirely so the saved JSON stays clean.
 */
import type { Json } from './templateFiles';

export type Lang = 'en' | 'fr';

export interface OptionDraft {
    value: string;
    label?: Record<string, string>;
}

function isParamDef(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && 'display_type' in (value as Json);
}

/** Read a parameter definition, or null when it isn't one. */
export function getParam(file: Json, section: string | null, key: string): Json | null {
    const params = (file.parameters ?? {}) as Json;
    const target = section === null ? params[key] : (params[section] as Json | undefined)?.[key];

    return isParamDef(target) ? (target as Json) : null;
}

function mutateParam(file: Json, section: string | null, key: string, fn: (param: Json) => void): Json {
    const clone = structuredClone(file);
    const params = (clone.parameters ?? {}) as Json;
    const target = (section === null ? params[key] : (params[section] as Json | undefined)?.[key]) as Json | undefined;
    if (target && typeof target === 'object') {
        fn(target);
    }

    return clone;
}

/** Set/clear a localised `label` or `description` for one language. */
export function setLocale(file: Json, section: string | null, key: string, field: 'label' | 'description', lang: Lang, value: string): Json {
    return mutateParam(file, section, key, (param) => {
        const current = (typeof param[field] === 'object' && param[field] !== null ? param[field] : {}) as Record<string, string>;
        const next = { ...current };
        if (value.trim() === '') {
            delete next[lang];
        } else {
            next[lang] = value.trim();
        }
        if (Object.keys(next).length === 0) {
            delete param[field];
        } else {
            param[field] = next;
        }
    });
}

export function setDisplayType(file: Json, section: string | null, key: string, value: string): Json {
    return mutateParam(file, section, key, (param) => {
        param.display_type = value;
    });
}

/**
 * Set/clear a `config.*` field (min, max, step, float, suffix, default, regex,
 * max_length, format, separator, true_value, false_value…). An empty string or
 * `undefined` removes it; the `config` object is dropped once empty.
 */
export function setConfigField(file: Json, section: string | null, key: string, field: string, value: string | number | boolean | undefined): Json {
    return mutateParam(file, section, key, (param) => {
        const current = (typeof param.config === 'object' && param.config !== null ? param.config : {}) as Json;
        const next: Json = { ...current };
        if (value === undefined || value === '') {
            delete next[field];
        } else {
            next[field] = value;
        }
        if (Object.keys(next).length === 0) {
            delete param.config;
        } else {
            param.config = next;
        }
    });
}

/** Read the current select/multiselect options of a parameter. */
export function getOptions(file: Json, section: string | null, key: string): OptionDraft[] {
    const param = getParam(file, section, key);
    const config = (param?.config ?? {}) as Json;
    const options = config.options;

    return Array.isArray(options) ? (options as OptionDraft[]) : [];
}

/** Replace the whole options array (drops it, and `config`, when empty). */
export function setOptions(file: Json, section: string | null, key: string, options: OptionDraft[]): Json {
    return mutateParam(file, section, key, (param) => {
        const current = (typeof param.config === 'object' && param.config !== null ? param.config : {}) as Json;
        const next: Json = { ...current };
        if (options.length === 0) {
            delete next.options;
        } else {
            next.options = options;
        }
        if (Object.keys(next).length === 0) {
            delete param.config;
        } else {
            param.config = next;
        }
    });
}
