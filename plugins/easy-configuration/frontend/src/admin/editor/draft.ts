/** The template editor's working state — a flat, form-friendly view of a template. */
export interface Draft {
    id: string;
    version: string;
    nameEn: string;
    nameFr: string;
    descEn: string;
    descFr: string;
    author: string;
    targetEggs: number[];
    boostEnabled: boolean;
    blacklist: string;
    columns: number;
    /** Require the server to be stopped before any value can be edited. */
    requireShutdown: boolean;
    filesJson: string;
}

// A new template starts with no files — the admin imports real ones from a
// server (or pastes JSON). A fake starter file used to stay attached even when
// it didn't match the server, which was confusing.
const STARTER_FILES = '[]';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function str(value: unknown, fallback = ''): string {
    return typeof value === 'string' ? value : fallback;
}

export function blankDraft(): Draft {
    return {
        id: '',
        version: '1.0.0',
        nameEn: '',
        nameFr: '',
        descEn: '',
        descFr: '',
        author: '',
        targetEggs: [],
        boostEnabled: false,
        blacklist: '',
        columns: 1,
        requireShutdown: true,
        filesJson: STARTER_FILES,
    };
}

/**
 * Build a Draft from a template definition — used when editing an existing
 * template, opening the example, and loading the JSON an AI returns into the
 * editor for review.
 */
export function draftFrom(id: string, def: Record<string, unknown> | null): Draft {
    if (def === null) {
        return { ...blankDraft(), id };
    }

    const name = isRecord(def.name) ? def.name : {};
    const description = isRecord(def.description) ? def.description : {};
    const boost = isRecord(def.boost) ? def.boost : {};
    const blacklist = Array.isArray(boost.parameter_blacklist) ? boost.parameter_blacklist.map(String) : [];
    const eggs = Array.isArray(def.target_eggs) ? def.target_eggs.filter((v): v is number => typeof v === 'number') : [];

    return {
        id,
        version: str(def.version, '1.0.0'),
        nameEn: str(name.en),
        nameFr: str(name.fr),
        descEn: str(description.en),
        descFr: str(description.fr),
        author: str(def.author),
        targetEggs: eggs,
        boostEnabled: boost.enabled === true,
        blacklist: blacklist.join(', '),
        columns: typeof def.columns === 'number' ? def.columns : 1,
        requireShutdown: def.require_shutdown !== false,
        filesJson: JSON.stringify(def.files ?? [], null, 2),
    };
}
