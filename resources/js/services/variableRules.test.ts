import { describe, expect, it } from 'vitest';
import { controlFor, parseRules, validateVariable } from './variableRules';

describe('parseRules', () => {
    it('parses the common Pelican shapes', () => {
        const parsed = parseRules('required|integer|between:0,5');
        expect(parsed.required).toBe(true);
        expect(parsed.integer).toBe(true);
        expect(parsed.numMin).toBe(0);
        expect(parsed.numMax).toBe(5);
    });

    it('treats min/max as LENGTH bounds for string variables and VALUE bounds for numeric ones', () => {
        expect(parseRules('required|string|max:20').lenMax).toBe(20);
        expect(parseRules('required|string|max:20').numMax).toBeNull();
        expect(parseRules('required|integer|max:20').numMax).toBe(20);
    });

    it('parses in: lists in declared order', () => {
        expect(parseRules('required|string|in:paper,forge,fabric').inValues).toEqual(['paper', 'forge', 'fabric']);
    });

    it('survives a regex containing pipes', () => {
        const parsed = parseRules('required|regex:/^(latest|1\\.\\d+)$/|string');
        expect(parsed.regex).not.toBeNull();
        expect(parsed.regex?.test('latest')).toBe(true);
        expect(parsed.regex?.test('1.21')).toBe(true);
        expect(parsed.regex?.test('nope')).toBe(false);
        // the trailing rule after the regex is still picked up
        expect(parsed.lenMax).toBeNull();
    });
});

describe('controlFor', () => {
    it('renders booleans as toggles, keeping the wire format', () => {
        expect(controlFor(parseRules('required|boolean'), '1')).toEqual({ kind: 'toggle', onValue: '1', offValue: '0' });
        expect(controlFor(parseRules('required|boolean'), 'true')).toEqual({ kind: 'toggle', onValue: 'true', offValue: 'false' });
        expect(controlFor(parseRules('required|in:true,false'), '')).toEqual({ kind: 'toggle', onValue: 'true', offValue: 'false' });
        expect(controlFor(parseRules('required|string|in:on,off'), 'on')).toEqual({ kind: 'toggle', onValue: 'on', offValue: 'off' });
    });

    it('renders in: lists as selects', () => {
        expect(controlFor(parseRules('required|string|in:a,b,c'), 'a')).toEqual({
            kind: 'select', options: ['a', 'b', 'c'], allowEmpty: false,
        });
        expect(controlFor(parseRules('nullable|string|in:a,b,c'), '')).toMatchObject({ kind: 'select', allowEmpty: true });
    });

    it('renders numeric rules as bounded number inputs', () => {
        expect(controlFor(parseRules('required|integer|between:0,5'), '2')).toEqual({
            kind: 'number', min: 0, max: 5, integer: true,
        });
        expect(controlFor(parseRules('required|numeric'), '1.5')).toMatchObject({ kind: 'number', integer: false });
    });

    it('falls back to text with a length cap', () => {
        expect(controlFor(parseRules('required|string|max:30'), 'x')).toEqual({ kind: 'text', maxLength: 30 });
        expect(controlFor(parseRules(''), '')).toEqual({ kind: 'text', maxLength: null });
    });
});

describe('validateVariable', () => {
    it('enforces required vs nullable on empty input', () => {
        expect(validateVariable(parseRules('required|string'), '')).toBe('required');
        expect(validateVariable(parseRules('nullable|string'), '')).toBeNull();
        expect(validateVariable(parseRules('string'), '')).toBeNull();
    });

    it('enforces integers, ranges and lists', () => {
        const rules = parseRules('required|integer|between:0,5');
        expect(validateVariable(rules, '3')).toBeNull();
        expect(validateVariable(rules, '9')).toBe('range');
        expect(validateVariable(rules, '2.5')).toBe('integer');
        expect(validateVariable(parseRules('required|in:a,b'), 'c')).toBe('in');
        expect(validateVariable(parseRules('required|in:a,b'), 'b')).toBeNull();
    });

    it('enforces string length and regex', () => {
        expect(validateVariable(parseRules('required|string|max:3'), 'abcd')).toBe('length');
        expect(validateVariable(parseRules('required|regex:/^[A-Z]+$/'), 'abc')).toBe('regex');
        expect(validateVariable(parseRules('required|regex:/^[A-Z]+$/'), 'ABC')).toBeNull();
    });

    it('never throws on an uncompilable regex', () => {
        expect(validateVariable(parseRules('required|regex:/^(?<broken/'), 'anything')).toBeNull();
    });
});
