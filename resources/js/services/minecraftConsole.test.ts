import { describe, expect, it } from 'vitest';
import { detectMinecraftIssue } from './minecraftConsole';

describe('detectMinecraftIssue', () => {
    it('detects the EULA boot failure', () => {
        expect(
            detectMinecraftIssue(
                '[15:02:01] [main/INFO]: You need to agree to the EULA in order to run the server. Go to eula.txt for more info.',
            ),
        ).toEqual({ type: 'eula' });
        expect(detectMinecraftIssue('[main/WARN]: Failed to load eula.txt')).toEqual({ type: 'eula' });
    });

    it('derives the required Java major from the class file version', () => {
        const line =
            'Caused by: java.lang.UnsupportedClassVersionError: net/minecraft/server: class file version 65.0, this version of the Java Runtime only recognizes class file versions up to 52.0';
        expect(detectMinecraftIssue(line)).toEqual({ type: 'java', requiredJava: 21 });
    });

    it('detects Paper-style java-version wording', () => {
        expect(
            detectMinecraftIssue('Minecraft 1.20.5 requires running the server with Java 21 or above'),
        ).toEqual({ type: 'java', requiredJava: 21 });
    });

    it('flags an unversioned UnsupportedClassVersionError', () => {
        expect(detectMinecraftIssue('java.lang.UnsupportedClassVersionError')).toEqual({
            type: 'java',
            requiredJava: null,
        });
    });

    it('ignores ordinary log lines', () => {
        expect(detectMinecraftIssue('[Server thread/INFO]: Done (3.214s)! For help, type "help"')).toBeNull();
        expect(detectMinecraftIssue('Starting minecraft server version 1.20.4')).toBeNull();
    });
});
