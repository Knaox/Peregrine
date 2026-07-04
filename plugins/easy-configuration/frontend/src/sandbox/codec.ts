import data from './sandbox-data.json';
import frTranslations from './sandbox-i18n-fr.json';

/**
 * Codec for 7 Days to Die's V2 `SandboxCode` (serverconfig.xml).
 *
 * Wire format (matches the game and hosthavoc.com's generator):
 *   - 1st char: format version letter (currently "A");
 *   - then one 3-letter record per NON-DEFAULT option, in the canonical
 *     option-table order: 2 letters = the option's enumIndex in base-26
 *     (AA=0, AB=1, …), 1 letter = the index of the chosen value on the
 *     option's value ladder (A=0, B=1, …).
 * Options sitting at their default value are omitted; decoding therefore
 * starts from the defaults and overlays the records. Records whose enumIndex
 * is unknown (newer game build) are counted but skipped.
 */

export interface SandboxDisables {
    whenValue: number | boolean;
    inverted: boolean;
    options: string[];
}

export interface SandboxOption {
    option: string;
    enumIndex: number;
    kind: 'float' | 'int' | 'bool';
    label: string;
    category: string;
    valueSet: string;
    defaultIndex: number;
    valueLabels?: string[];
    description?: string;
    disables?: SandboxDisables;
}

export type SandboxValue = number | boolean;
export type SandboxState = Record<string, SandboxValue>;

const VERSION: string = data.formatVersionChar;
const VALUE_SETS = data.valueSets as Record<string, SandboxValue[]>;
const FR = frTranslations as { values: Record<string, string>; options: Record<string, { label: string; description: string }> };

/** Canonical option table — encoding follows THIS array order. */
export const SANDBOX_OPTIONS = data.options as SandboxOption[];

/** Display order of the option groups (the game's own grouping). */
export const SANDBOX_CATEGORIES = ['General', 'Entities', 'World', 'Resources', 'Crafting', 'Traders', 'Tasks', 'Misc'];

const byEnumIndex = new Map(SANDBOX_OPTIONS.map((option) => [option.enumIndex, option]));
const byName = new Map(SANDBOX_OPTIONS.map((option) => [option.option, option]));

export class SandboxCodeError extends Error {}

export const sandboxOption = (name: string): SandboxOption | undefined => byName.get(name);

export const valuesOf = (option: SandboxOption): SandboxValue[] => VALUE_SETS[option.valueSet] ?? [];

export const defaultValueOf = (option: SandboxOption): SandboxValue => valuesOf(option)[option.defaultIndex];

/** Index of the option's current value on its ladder (default when off-ladder). */
export function valueIndexOf(option: SandboxOption, state: SandboxState): number {
    const index = valuesOf(option).findIndex((value) => value === state[option.option]);

    return index >= 0 ? index : option.defaultIndex;
}

export function sandboxDefaults(): SandboxState {
    return Object.fromEntries(SANDBOX_OPTIONS.map((option) => [option.option, defaultValueOf(option)]));
}

export function encodeSandbox(state: SandboxState): string {
    let code = VERSION;
    for (const option of SANDBOX_OPTIONS) {
        if (!(option.option in state)) {
            continue;
        }
        const index = valuesOf(option).findIndex((value) => value === state[option.option]);
        if (index < 0) {
            throw new SandboxCodeError(`${option.option}: value ${String(state[option.option])} not on ladder`);
        }
        if (index === option.defaultIndex) {
            continue;
        }
        code += String.fromCharCode(65 + Math.floor(option.enumIndex / 26))
            + String.fromCharCode(65 + (option.enumIndex % 26))
            + String.fromCharCode(65 + index);
    }

    return code;
}

export function decodeSandbox(raw: string): { state: SandboxState; unknownRecords: number } {
    const code = raw.trim().toUpperCase();
    if (code === '' || code[0] !== VERSION) {
        throw new SandboxCodeError(`bad version (expected ${VERSION})`);
    }
    const body = code.slice(1);
    if (!/^[A-Z]*$/.test(body)) {
        throw new SandboxCodeError('only letters A-Z are allowed');
    }
    if (body.length % 3 !== 0) {
        throw new SandboxCodeError(`body length ${body.length} not a multiple of 3`);
    }

    const state = sandboxDefaults();
    let unknownRecords = 0;
    for (let i = 0; i < body.length / 3; i++) {
        const record = body.slice(i * 3, i * 3 + 3);
        const enumIndex = (record.charCodeAt(0) - 65) * 26 + (record.charCodeAt(1) - 65);
        const option = byEnumIndex.get(enumIndex);
        if (option === undefined) {
            unknownRecords++;
            continue;
        }
        const valueIndex = record.charCodeAt(2) - 65;
        const ladder = valuesOf(option);
        if (valueIndex >= ladder.length) {
            throw new SandboxCodeError(`${option.option}: value index ${valueIndex} out of range`);
        }
        state[option.option] = ladder[valueIndex];
    }

    return { state, unknownRecords };
}

/** Localised display name of an option (game term, EN by default, FR translated). */
export function optionLabel(option: SandboxOption, lang: string): string {
    return lang === 'fr' ? (FR.options[option.option]?.label ?? option.label) : option.label;
}

/** Localised long description of an option ('' when the game ships none). */
export function optionDescription(option: SandboxOption, lang: string): string {
    const english = option.description ?? '';

    return lang === 'fr' ? (FR.options[option.option]?.description ?? english) : english;
}

/** Human label for one ladder position (option labels, Yes/No booleans, else the raw value) — localised. */
export function valueLabel(option: SandboxOption, index: number, lang = 'en'): string {
    const custom = option.valueLabels?.[index];
    if (custom !== undefined) {
        return lang === 'fr' ? (FR.values[custom] ?? custom) : custom;
    }
    const value = valuesOf(option)[index];
    if (value === undefined) {
        return '';
    }
    if (typeof value === 'boolean') {
        const key = value ? 'Yes' : 'No';

        return lang === 'fr' ? (FR.values[key] ?? key) : key;
    }

    return String(value);
}

/** Options greyed out because another option's current value disables them. */
export function disabledSandboxOptions(state: SandboxState): Set<string> {
    const disabled = new Set<string>();
    for (const option of SANDBOX_OPTIONS) {
        const rule = option.disables;
        if (!rule) {
            continue;
        }
        const current = option.option in state ? state[option.option] : defaultValueOf(option);
        if ((current === rule.whenValue) !== rule.inverted) {
            for (const name of rule.options) {
                disabled.add(name);
            }
        }
    }

    return disabled;
}

export function modifiedSandboxOptions(state: SandboxState): string[] {
    return SANDBOX_OPTIONS
        .filter((option) => option.option in state && state[option.option] !== defaultValueOf(option))
        .map((option) => option.option);
}
