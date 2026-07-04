import { describe, expect, it } from 'vitest';
import {
    decodeSandbox,
    disabledSandboxOptions,
    encodeSandbox,
    modifiedSandboxOptions,
    SANDBOX_OPTIONS,
    SandboxCodeError,
    sandboxDefaults,
    valuesOf,
} from './codec';

/** The stock serverconfig.xml ships this code for the Adventurer preset. */
const ADVENTURER = 'AAAGABGACGADGARJBAABBAAMLBMCBPFBZFCABCOHCXBDMNDBJDCIDDIDEIDJIFEDFIDFFGETKERG';

describe('sandbox codec', () => {
    it('round-trips the official Adventurer preset byte-identically', () => {
        const { state, unknownRecords } = decodeSandbox(ADVENTURER);

        expect(unknownRecords).toBe(0);
        expect(encodeSandbox(state)).toBe(ADVENTURER);
    });

    it('decodes the example from the game comment', () => {
        const { state, unknownRecords } = decodeSandbox('AAAJABJACJADJARFBNC');

        expect(unknownRecords).toBe(0);
        expect(state.RangedDamage).toBe(1.5);
        expect(state.MeleeDamage).toBe(1.5);
        expect(state.IncomingDamage).toBe(0.75);
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
