import type { ConfigParam } from '../types';

/** Unit Separator (0x1F) — same delimiter the backend uses for composite keys. */
const SEP = String.fromCharCode(0x1f);

/** Stable per-field key: file id + section + key, matching the backend composite. */
export function fieldKey(fileId: string, section: string | null, key: string): string {
    return `${fileId}${SEP}${section ?? ''}${SEP}${key}`;
}

export function fieldKeyOf(fileId: string, param: ConfigParam): string {
    const base = fieldKey(fileId, param.section, param.key);
    // Repeated keys (occurrence > 0) get a unique suffix so each line has its own
    // editor state + React key; occurrence 0 stays unchanged (no regression).
    return param.occurrence && param.occurrence > 0 ? `${base}${SEP}${param.occurrence}` : base;
}

/** The backend reports field errors keyed by "section{SEP}key" within a file; prefix with the file id. */
export function backendFieldKey(fileId: string, composite: string): string {
    return `${fileId}${SEP}${composite}`;
}
