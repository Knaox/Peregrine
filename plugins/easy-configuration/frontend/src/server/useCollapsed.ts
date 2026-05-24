import { useState } from 'react';

/**
 * Collapsible open/closed state for one file or section.
 *
 * Base default is COLLAPSED; the template may seed a unit open via
 * `defaultExpanded`. The player's manual toggle is persisted in localStorage per
 * `storageKey` and wins over the template default. While the server is running,
 * `forceCollapsed` overrides the UI to closed and locks the toggle WITHOUT
 * touching localStorage — so the player's own layout is restored unchanged once
 * the server is offline again. This rule is identical for every template.
 */
export function useCollapsed(
    storageKey: string,
    defaultExpanded: boolean,
    forceCollapsed: boolean,
): { isOpen: boolean; toggle: () => void; locked: boolean } {
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

    const isOpen = forceCollapsed ? false : open;

    const toggle = (): void => {
        if (forceCollapsed) {
            return;
        }
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

    return { isOpen, toggle, locked: forceCollapsed };
}
