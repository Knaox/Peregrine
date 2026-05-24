import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useCollapsed } from './useCollapsed';

// A clean in-memory localStorage per test (avoids relying on Node's experimental
// global Web Storage, whose clear() isn't available under the test runner).
beforeEach(() => {
    const store = new Map<string, string>();
    vi.stubGlobal('localStorage', {
        getItem: (key: string) => store.get(key) ?? null,
        setItem: (key: string, value: string) => void store.set(key, value),
        removeItem: (key: string) => void store.delete(key),
        clear: () => store.clear(),
    });
});

afterEach(() => vi.unstubAllGlobals());

describe('useCollapsed', () => {
    it('falls back to the template default when nothing is stored', () => {
        expect(renderHook(() => useCollapsed('a', false)).result.current.isOpen).toBe(false);
        expect(renderHook(() => useCollapsed('b', true)).result.current.isOpen).toBe(true);
    });

    it('lets a stored preference win over the template default', () => {
        localStorage.setItem('a', '1');
        localStorage.setItem('b', '0');
        expect(renderHook(() => useCollapsed('a', false)).result.current.isOpen).toBe(true);
        expect(renderHook(() => useCollapsed('b', true)).result.current.isOpen).toBe(false);
    });

    it('toggles and persists the new state', () => {
        const { result } = renderHook(() => useCollapsed('a', false));

        act(() => result.current.toggle());

        expect(result.current.isOpen).toBe(true);
        expect(localStorage.getItem('a')).toBe('1');
    });
});
