import { useState } from 'react';

/**
 * Collapsible open/closed state for one file or section.
 *
 * Base default is COLLAPSED; the template may seed a unit open via
 * `defaultExpanded`. The player's manual toggle is persisted in localStorage per
 * `storageKey` and wins over the template default. The layout is identical
 * whether the server is running or offline — running only makes the editor
 * read-only, it never changes what is expanded.
 */
export function useCollapsed(storageKey: string, defaultExpanded: boolean): { isOpen: boolean; toggle: () => void } {
    const [open, setOpen] = useState<boolean>(() => {
        try {
            const stored = localStorage.getItem(storageKey);
            if (stored === '1') {
                return true;
            }
            if (stored === '0') {
                return false;
            }
        } catch {
            /* localStorage unavailable — fall back to the template default */
        }

        return defaultExpanded;
    });

    const toggle = (): void => {
        setOpen((current) => {
            const next = !current;
            try {
                localStorage.setItem(storageKey, next ? '1' : '0');
            } catch {
                /* localStorage unavailable — keep in-memory only */
            }

            return next;
        });
    };

    return { isOpen: open, toggle };
}
