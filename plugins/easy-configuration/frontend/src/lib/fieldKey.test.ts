import { describe, expect, it } from 'vitest';
import type { ConfigParam } from '../types';
import { backendFieldKey, fieldKey, fieldKeyOf } from './fieldKey';

const SEP = String.fromCharCode(0x1f);

const param = (extra: Record<string, unknown>): ConfigParam =>
    ({ key: 'k', section: null, display_type: 'text', config: {}, label: null, description: null, value: '', inferred: false, ...extra }) as unknown as ConfigParam;

describe('fieldKey', () => {
    it('joins file, (empty) section and key with the unit separator', () => {
        expect(fieldKey('f', null, 'k')).toBe(`f${SEP}${SEP}k`);
        expect(fieldKey('f', 'Section', 'k')).toBe(`f${SEP}Section${SEP}k`);
    });
});

describe('fieldKeyOf', () => {
    it('leaves occurrence 0 unsuffixed and suffixes repeats', () => {
        expect(fieldKeyOf('f', param({ section: 'S', key: 'k' }))).toBe(`f${SEP}S${SEP}k`);
        expect(fieldKeyOf('f', param({ section: 'S', key: 'k', occurrence: 0 }))).toBe(`f${SEP}S${SEP}k`);
        expect(fieldKeyOf('f', param({ section: 'S', key: 'k', occurrence: 2 }))).toBe(`f${SEP}S${SEP}k${SEP}2`);
    });
});

describe('backendFieldKey', () => {
    it('prefixes a backend composite with the file id', () => {
        expect(backendFieldKey('f', `S${SEP}k`)).toBe(`f${SEP}S${SEP}k`);
    });
});
