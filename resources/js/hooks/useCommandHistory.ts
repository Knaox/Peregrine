import { useState, useCallback, useRef } from 'react';

const MAX_HISTORY = 50;

function getStorageKey(serverId: number): string {
    return `console-history-${serverId}`;
}

function loadHistory(serverId: number): string[] {
    try {
        const raw = localStorage.getItem(getStorageKey(serverId));
        if (!raw) return [];
        return JSON.parse(raw) as string[];
    } catch {
        return [];
    }
}

function saveHistory(serverId: number, history: string[]): void {
    localStorage.setItem(getStorageKey(serverId), JSON.stringify(history));
}

export function useCommandHistory(serverId: number) {
    const historyRef = useRef<string[]>(loadHistory(serverId));
    const [historyIndex, setHistoryIndex] = useState(-1);

    const addCommand = useCallback((cmd: string) => {
        const trimmed = cmd.trim();
        if (!trimmed) return;

        const updated = [trimmed, ...historyRef.current.filter((c) => c !== trimmed)];
        if (updated.length > MAX_HISTORY) {
            updated.length = MAX_HISTORY;
        }
        historyRef.current = updated;
        saveHistory(serverId, updated);
        setHistoryIndex(-1);
    }, [serverId]);

    const navigateUp = useCallback((): string => {
        const history = historyRef.current;
        if (history.length === 0) return '';
        const nextIndex = Math.min(historyIndex + 1, history.length - 1);
        setHistoryIndex(nextIndex);
        return history[nextIndex] ?? '';
    }, [historyIndex]);

    const navigateDown = useCallback((): string => {
        if (historyIndex <= 0) {
            setHistoryIndex(-1);
            return '';
        }
        const nextIndex = historyIndex - 1;
        setHistoryIndex(nextIndex);
        return historyRef.current[nextIndex] ?? '';
    }, [historyIndex]);

    const reset = useCallback(() => {
        setHistoryIndex(-1);
    }, []);

    return { addCommand, navigateUp, navigateDown, reset };
}
