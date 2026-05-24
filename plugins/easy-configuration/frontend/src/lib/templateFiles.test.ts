import { describe, expect, it } from 'vitest';
import { appendBlankFile, detectSections, flattenParams, setEnvVar, setExpandedByDefault, setFileField, toggleSection, type Json } from './templateFiles';

const flatFile = (): Json => ({
    id: 'server-properties',
    path: 'server.properties',
    format: 'properties',
    parameters: {
        'max-players': { display_type: 'slider', env_var: 'MAX_PLAYERS' },
        pvp: { display_type: 'boolean' },
    },
});

const twoSections = (): Json => ({
    id: 'game-ini',
    path: 'Game.ini',
    format: 'ini',
    parameters: {
        ServerSettings: { XPMultiplier: { display_type: 'slider' } },
        SessionSettings: { SessionName: { display_type: 'text' } },
    },
});

describe('appendBlankFile', () => {
    it('appends a blank file while keeping every existing file', () => {
        const files = [flatFile()];
        const next = appendBlankFile(files);

        expect(next).toHaveLength(2);
        expect(next[0]).toBe(files[0]); // existing file preserved by reference
        expect(next[1]).toMatchObject({ id: 'new-file', path: '', format: 'properties', parameters: {} });
    });

    it('does not mutate the input array', () => {
        const files = [flatFile()];
        appendBlankFile(files);
        expect(files).toHaveLength(1);
    });
});

describe('setFileField', () => {
    it('sets a top-level field immutably', () => {
        const file = flatFile();
        const next = setFileField(file, 'path', 'config/config.cfg');

        expect(next.path).toBe('config/config.cfg');
        expect(file.path).toBe('server.properties');
    });
});

describe('setExpandedByDefault', () => {
    it('adds the flag when true', () => {
        expect(setExpandedByDefault(flatFile(), true).expanded_by_default).toBe(true);
    });

    it('removes the flag when false to keep the JSON clean', () => {
        const withFlag: Json = { ...flatFile(), expanded_by_default: true };
        expect('expanded_by_default' in setExpandedByDefault(withFlag, false)).toBe(false);
    });
});

describe('setEnvVar', () => {
    it('sets then clears a flat parameter env_var (blank clears)', () => {
        const set = setEnvVar(flatFile(), null, 'pvp', 'PVP_FLAG');
        expect((set.parameters as Json).pvp).toMatchObject({ env_var: 'PVP_FLAG' });

        const cleared = setEnvVar(set, null, 'pvp', '   ');
        expect('env_var' in ((cleared.parameters as Json).pvp as Json)).toBe(false);
    });
});

describe('detectSections / flattenParams', () => {
    it('detects nested sections only', () => {
        expect(detectSections(twoSections())).toEqual(['ServerSettings', 'SessionSettings']);
        expect(detectSections(flatFile())).toEqual([]);
    });

    it('flattens flat and nested params with their env vars', () => {
        expect(flattenParams(flatFile())).toEqual([
            { section: null, key: 'max-players', envVar: 'MAX_PLAYERS' },
            { section: null, key: 'pvp', envVar: '' },
        ]);
        expect(flattenParams(twoSections())).toEqual([
            { section: 'ServerSettings', key: 'XPMultiplier', envVar: '' },
            { section: 'SessionSettings', key: 'SessionName', envVar: '' },
        ]);
    });
});

describe('toggleSection', () => {
    it('writes an explicit whitelist when hiding one of several sections', () => {
        const hidden = toggleSection({ ...twoSections(), section_whitelist: [] }, ['ServerSettings', 'SessionSettings'], 'ServerSettings');
        expect(hidden.section_whitelist).toEqual(['SessionSettings']);
    });

    it('clears the whitelist (show all) when every section becomes visible again', () => {
        const shown = toggleSection({ ...twoSections(), section_whitelist: ['SessionSettings'] }, ['ServerSettings', 'SessionSettings'], 'ServerSettings');
        expect(shown.section_whitelist).toEqual([]);
    });
});
