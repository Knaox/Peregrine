import { useState, useCallback } from 'react';

export function useServerSelection() {
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [isSelectionMode, setIsSelectionMode] = useState(false);

    const toggleSelectionMode = useCallback(() => {
        setIsSelectionMode((prev) => {
            if (prev) setSelectedIds(new Set());
            return !prev;
        });
    }, []);

    const toggleSelect = useCallback((serverId: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(serverId)) {
                next.delete(serverId);
            } else {
                next.add(serverId);
            }
            return next;
        });
    }, []);

    const selectAll = useCallback((serverIds: number[]) => {
        setSelectedIds(new Set(serverIds));
    }, []);

    const deselectAll = useCallback(() => {
        setSelectedIds(new Set());
    }, []);

    const isSelected = useCallback(
        (serverId: number): boolean => selectedIds.has(serverId),
        [selectedIds],
    );

    return {
        selectedIds,
        isSelectionMode,
        toggleSelectionMode,
        toggleSelect,
        selectAll,
        deselectAll,
        isSelected,
    };
}
