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
