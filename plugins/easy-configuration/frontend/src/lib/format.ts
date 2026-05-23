/** Locale-aware short date-time for boost windows. Falls back to the raw string. */
export function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? iso : date.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
}

/** Value for a `datetime-local` input (YYYY-MM-DDTHH:mm) from a Date. */
export function toLocalInput(date: Date): string {
    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** Config parser format from a file name's extension. Mirrors the backend's
 * ConfigImportScaffolder::EXTENSION_FORMATS. Returns undefined when unknown so
 * the import endpoint stays the single source of truth (it rejects with 422). */
const EXTENSION_FORMATS: Record<string, string> = {
    properties: 'properties',
    ini: 'ini',
    cfg: 'ini',
    conf: 'ini',
    toml: 'toml',
    yml: 'yaml',
    yaml: 'yaml',
    json: 'json',
};

export function extensionToFormat(name: string): string | undefined {
    const dot = name.lastIndexOf('.');
    if (dot < 0) {
        return undefined;
    }

    return EXTENSION_FORMATS[name.slice(dot + 1).toLowerCase()];
}

/** Short human file size, e.g. 1536 → "1.5 KB". Directories pass size 0 → "". */
export function formatBytes(bytes: number): string {
    if (!bytes || bytes <= 0) {
        return '';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / 1024 ** exponent;

    return `${value % 1 === 0 ? value : value.toFixed(1)} ${units[exponent]}`;
}
