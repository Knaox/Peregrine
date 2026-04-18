import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchDashboardLayout, saveDashboardLayout } from '@/services/userApi';
import type { DashboardLayout, DashboardCategory } from '@/types/DashboardLayout';
import type { Server } from '@/types/Server';

const DEBOUNCE_MS = 400;
const QUERY_KEY = ['dashboard-layout'] as const;

function generateCategoryId(): string {
    return 'cat_' + Math.random().toString(36).slice(2, 10);
}

function reconcileLayout(layout: DashboardLayout | null, servers: Server[]): DashboardLayout {
    const currentIds = new Set(servers.map((s) => s.id));
    const placed = new Set<number>();

    const categories: DashboardCategory[] = (layout?.categories ?? []).map((cat) => {
        const filtered = cat.serverIds.filter((id) => {
            if (!currentIds.has(id) || placed.has(id)) return false;
            placed.add(id);
            return true;
        });
        return { ...cat, serverIds: filtered };
    });

    const uncategorizedOrder = (layout?.uncategorizedOrder ?? []).filter((id) => {
        if (!currentIds.has(id) || placed.has(id)) return false;
        placed.add(id);
        return true;
    });

    for (const id of currentIds) {
        if (!placed.has(id)) uncategorizedOrder.push(id);
    }

    return { categories, uncategorizedOrder };
}

function insertAt<T>(arr: T[], index: number, item: T): T[] {
    const idx = Math.min(index, arr.length);
    return [...arr.slice(0, idx), item, ...arr.slice(idx)];
}

interface UseDashboardLayoutReturn {
    categories: DashboardCategory[];
    uncategorizedServers: Server[];
    getServersForCategory: (categoryId: string) => Server[];
    hasCustomLayout: boolean;
    isLoading: boolean;
    isSaving: boolean;
    createCategory: (name: string) => void;
    renameCategory: (categoryId: string, name: string) => void;
    deleteCategory: (categoryId: string) => void;
    moveServer: (serverId: number, targetZoneId: string, insertIndex: number) => void;
    moveCategory: (categoryId: string, newIndex: number) => void;
    resetLayout: () => void;
}

