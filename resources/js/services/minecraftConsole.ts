export type MinecraftConsoleIssue =
    | { type: 'eula' }
    | { type: 'java'; requiredJava: number | null };

/**
 * Inspect a single (ANSI-stripped) console line for a Minecraft boot failure
 * we can offer a one-click fix for. Returns null for everything else, so it's
 * cheap to run on every incoming line.
 *
 * Java class-file versions map to a major as `major - 44` (52 → Java 8,
 * 61 → Java 17, 65 → Java 21), which lets us pre-recommend an image.
 */
export function detectMinecraftIssue(line: string): MinecraftConsoleIssue | null {
    // EULA — "You need to agree to the EULA in order to run the server."
    if (/agree to the eula/i.test(line) || /failed to load eula\.txt/i.test(line)) {
        return { type: 'eula' };
    }

    // Java too old — UnsupportedClassVersionError carries the required class
    // file version (e.g. "class file version 65.0" → Java 21).
    const classVersion = line.match(/class file version (\d+)/i);
    if (classVersion || /unsupportedclassversionerror/i.test(line)) {
        const required = classVersion ? Number(classVersion[1]) - 44 : null;
        return { type: 'java', requiredJava: required !== null && required > 0 ? required : null };
    }

    // Paper / vanilla launcher wording: "requires … Java 21", "Java 17 or above".
    const reqJava =
        line.match(/requires?[^\n]*?\bjava\s*(\d{1,2})\b/i) ??
        line.match(/\bjava\s*(\d{1,2})\s*(?:or above|or newer|or higher|\+)/i);
    if (reqJava) {
        return { type: 'java', requiredJava: Number(reqJava[1]) };
    }
    if (/this (?:build|version) of minecraft requires a newer version of java/i.test(line)) {
        return { type: 'java', requiredJava: null };
    }

    return null;
}
