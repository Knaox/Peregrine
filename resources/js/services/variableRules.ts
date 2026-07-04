/**
 * Parser for Pelican egg-variable validation rules (Laravel rule strings such
 * as `required|integer|between:0,5` or `nullable|string|in:a,b,c|max:20`),
 * plus the mapping from parsed rules to the RIGHT input control and a
 * client-side validator mirroring what Pelican will enforce server-side.
 */

export interface ParsedRules {
    required: boolean;
    nullable: boolean;
    boolean: boolean;
    integer: boolean;
    numeric: boolean;
    /** `in:a,b,c` allow-list, in declared order. */
    inValues: string[] | null;
    /** Numeric bounds (integer/numeric rules) — from between:/min:/max:. */
    numMin: number | null;
    numMax: number | null;
    /** String length bounds — from min:/max:/size: on non-numeric variables. */
    lenMin: number | null;
    lenMax: number | null;
    /** Compiled `regex:` pattern when convertible to JS, else null. */
    regex: RegExp | null;
    alphaNum: boolean;
    alphaDash: boolean;
    url: boolean;
    email: boolean;
    ip: boolean;
}

export type VariableControl =
    | { kind: 'toggle'; onValue: string; offValue: string }
    | { kind: 'select'; options: string[]; allowEmpty: boolean }
    | { kind: 'number'; min: number | null; max: number | null; integer: boolean }
    | { kind: 'text'; maxLength: number | null };

/** Rule names used as boundaries when re-joining a `regex:` split on `|`. */
const KNOWN_RULES = new Set([
    'required', 'nullable', 'sometimes', 'present', 'filled', 'boolean', 'bool', 'integer', 'int',
    'numeric', 'string', 'in', 'not_in', 'between', 'min', 'max', 'size', 'digits', 'digits_between',
    'regex', 'not_regex', 'alpha', 'alpha_num', 'alpha_dash', 'url', 'email', 'ip', 'ipv4', 'ipv6',
    'uuid', 'json', 'date', 'starts_with', 'ends_with',
]);

/**
 * Split a pipe-separated rule string without breaking a `regex:` whose pattern
 * itself contains `|` (alternations): fragments are re-joined onto the pending
 * regex rule until a fragment starts with a KNOWN rule name.
 */
function tokenize(rules: string): string[] {
    const out: string[] = [];
    for (const fragment of rules.split('|')) {
        const name = fragment.split(':', 1)[0]?.trim().toLowerCase() ?? '';
        const isRule = KNOWN_RULES.has(name);
        const last = out[out.length - 1];
        if (!isRule && last !== undefined && (last.startsWith('regex:') || last.startsWith('not_regex:'))) {
            out[out.length - 1] = `${last}|${fragment}`;
            continue;
        }
        if (fragment.trim() !== '') {
            out.push(fragment.trim());
        }
    }

    return out;
}

/** Convert a Laravel `/pattern/flags` (or bare pattern) into a JS RegExp. */
function compileRegex(raw: string): RegExp | null {
    try {
        const delimited = /^(.)(.*)\1([a-zA-Z]*)$/s.exec(raw);
        if (delimited && ['/', '#', '~'].includes(delimited[1] ?? '')) {
            const flags = [...(delimited[3] ?? '')].filter((flag) => 'imsu'.includes(flag)).join('');

            return new RegExp(delimited[2] ?? '', flags);
        }

        return new RegExp(raw);
    } catch {
        return null;
    }
}

