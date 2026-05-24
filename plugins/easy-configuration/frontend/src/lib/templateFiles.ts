/**
 * Pure helpers to read/mutate a template `file` object (the JSON the admin
 * edits) for the "Links & sections" panel: detect sections, flatten parameters,
 * set/clear a parameter's `env_var`, and toggle the `section_whitelist`. All
 * mutations are immutable (clone in, clone out) so React state updates cleanly.
 */
export type Json = Record<string, unknown>;

export interface ParamRef {
    section: string | null;
    key: string;
    envVar: string;
}

function isParamDef(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && 'display_type' in (value as Json);
}

/**
 * Append a blank file block, preserving every existing file and its parameters
 * (the spread guarantees the rest of the template is untouched).
 */
export function appendBlankFile(files: Json[]): Json[] {
    return [...files, { id: 'new-file', path: '', format: 'properties', label: {}, parameters: {} }];
}

/** Immutably set a top-level string field (id/path/format) on a file. */
export function setFileField(file: Json, field: 'id' | 'path' | 'format', value: string): Json {
    return { ...file, [field]: value };
}

/** Immutably set/clear the `expanded_by_default` flag (cleared when false → clean JSON). */
export function setExpandedByDefault(file: Json, on: boolean): Json {
    const clone = { ...file };
    if (on) {
        clone.expanded_by_default = true;
    } else {
        delete clone.expanded_by_default;
    }

    return clone;
}

function params(file: Json): Json {
    return (file.parameters ?? {}) as Json;
}

/** Section names declared in a file's parameters (nested objects — ini/toml). */
export function detectSections(file: Json): string[] {
    return Object.entries(params(file))
        .filter(([, value]) => typeof value === 'object' && value !== null && !isParamDef(value))
        .map(([key]) => key);
}

/** Flatten a file's parameters to {section, key, envVar} rows (curated order). */
export function flattenParams(file: Json): ParamRef[] {
    const rows: ParamRef[] = [];
    for (const [key, value] of Object.entries(params(file))) {
        if (isParamDef(value)) {
            rows.push({ section: null, key, envVar: String(value.env_var ?? '') });
        } else if (typeof value === 'object' && value !== null) {
            for (const [childKey, childDef] of Object.entries(value as Json)) {
                if (isParamDef(childDef)) {
                    rows.push({ section: key, key: childKey, envVar: String((childDef as Json).env_var ?? '') });
                }
            }
        }
    }

    return rows;
}

/** Immutably set/clear a parameter's `env_var` (blank clears it). */
export function setEnvVar(file: Json, section: string | null, key: string, envVar: string): Json {
    const clone = structuredClone(file);
    const cloneParams = (clone.parameters ?? {}) as Json;
    const target = (section === null ? cloneParams[key] : (cloneParams[section] as Json | undefined)?.[key]) as Json | undefined;
    if (!target) {
        return clone;
    }
    if (envVar.trim() === '') {
        delete target.env_var;
    } else {
        target.env_var = envVar.trim();
    }

    return clone;
}

/** Current friendly label for a section in one language (empty when unset). */
export function sectionLabel(file: Json, section: string, lang: 'en' | 'fr'): string {
    const labels = file.section_labels as Record<string, Record<string, string>> | undefined;

    return labels?.[section]?.[lang] ?? '';
}

/** Immutably set/clear a section's friendly label for one language. */
export function setSectionLabel(file: Json, section: string, lang: 'en' | 'fr', value: string): Json {
    const clone = structuredClone(file);
    const labels = (typeof clone.section_labels === 'object' && clone.section_labels !== null
        ? clone.section_labels
        : {}) as Record<string, Record<string, string>>;
    const label = { ...(labels[section] ?? {}) };

    if (value.trim() === '') {
        delete label[lang];
    } else {
        label[lang] = value.trim();
    }

    if (Object.keys(label).length === 0) {
        delete labels[section];
    } else {
        labels[section] = label;
    }

    clone.section_labels = labels;

    return clone;
}

/** Is a section currently shown? Empty/absent whitelist = all sections shown. */
export function isSectionVisible(file: Json, section: string): boolean {
    const whitelist = file.section_whitelist;

    return !Array.isArray(whitelist) || whitelist.length === 0 || whitelist.includes(section);
}

/**
 * Immutably toggle a section's visibility. When every section ends up visible
 * the whitelist is cleared ([]) — matching the backend's "empty = show all".
 */
export function toggleSection(file: Json, allSections: string[], section: string): Json {
    const visible = new Set(allSections.filter((name) => isSectionVisible(file, name)));
    if (visible.has(section)) {
        visible.delete(section);
    } else {
        visible.add(section);
    }

    const clone = structuredClone(file);
    clone.section_whitelist = visible.size === allSections.length ? [] : allSections.filter((name) => visible.has(name));

    return clone;
}
