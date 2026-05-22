import type { ConfigParam } from '../types';

/** Unit Separator (0x1F) — same delimiter the backend uses for composite keys. */
const SEP = String.fromCharCode(0x1f);

/** Stable per-field key: file id + section + key, matching the backend composite. */
export function fieldKey(fileId: string, section: string | null, key: string): string {
    return `${fileId}${SEP}${section ?? ''}${SEP}${key}`;
}

export function fieldKeyOf(fileId: string, param: ConfigParam): string {
    return fieldKey(fileId, param.section, param.key);
}

/** The backend reports field errors keyed by "section{SEP}key" within a file; prefix with the file id. */
export function backendFieldKey(fileId: string, composite: string): string {
    return `${fileId}${SEP}${composite}`;
}