export function parseRules(rules: string): ParsedRules {
    const parsed: ParsedRules = {
        required: false, nullable: false, boolean: false, integer: false, numeric: false,
        inValues: null, numMin: null, numMax: null, lenMin: null, lenMax: null,
        regex: null, alphaNum: false, alphaDash: false, url: false, email: false, ip: false,
    };

    let min: number | null = null;
    let max: number | null = null;

    const bounds = (raw: string): [number | null, number | null] => {
        const parts = raw.split(',').map((part) => Number(part));
        const low = parts[0];
        const high = parts[1];

        return [
            low !== undefined && Number.isFinite(low) ? low : null,
            high !== undefined && Number.isFinite(high) ? high : null,
        ];
    };

    for (const token of tokenize(rules)) {
        const separatorIndex = token.indexOf(':');
        const name = (separatorIndex === -1 ? token : token.slice(0, separatorIndex)).trim().toLowerCase();
        const arg = separatorIndex === -1 ? '' : token.slice(separatorIndex + 1);
        switch (name) {
            case 'required': parsed.required = true; break;
            case 'nullable': parsed.nullable = true; break;
            case 'boolean': case 'bool': parsed.boolean = true; break;
            case 'integer': case 'int': parsed.integer = true; break;
            case 'numeric': parsed.numeric = true; break;
            case 'in': parsed.inValues = arg.split(',').map((value) => value.trim()).filter((value) => value !== ''); break;
            case 'between': {
                const [low, high] = bounds(arg);
                if (low !== null) min = low;
                if (high !== null) max = high;
                break;
            }
            case 'min': { const value = Number(arg); if (Number.isFinite(value)) min = value; break; }
            case 'max': { const value = Number(arg); if (Number.isFinite(value)) max = value; break; }
            case 'size': { const value = Number(arg); if (Number.isFinite(value)) { min = value; max = value; } break; }
            case 'digits': { const value = Number(arg); parsed.integer = true; if (Number.isFinite(value)) { parsed.lenMin = value; parsed.lenMax = value; } break; }
            case 'digits_between': {
                const [low, high] = bounds(arg);
                parsed.integer = true;
                if (low !== null) parsed.lenMin = low;
                if (high !== null) parsed.lenMax = high;
                break;
            }
            case 'regex': parsed.regex = compileRegex(arg); break;
            case 'alpha_num': parsed.alphaNum = true; break;
            case 'alpha_dash': parsed.alphaDash = true; break;
            case 'url': parsed.url = true; break;
            case 'email': parsed.email = true; break;
            case 'ip': case 'ipv4': case 'ipv6': parsed.ip = true; break;
        }
    }

    // Laravel semantics: min/max bound the VALUE for numeric rules and the
    // LENGTH for string ones.
    if (parsed.integer || parsed.numeric) {
        parsed.numMin = min;
        parsed.numMax = max;
    } else {
        if (min !== null) parsed.lenMin = min;
        if (max !== null) parsed.lenMax = max;
    }

    return parsed;
}

/** Boolean-looking `in:` pairs, checked case-insensitively. */
const BOOLEAN_PAIRS: ReadonlyArray<readonly [string, string]> = [
    ['0', '1'], ['false', 'true'], ['no', 'yes'], ['off', 'on'],
];

function booleanPairFor(values: string[]): { onValue: string; offValue: string } | null {
    if (values.length !== 2) {
        return null;
    }
    const lower = values.map((value) => value.toLowerCase());
    for (const [off, on] of BOOLEAN_PAIRS) {
        const onIndex = lower.indexOf(on);
        const offIndex = lower.indexOf(off);
        if (onIndex !== -1 && offIndex !== -1) {
            return { onValue: values[onIndex] ?? on, offValue: values[offIndex] ?? off };
        }
    }

    return null;
}

/** Pick the control a variable should render with (currentValue disambiguates boolean wire formats). */
export function controlFor(parsed: ParsedRules, currentValue: string): VariableControl {
    if (parsed.inValues !== null && parsed.inValues.length > 0) {
        const pair = booleanPairFor(parsed.inValues);
        if (pair !== null) {
            return { kind: 'toggle', ...pair };
        }

        return { kind: 'select', options: parsed.inValues, allowEmpty: parsed.nullable || !parsed.required };
    }
    if (parsed.boolean) {
        // Laravel `boolean` accepts 1/0 and true/false — keep whichever wire
        // format the variable already uses so a save round-trips unchanged.
        const lower = currentValue.toLowerCase();

        return lower === 'true' || lower === 'false'
            ? { kind: 'toggle', onValue: 'true', offValue: 'false' }
            : { kind: 'toggle', onValue: '1', offValue: '0' };
    }
    if (parsed.integer || parsed.numeric) {
        return { kind: 'number', min: parsed.numMin, max: parsed.numMax, integer: parsed.integer };
    }

    return { kind: 'text', maxLength: parsed.lenMax };
}

