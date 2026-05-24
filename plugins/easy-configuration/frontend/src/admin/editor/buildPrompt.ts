import type { Json } from '../../lib/templateFiles';

/** One parameter discovered in a scaffolded config file (read from the server). */
export interface PromptParam {
    section: string | null;
    key: string;
    value: string;
    type: string;
}

/** A config file the AI must produce parameter entries for. */
export interface PromptFile {
    id: string;
    path: string;
    format: string;
    expandedByDefault: boolean;
    /** Whitelisted native sections (ini/toml). Empty = every section. */
    sectionWhitelist: string[];
    params: PromptParam[];
}

/** An explicit env_var ↔ parameter link the admin wants applied. */
export interface PromptEnvLink {
    fileId: string;
    section: string | null;
    key: string;
    envVar: string;
}

export interface PromptInput {
    id: string;
    nameEn: string;
    nameFr: string;
    descEn: string;
    descFr: string;
    author: string;
    targetEggs: number[];
    columns: number;
    boostEnabled: boolean;
    blacklist: string[];
    files: PromptFile[];
    envLinks: PromptEnvLink[];
}

function isParamDef(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && 'display_type' in (value as Json);
}

/** Whitelisted sections of a file block (empty = every section is kept). */
export function sectionWhitelistOf(file: Json): string[] {
    return Array.isArray(file.section_whitelist) ? (file.section_whitelist as unknown[]).map(String) : [];
}

/**
 * Read every parameter (key, current value, detected type) out of a scaffolded
 * file block, honouring `section_whitelist` — params of a non-whitelisted
 * native section are dropped so the prompt only covers what the player will see.
 */
export function fileParamsForPrompt(file: Json): PromptParam[] {
    const rows: PromptParam[] = [];
    const params = (file.parameters ?? {}) as Json;
    const whitelist = sectionWhitelistOf(file);
    const allows = (section: string): boolean => whitelist.length === 0 || whitelist.includes(section);

    const push = (section: string | null, key: string, def: Json): void => {
        const config = (def.config ?? {}) as Json;
        rows.push({ section, key, value: String(config.default ?? ''), type: String(def.display_type ?? 'text') });
    };

    for (const [key, value] of Object.entries(params)) {
        if (isParamDef(value)) {
            push(null, key, value);
        } else if (typeof value === 'object' && value !== null && allows(key)) {
            for (const [childKey, childDef] of Object.entries(value as Json)) {
                if (isParamDef(childDef)) {
                    push(key, childKey, childDef as Json);
                }
            }
        }
    }

    return rows;
}

/** Map parsed `files` JSON blocks into the prompt's file model. */
export function promptFilesFrom(files: Json[]): PromptFile[] {
    return files.map((file) => ({
        id: String(file.id ?? ''),
        path: String(file.path ?? ''),
        format: String(file.format ?? 'properties'),
        expandedByDefault: file.expanded_by_default === true,
        sectionWhitelist: sectionWhitelistOf(file),
        params: fileParamsForPrompt(file),
    }));
}

const SCHEMA_SPEC = `Output a SINGLE JSON object, no markdown fences, no commentary, matching exactly:
{
  "id": string, "version": "1.0.0",
  "name": { "en": string, "fr": string },
  "description": { "en": string, "fr": string },
  "author": string,
  "target_eggs": number[],
  "columns": 1 | 2 | 3,
  "boost": { "enabled": boolean, "parameter_blacklist": string[] },
  "files": [ {
    "id": string, "path": string,
    "format": "properties" | "ini" | "yaml" | "json" | "toml" | "palworld" | "theforest",
    "expanded_by_default": boolean,
    "section_labels": { "<rawSection>": { "en": string, "fr": string } },
    "parameters": <flat { "<key>": <param> } for properties/json/yaml, nested { "<section>": { "<key>": <param> } } for ini/toml/palworld>
  } ]
}
<param> = { "display_type": "boolean"|"slider"|"select"|"multiselect"|"text"|"number"|"textarea"|"color", "config": {...}, "label": {"en","fr"}, "description": {"en","fr"}, "env_var"?: string }
config by type — number/slider: { "min", "max", "step"?, "float"?, "default" } ; boolean: { "true_value", "false_value", "default" } ; select/multiselect: { "default", "options": [{ "value", "label": {"en","fr"} }], "separator"? } ; text/textarea: { "default", "max_length"?, "regex"? } ; color: { "default" }.`;

const RULES = `RULES:
- Produce a <param> entry for EVERY parameter listed below — do not add, rename or drop any. Keep the exact key names, section grouping, file id, path and format.
- Set config.default to EXACTLY the current value shown.
- Choose the best display_type from the detected type and the value: boolean for true/false, number or slider for numerics (give sensible min/max/step), select for clearly enumerable values (provide options with en+fr labels), text/textarea otherwise.
- Write a clear, human label AND a one-sentence description in BOTH English and French for every parameter and every file.
- Add env_var only on the parameters listed in ENV LINKS.
- Output JSON only.`;

function paramLine(p: PromptParam): string {
    const where = p.section !== null ? `[${p.section}] ${p.key}` : p.key;

    return `  - ${where} = ${p.value} (${p.type})`;
}

function fileBlock(file: PromptFile): string {
    const header = `FILE "${file.id}" — path "${file.path}", format ${file.format}, expanded_by_default ${file.expandedByDefault}`;
    const whitelist = file.sectionWhitelist.length > 0
        ? `\nsection_whitelist: ${JSON.stringify(file.sectionWhitelist)} — set this on the file and produce ONLY these native sections, ignore every other section.`
        : '';
    const params = file.params.length > 0 ? file.params.map(paramLine).join('\n') : '  (no parameters detected)';

    return `${header}${whitelist}\nParameters (key = current value (detected type)):\n${params}`;
}

function envLinkLine(link: PromptEnvLink): string {
    const where = link.section !== null ? `param "${link.key}" in section "${link.section}"` : `param "${link.key}"`;

    return `- file "${link.fileId}" ${where} → env_var "${link.envVar}"`;
}

/** Assemble the full, self-contained prompt the admin pastes into any chat AI. */
export function buildPrompt(input: PromptInput): string {
    const meta = [
        `id: ${input.id}`,
        `name: en="${input.nameEn}" fr="${input.nameFr}"`,
        `description: en="${input.descEn}" fr="${input.descFr}"`,
        `author: ${input.author || 'Peregrine'}`,
        `target_eggs: ${JSON.stringify(input.targetEggs)}`,
        `columns: ${input.columns}`,
        `boost.enabled: ${input.boostEnabled}`,
        `boost.parameter_blacklist: ${JSON.stringify(input.blacklist)}`,
    ].join('\n');

    const files = input.files.length > 0 ? input.files.map(fileBlock).join('\n\n') : '(no files — ask the admin to import config files first)';
    const links = input.envLinks.length > 0 ? input.envLinks.map(envLinkLine).join('\n') : '(none)';

    return [
        'You are generating a Peregrine "Easy Configuration" template. It is a pure render schema that turns a game server config file into a friendly editor (sliders, toggles, dropdowns) with bilingual labels.',
        '',
        '### OUTPUT FORMAT',
        SCHEMA_SPEC,
        '',
        '### TEMPLATE METADATA (use verbatim)',
        meta,
        '',
        '### FILES & PARAMETERS (use the exact ids/paths/formats and every parameter)',
        files,
        '',
        '### ENV LINKS',
        links,
        '',
        '### RULES',
        RULES,
    ].join('\n');
}
