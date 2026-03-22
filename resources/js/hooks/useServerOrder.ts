import { useState, useCallback, useRef } from 'react';
import type { Server } from '@/types/Server';

const STORAGE_KEY = 'peregrine-server-order';

function loadOrder(): number[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed: unknown = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.every((v): v is number => typeof v === 'number')) {
            return parsed;
        }
        return [];
    } catch {
        return [];
    }
}

function saveOrder(order: number[]): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(order));
}

export function useServerOrder() {
    const [order, setOrder] = useState<number[]>(loadOrder);
    const [dragIndex, setDragIndex] = useState<number | null>(null);
    const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);
    const holdTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const getOrderedServers = useCallback(
        (servers: Server[]): Server[] => {
            if (order.length === 0) return servers;
            const idSet = new Set(servers.map((s) => s.id));
            const ordered: Server[] = [];
            const serverMap = new Map(servers.map((s) => [s.id, s]));
            for (const id of order) {
                const srv = serverMap.get(id);
                if (srv) {
                    ordered.push(srv);
                    idSet.delete(id);
                }
            }
            for (const id of idSet) {
                const srv = serverMap.get(id);
                if (srv) ordered.push(srv);
            }
            return ordered;
        },
        [order],
    );

    const moveServer = useCallback((fromIdx: number, toIdx: number, servers: Server[]) => {
        const ids = servers.map((s) => s.id);
        const removed = ids.splice(fromIdx, 1);
        const moved = removed[0];
        if (moved === undefined) return;
        ids.splice(toIdx, 0, moved);
        setOrder(ids);
        saveOrder(ids);
    }, []);

    const startDrag = useCallback((index: number) => {
        setDragIndex(index);
    }, []);

    const dragOver = useCallback((index: number) => {
        setDragOverIndex(index);
    }, []);

    const endDrag = useCallback(() => {
        setDragIndex(null);
        setDragOverIndex(null);
        if (holdTimer.current) {
            clearTimeout(holdTimer.current);
            holdTimer.current = null;
        }
    }, []);

    const isDragging = dragIndex !== null;

    return {
        getOrderedServers,
        moveServer,
        dragIndex,
        dragOverIndex,
        isDragging,
        startDrag,
        dragOver,
        endDrag,
        holdTimer,
    };
}
