import { describe, expect, it } from 'vitest';
import type { ConfigParam } from '../types';
import { validateValue } from './validate';

/** Minimal ConfigParam for validation tests (only display_type/config/env_var matter). */
const mk = (display_type: string, config: Record<string, unknown> = {}, extra: Record<string, unknown> = {}): ConfigParam =>
    ({ key: 'k', section: null, display_type, config, label: null, description: null, value: '', inferred: false, ...extra }) as unknown as ConfigParam;

describe('validateValue — number / slider', () => {
    it('rejects empty and non-numeric input', () => {
        expect(validateValue(mk('number'), '')).toBe('number');
        expect(validateValue(mk('number'), 'abc')).toBe('number');
    });

    it('accepts a plain integer', () => {
        expect(validateValue(mk('number'), '42')).toBeNull();
    });

    it('rejects decimals unless float / fractional step / decimal default', () => {
        expect(validateValue(mk('number'), '1.5')).toBe('integer');
        expect(validateValue(mk('number', { float: true }), '1.5')).toBeNull();
        expect(validateValue(mk('slider', { step: 0.1 }), '1.5')).toBeNull();
        expect(validateValue(mk('slider', { default: '1.0' }), '1.5')).toBeNull();
    });

    it('treats min/max as hard caps only for env-linked params', () => {
        expect(validateValue(mk('slider', { min: 0, max: 10 }), '99')).toBeNull(); // soft cap, manual override allowed
        expect(validateValue(mk('slider', { min: 0, max: 10 }, { env_var: 'X' }), '99')).toBe('max');
        expect(validateValue(mk('slider', { min: 5, max: 10 }, { env_var: 'X' }), '1')).toBe('min');
    });
});

describe('validateValue — select / multiselect / boolean', () => {
    const options = [{ value: 'a' }, { value: 'b' }];

    it('checks select membership', () => {
        expect(validateValue(mk('select', { options }), 'a')).toBeNull();
        expect(validateValue(mk('select', { options }), 'z')).toBe('option');
    });

    it('checks every multiselect item', () => {
        expect(validateValue(mk('multiselect', { options }), 'a,b')).toBeNull();
        expect(validateValue(mk('multiselect', { options }), 'a,z')).toBe('option');
    });

    it('checks boolean against the configured true/false values', () => {
        expect(validateValue(mk('boolean', { true_value: 'On', false_value: 'Off' }), 'On')).toBeNull();
        expect(validateValue(mk('boolean', { true_value: 'On', false_value: 'Off' }), 'maybe')).toBe('boolean');
    });

    // Template JSON commonly declares NUMERIC option values (0/1/2 enums,
    // 6144/8192 world sizes…) while the compared value is always the string
    // read from the file / the <select>. A strict-typed includes() flagged
    // every such field invalid, which blocked the whole save (7DTD bug).
    it('accepts numeric select options against their string value', () => {
        const numeric = [{ value: 0 }, { value: 1 }, { value: 2 }];
        expect(validateValue(mk('select', { options: numeric }), '2')).toBeNull();
        expect(validateValue(mk('select', { options: numeric }), '5')).toBe('option');
    });

    it('accepts numeric multiselect options against their string items', () => {
        const numeric = [{ value: 0 }, { value: 50 }, { value: 100 }];
        expect(validateValue(mk('multiselect', { options: numeric }), '0,100')).toBeNull();
        expect(validateValue(mk('multiselect', { options: numeric }), '0,7')).toBe('option');
    });
});

describe('validateValue — text / color', () => {
    it('enforces max length and regex', () => {
        expect(validateValue(mk('text', { max_length: 3 }), 'toolong')).toBe('length');
        expect(validateValue(mk('text', { regex: '^[a-z]+$' }), 'ABC')).toBe('format');
        expect(validateValue(mk('text', { regex: '^[a-z]+$' }), 'abc')).toBeNull();
    });

    it('accepts a 6-digit hex colour, with or without the hash', () => {
        expect(validateValue(mk('color'), '#ff0000')).toBeNull();
        expect(validateValue(mk('color'), 'ff0000')).toBeNull();
        expect(validateValue(mk('color'), 'red')).toBe('color');
    });
});