const PATTERNS = {
    integer: /^-?\d+$/,
    alphaNum: /^[\p{L}\p{N}]+$/u,
    alphaDash: /^[\p{L}\p{N}_-]+$/u,
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    ip: /^([0-9]{1,3}\.){3}[0-9]{1,3}$|^[0-9a-fA-F:]+:[0-9a-fA-F:]*$/,
};

/** Client-side mirror of the Pelican-side validation. Returns an error token or null. */
export function validateVariable(parsed: ParsedRules, value: string): string | null {
    if (value === '') {
        return parsed.required && !parsed.nullable ? 'required' : null;
    }
    if (parsed.inValues !== null && parsed.inValues.length > 0 && !parsed.inValues.includes(value)) {
        return 'in';
    }
    if (parsed.boolean && !['0', '1', 'true', 'false'].includes(value.toLowerCase())) {
        return 'boolean';
    }
    if (parsed.integer && !PATTERNS.integer.test(value)) {
        return 'integer';
    }
    if (parsed.numeric && !Number.isFinite(Number(value))) {
        return 'numeric';
    }
    if (parsed.integer || parsed.numeric) {
        const numberValue = Number(value);
        if (parsed.numMin !== null && numberValue < parsed.numMin) return 'range';
        if (parsed.numMax !== null && numberValue > parsed.numMax) return 'range';
    } else {
        if (parsed.lenMin !== null && value.length < parsed.lenMin) return 'length';
        if (parsed.lenMax !== null && value.length > parsed.lenMax) return 'length';
    }
    if (parsed.regex !== null && !parsed.regex.test(value)) {
        return 'regex';
    }
    if (parsed.alphaNum && !PATTERNS.alphaNum.test(value)) return 'format';
    if (parsed.alphaDash && !PATTERNS.alphaDash.test(value)) return 'format';
    if (parsed.email && !PATTERNS.email.test(value)) return 'format';
    if (parsed.url) {
        try {
            new URL(value);
        } catch {
            return 'format';
        }
    }
    if (parsed.ip && !PATTERNS.ip.test(value)) return 'format';

    return null;
}

/** Structured summary the UI renders as a localised hint line ("integer 0–5", "max 20 chars"…). */
export function describeRules(parsed: ParsedRules): { token: string; params?: Record<string, unknown> }[] {
    const out: { token: string; params?: Record<string, unknown> }[] = [];
    if (parsed.integer || parsed.numeric) {
        if (parsed.numMin !== null && parsed.numMax !== null) {
            out.push({ token: parsed.integer ? 'hint_integer_between' : 'hint_number_between', params: { min: parsed.numMin, max: parsed.numMax } });
        } else if (parsed.numMin !== null) {
            out.push({ token: 'hint_min', params: { min: parsed.numMin } });
        } else if (parsed.numMax !== null) {
            out.push({ token: 'hint_max', params: { max: parsed.numMax } });
        } else {
            out.push({ token: parsed.integer ? 'hint_integer' : 'hint_number' });
        }
    } else if (parsed.lenMax !== null) {
        out.push({ token: 'hint_max_length', params: { max: parsed.lenMax } });
    }
    if (parsed.regex !== null) {
        out.push({ token: 'hint_pattern' });
    }
    if (!parsed.required || parsed.nullable) {
        out.push({ token: 'hint_optional' });
    }

    return out;
}
