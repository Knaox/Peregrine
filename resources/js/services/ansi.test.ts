import { describe, expect, it } from 'vitest';
import { stripAnsi } from './ansi';

describe('stripAnsi', () => {
    it('strips private-mode cursor show/hide sequences (the Sons of the Forest bug)', () => {
        // The exact garbage SotF spams character-by-character: ESC[?25l ESC[?25h.
        expect(stripAnsi('\x1b[?25lS\x1b[?25h\x1b[?25le')).toBe('Se');
        expect(stripAnsi('\x1b[?25lStarting\x1b[?25h')).toBe('Starting');
    });

    it('strips SGR colour codes', () => {
        expect(stripAnsi('\x1b[31mred\x1b[0m')).toBe('red');
        expect(stripAnsi('\x1b[1;32mok\x1b[0m')).toBe('ok');
    });

    it('strips cursor-movement and erase sequences', () => {
        expect(stripAnsi('\x1b[2K\x1b[1Gline')).toBe('line');
    });

    it('strips OSC window-title sequences', () => {
        expect(stripAnsi('\x1b]0;my title\x07done')).toBe('done');
    });

    it('leaves plain text untouched', () => {
        expect(stripAnsi('#DSL Self tests passed.')).toBe('#DSL Self tests passed.');
        expect(stripAnsi('GetAdaptersAddresses returned 2')).toBe('GetAdaptersAddresses returned 2');
    });
});
