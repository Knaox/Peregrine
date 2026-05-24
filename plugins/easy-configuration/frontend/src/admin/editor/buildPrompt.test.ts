import { describe, expect, it } from 'vitest';
import type { Json } from '../../lib/templateFiles';
import { buildPrompt, fileParamsForPrompt, promptFilesFrom, type PromptInput } from './buildPrompt';

const flatBlock: Json = {
    id: 'server-properties',
    path: 'server.properties',
    format: 'properties',
    expanded_by_default: true,
    parameters: {
        'max-players': { display_type: 'slider', config: { default: '20', min: 1, max: 100 } },
        pvp: { display_type: 'boolean', config: { default: 'true', true_value: 'true', false_value: 'false' } },
    },
};

const sectionedBlock: Json = {
    id: 'game-ini',
    path: 'Game.ini',
    format: 'ini',
    parameters: { ServerSettings: { XPMultiplier: { display_type: 'slider', config: { default: '3' } } } },
};

describe('fileParamsForPrompt', () => {
    it('extracts flat params with current value + detected type', () => {
        expect(fileParamsForPrompt(flatBlock)).toEqual([
            { section: null, key: 'max-players', value: '20', type: 'slider' },
            { section: null, key: 'pvp', value: 'true', type: 'boolean' },
        ]);
    });

    it('extracts nested section params', () => {
        expect(fileParamsForPrompt(sectionedBlock)).toEqual([{ section: 'ServerSettings', key: 'XPMultiplier', value: '3', type: 'slider' }]);
    });
});

describe('promptFilesFrom', () => {
    it('maps scaffolded blocks to the prompt file model', () => {
        const [a, b] = promptFilesFrom([flatBlock, sectionedBlock]);
        expect(a).toMatchObject({ id: 'server-properties', format: 'properties', expandedByDefault: true });
        expect(a.params).toHaveLength(2);
        expect(b.expandedByDefault).toBe(false); // no flag → collapsed
    });
});

describe('buildPrompt', () => {
    const input: PromptInput = {
        id: 'my-tpl',
        nameEn: 'My',
        nameFr: 'Mon',
        descEn: 'd',
        descFr: 'D',
        author: 'Me',
        targetEggs: [54],
        columns: 2,
        boostEnabled: true,
        blacklist: ['server-port'],
        files: promptFilesFrom([flatBlock]),
        envLinks: [{ fileId: 'server-properties', section: null, key: 'max-players', envVar: 'MAX_PLAYERS' }],
    };
    const prompt = buildPrompt(input);

    it('includes the output format spec and the JSON-only rule', () => {
        expect(prompt).toContain('### OUTPUT FORMAT');
        expect(prompt).toContain('"display_type"');
        expect(prompt).toContain('Output JSON only.');
    });

    it('includes the chosen metadata verbatim', () => {
        expect(prompt).toContain('id: my-tpl');
        expect(prompt).toContain('columns: 2');
        expect(prompt).toContain('boost.enabled: true');
        expect(prompt).toContain('[54]');
    });

    it('lists every parameter with its current value and type', () => {
        expect(prompt).toContain('max-players = 20 (slider)');
        expect(prompt).toContain('pvp = true (boolean)');
    });

    it('includes the explicit env links', () => {
        expect(prompt).toContain('param "max-players" → env_var "MAX_PLAYERS"');
    });
});

describe('section whitelist', () => {
    const whitelisted: Json = {
        id: 'gus',
        path: 'GameUserSettings.ini',
        format: 'ini',
        section_whitelist: ['ServerSettings'],
        parameters: {
            ServerSettings: { XP: { display_type: 'slider', config: { default: '3' } } },
            SessionSettings: { SessionName: { display_type: 'text', config: { default: 'srv' } } },
        },
    };

    it('drops params of non-whitelisted sections', () => {
        expect(fileParamsForPrompt(whitelisted)).toEqual([{ section: 'ServerSettings', key: 'XP', value: '3', type: 'slider' }]);
    });

    it('carries the whitelist into the file model and the prompt', () => {
        const [file] = promptFilesFrom([whitelisted]);
        expect(file.sectionWhitelist).toEqual(['ServerSettings']);

        const prompt = buildPrompt({
            id: 't',
            nameEn: 'T',
            nameFr: 'T',
            descEn: '',
            descFr: '',
            author: '',
            targetEggs: [],
            columns: 1,
            boostEnabled: false,
            blacklist: [],
            files: [file],
            envLinks: [],
        });
        expect(prompt).toContain('section_whitelist: ["ServerSettings"]');
        expect(prompt).toContain('XP = 3 (slider)');
        expect(prompt).not.toContain('SessionName');
    });
});