export function useDashboardLayout(servers: Server[]): UseDashboardLayoutReturn {
    const queryClient = useQueryClient();
    const query = useQuery<DashboardLayout | null>({
        queryKey: QUERY_KEY,
        queryFn: fetchDashboardLayout,
        staleTime: Infinity,
    });

    const [localLayout, setLocalLayout] = useState<DashboardLayout | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingLayoutRef = useRef<DashboardLayout | null>(null);

    // Sync from query data when it first loads or changes
    useEffect(() => {
        if (query.data !== undefined) setLocalLayout(query.data);
    }, [query.data]);

    const reconciledLayout = useMemo(
        () => reconcileLayout(localLayout, servers),
        [localLayout, servers],
    );

    const serverMap = useMemo(() => {
        const map = new Map<number, Server>();
        for (const server of servers) map.set(server.id, server);
        return map;
    }, [servers]);

    // Debounced save to backend
    const scheduleSave = useCallback((layout: DashboardLayout) => {
        pendingLayoutRef.current = layout;
        if (timerRef.current !== null) clearTimeout(timerRef.current);

        timerRef.current = setTimeout(() => {
            timerRef.current = null;
            const toSave = pendingLayoutRef.current;
            if (!toSave) return;
            pendingLayoutRef.current = null;
            setIsSaving(true);
            saveDashboardLayout(toSave)
                .then((saved) => queryClient.setQueryData(QUERY_KEY, saved))
                .catch(() => { /* local state remains; next mutation retries */ })
                .finally(() => setIsSaving(false));
        }, DEBOUNCE_MS);
    }, [queryClient]);

    // Flush pending save on page unload
    useEffect(() => {
        const handleBeforeUnload = () => {
            const pending = pendingLayoutRef.current;
            if (!pending) return;
            pendingLayoutRef.current = null;
            if (timerRef.current !== null) {
                clearTimeout(timerRef.current);
                timerRef.current = null;
            }
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            fetch('/api/user/dashboard-layout', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ layout: pending }),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => { /* best-effort */ });
        };
        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, []);

    // Clean up debounce timer on unmount
    useEffect(() => () => {
        if (timerRef.current !== null) clearTimeout(timerRef.current);
    }, []);

    // Apply an update to the layout and schedule a backend save
    const updateLayout = useCallback(
        (updater: (prev: DashboardLayout) => DashboardLayout) => {
            setLocalLayout((prev) => {
                const next = updater(reconcileLayout(prev, servers));
                scheduleSave(next);
                return next;
            });
        },
        [servers, scheduleSave],
    );

    const createCategory = useCallback((name: string) => {
        updateLayout((layout) => ({
            ...layout,
            categories: [...layout.categories, { id: generateCategoryId(), name, serverIds: [] }],
        }));
    }, [updateLayout]);

    const renameCategory = useCallback((categoryId: string, name: string) => {
        updateLayout((layout) => ({
            ...layout,
            categories: layout.categories.map((c) =>
                c.id === categoryId ? { ...c, name } : c,
            ),
        }));
    }, [updateLayout]);

    const deleteCategory = useCallback((categoryId: string) => {
        updateLayout((layout) => {
            const target = layout.categories.find((c) => c.id === categoryId);
            return {
                categories: layout.categories.filter((c) => c.id !== categoryId),
                uncategorizedOrder: [...layout.uncategorizedOrder, ...(target?.serverIds ?? [])],
            };
        });
    }, [updateLayout]);

    const moveServer = useCallback(
        (serverId: number, targetZoneId: string, insertIndex: number) => {
            updateLayout((layout) => {
                // Remove serverId from everywhere
                const categories = layout.categories.map((cat) => ({
                    ...cat,
                    serverIds: cat.serverIds.filter((id) => id !== serverId),
                }));
                const uncategorized = layout.uncategorizedOrder.filter((id) => id !== serverId);

                // Insert at target zone
                if (targetZoneId === 'uncategorized') {
                    return {
                        categories,
                        uncategorizedOrder: insertAt(uncategorized, insertIndex, serverId),
                    };
                }
                const catIdx = categories.findIndex((c) => c.id === targetZoneId);
                const targetCat = categories[catIdx];
                if (catIdx !== -1 && targetCat) {
                    categories[catIdx] = {
                        id: targetCat.id,
                        name: targetCat.name,
                        serverIds: insertAt(targetCat.serverIds, insertIndex, serverId),
                    };
                }
                return { categories, uncategorizedOrder: uncategorized };
            });
        },
        [updateLayout],
    );

    const moveCategory = useCallback(
        (categoryId: string, newIndex: number) => {
            updateLayout((layout) => {
                const fromIdx = layout.categories.findIndex((c) => c.id === categoryId);
                if (fromIdx === -1) return layout;
                const moved = layout.categories[fromIdx];
                if (!moved) return layout;
                const without = layout.categories.filter((c) => c.id !== categoryId);
                const clamped = Math.min(newIndex, without.length);
                const reordered = [...without.slice(0, clamped), moved, ...without.slice(clamped)];
                return { ...layout, categories: reordered };
            });
        },
        [updateLayout],
    );

    const resetLayout = useCallback(() => {
        if (timerRef.current !== null) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }
        pendingLayoutRef.current = null;
        setLocalLayout(null);
        queryClient.setQueryData(QUERY_KEY, null);
        setIsSaving(true);
        saveDashboardLayout({ categories: [], uncategorizedOrder: [] })
            .then(() => queryClient.invalidateQueries({ queryKey: QUERY_KEY }))
            .catch(() => { /* best-effort reset */ })
            .finally(() => setIsSaving(false));
    }, [queryClient]);

    const uncategorizedServers = useMemo(
        () => reconciledLayout.uncategorizedOrder
            .map((id) => serverMap.get(id))
            .filter((s): s is Server => s !== undefined),
        [reconciledLayout.uncategorizedOrder, serverMap],
    );

    const getServersForCategory = useCallback(
        (categoryId: string): Server[] => {
            const cat = reconciledLayout.categories.find((c) => c.id === categoryId);
            if (!cat) return [];
            return cat.serverIds
                .map((id) => serverMap.get(id))
                .filter((s): s is Server => s !== undefined);
        },
        [reconciledLayout.categories, serverMap],
    );

    const hasCustomLayout = useMemo(
        () => localLayout !== null && localLayout.categories.length > 0,
        [localLayout],
    );

    return {
        categories: reconciledLayout.categories,
        uncategorizedServers,
        getServersForCategory,
        hasCustomLayout,
        isLoading: query.isLoading,
        isSaving,
        createCategory,
        renameCategory,
        deleteCategory,
        moveServer,
        moveCategory,
        resetLayout,
    };
}
