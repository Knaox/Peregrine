import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'sidebar_collapsed';

/**
 * Optional local override for the Classic/Rail sidebar — lets the user
 * temporarily collapse the panel to icon-only width (hero banner becomes
 * fully visible) without changing their saved preset. Persisted per-browser
 * via localStorage so the choice survives refreshes.
 */
export function useCollapsedSidebar(): {
    collapsed: boolean;
    toggle: () => void;
    setCollapsed: (value: boolean) => void;
} {
    const [collapsed, setCollapsedState] = useState<boolean>(() => {
        if (typeof window === 'undefined') return false;
        return window.localStorage.getItem(STORAGE_KEY) === 'true';
    });

    const setCollapsed = useCallback((value: boolean): void => {
        setCollapsedState(value);
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, value ? 'true' : 'false');
        }
    }, []);

    const toggle = useCallback((): void => {
        setCollapsed(!collapsed);
    }, [collapsed, setCollapsed]);

    // Keep tabs in sync when the user toggles from another tab.
    useEffect(() => {
        const onStorage = (e: StorageEvent): void => {
            if (e.key !== STORAGE_KEY) return;
            setCollapsedState(e.newValue === 'true');
        };
        window.addEventListener('storage', onStorage);
        return () => window.removeEventListener('storage', onStorage);
    }, []);

    return { collapsed, toggle, setCollapsed };
}
