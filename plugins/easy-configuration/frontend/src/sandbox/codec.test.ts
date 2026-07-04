import { describe, expect, it } from 'vitest';
import {
    decodeSandbox,
    disabledSandboxOptions,
    encodeSandbox,
    modifiedSandboxOptions,
    optionLabel,
    SANDBOX_OPTIONS,
    SandboxCodeError,
    sandboxDefaults,
    sandboxOption,
    valueLabel,
    valuesOf,
} from './codec';

/** The stock serverconfig.xml default (legacy "Adventurer" preset). */
const STOCK_ADVENTURER = 'AAAJABJACJADJARFBNC';

/** A rich in-game-generated code (25 non-default options) for round-trip coverage. */
const IN_GAME_CODE = 'AAAGABGACGADGARJBAABBAAMLBMCBPFBZFCABCOHCXBDMNDBJDCIDDIDEIDJIFEDFIDFFGETKERG';

describe('sandbox codec', () => {
    it('round-trips a rich in-game-generated code byte-identically', () => {
        const { state, unknownRecords } = decodeSandbox(IN_GAME_CODE);

        expect(unknownRecords).toBe(0);
        expect(encodeSandbox(state)).toBe(IN_GAME_CODE);
    });

    it('decodes and round-trips the stock Adventurer default', () => {
        const { state, unknownRecords } = decodeSandbox(STOCK_ADVENTURER);

        expect(unknownRecords).toBe(0);
        expect(state.RangedDamage).toBe(1.5);
        expect(state.MeleeDamage).toBe(1.5);
        expect(state.IncomingDamage).toBe(0.75);
        expect(encodeSandbox(state)).toBe(STOCK_ADVENTURER);
    });

    it('localises option labels, value labels and booleans in French', () => {
        const ranged = sandboxOption('RangedDamage')!;
        expect(optionLabel(ranged, 'en')).toBe('Ranged Damage');
        expect(optionLabel(ranged, 'fr')).toBe('Dégâts à distance');
        expect(valueLabel(ranged, 0, 'en')).toBe('None');
        expect(valueLabel(ranged, 0, 'fr')).toBe('Aucun');

        const digging = sandboxOption('AllowZombieDigging')!;
        const yesIndex = valuesOf(digging).findIndex((value) => value === true);
        expect(valueLabel(digging, yesIndex, 'fr')).toBe('Oui');
    });

    it('encodes the defaults as the bare version letter', () => {
        expect(encodeSandbox(sandboxDefaults())).toBe('A');
        expect(modifiedSandboxOptions(sandboxDefaults())).toEqual([]);
    });

    it('decoding overlays the defaults for untouched options', () => {
        const { state } = decodeSandbox('AAAJ');

        expect(Object.keys(state)).toHaveLength(SANDBOX_OPTIONS.length);
        expect(state.RangedDamage).toBe(1.5);
    });

    it('accepts lowercase and surrounding whitespace', () => {
        expect(decodeSandbox('  aaaj ').state.RangedDamage).toBe(1.5);
    });

    it('counts unknown records and keeps decoding known ones', () => {
        // ZZ = enumIndex 675, far above any known option.
        const { state, unknownRecords } = decodeSandbox('AZZAAAJ');

        expect(unknownRecords).toBe(1);
        expect(state.RangedDamage).toBe(1.5);
    });

    it('rejects a wrong version, bad length, bad characters and off-ladder indexes', () => {
        expect(() => decodeSandbox('BAAJ')).toThrow(SandboxCodeError);
        expect(() => decodeSandbox('AAA')).toThrow(SandboxCodeError);
        expect(() => decodeSandbox('A1AJ')).toThrow(SandboxCodeError);
        expect(() => decodeSandbox('AAAZ')).toThrow(SandboxCodeError); // DamageValues has 13 rungs, Z=25
        expect(() => decodeSandbox('')).toThrow(SandboxCodeError);
    });

    it('throws when encoding a value that is not on the option ladder', () => {
        expect(() => encodeSandbox({ RangedDamage: 0.33 })).toThrow(SandboxCodeError);
    });

    it('applies disables rules to grey out dependent options', () => {
        const carrier = SANDBOX_OPTIONS.find((option) => option.disables !== undefined);
        expect(carrier).toBeDefined();

        const rule = carrier!.disables!;
        const triggering = rule.inverted
            ? valuesOf(carrier!).find((value) => value !== rule.whenValue)!
            : rule.whenValue;

        const disabled = disabledSandboxOptions({ ...sandboxDefaults(), [carrier!.option]: triggering });
        for (const name of rule.options) {
            expect(disabled.has(name)).toBe(true);
        }
    });
});
