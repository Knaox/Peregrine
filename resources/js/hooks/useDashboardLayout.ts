import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchDashboardLayout, saveDashboardLayout } from '@/services/userApi';
import type { DashboardLayout, DashboardCategory } from '@/types/DashboardLayout';
import type { Server } from '@/types/Server';

const DEBOUNCE_MS = 400;
const QUERY_KEY = ['dashboard-layout'] as const;
const CACHE_KEY = 'peregrine.dashboard-layout.v1';

/**
 * Read the last-saved layout from localStorage so a hard refresh renders
 * the categorised dashboard *immediately* with the user's last-known
 * arrangement, before /api/user/dashboard-layout resolves. The query
 * revalidates in the background; if the server returns a different shape
 * we silently transition to it.
 */
function readLayoutCache(): DashboardLayout | null {
    try {
        const raw = localStorage.getItem(CACHE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as { layout?: DashboardLayout; cachedAt?: number };
        if (parsed.cachedAt && Date.now() - parsed.cachedAt > 7 * 24 * 60 * 60 * 1000) return null;
        if (!parsed.layout || !Array.isArray(parsed.layout.categories) || !Array.isArray(parsed.layout.uncategorizedOrder)) {
            return null;
        }
        return parsed.layout;
    } catch {
        return null;
    }
}

function writeLayoutCache(layout: DashboardLayout | null): void {
    try {
        if (layout === null) {
            localStorage.removeItem(CACHE_KEY);
            return;
        }
        localStorage.setItem(CACHE_KEY, JSON.stringify({ layout, cachedAt: Date.now() }));
    } catch {
        // localStorage full / disabled — silently skip; cache is optional
    }
}

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
        queryFn: async () => {
            const layout = await fetchDashboardLayout();
            writeLayoutCache(layout);
            return layout;
        },
        staleTime: Infinity,
        // Bootstrap from localStorage so refresh renders categories instantly
        // — without this the dashboard shows uncategorised cards for ~200ms
        // until the API responds, then re-shuffles into categories (visible
        // flicker for users with many categories).
        initialData: readLayoutCache,
        initialDataUpdatedAt: 0,
    });

    const [localLayout, setLocalLayout] = useState<DashboardLayout | null>(() => readLayoutCache());
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
                // Mirror to localStorage immediately so a refresh picks up
                // the user's latest gesture even if the debounced backend
                // save hasn't fired yet.
                writeLayoutCache(next);
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
        writeLayoutCache(null);
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

    // Pre-compute every category's server list once per (layout, servers)
    // change. Without this, calling `getServersForCategory(catId)` inside
    // the render loop would build a fresh `Server[]` on every render →
    // ServerGrid's useMemo on `filtered` invalidates → every card re-renders.
    // With the cached map the array reference is stable across renders.
    const serversByCategoryId = useMemo(() => {
        const map = new Map<string, Server[]>();
        for (const cat of reconciledLayout.categories) {
            map.set(
                cat.id,
                cat.serverIds
                    .map((id) => serverMap.get(id))
                    .filter((s): s is Server => s !== undefined),
            );
        }
        return map;
    }, [reconciledLayout.categories, serverMap]);

    const getServersForCategory = useCallback(
        (categoryId: string): Server[] => serversByCategoryId.get(categoryId) ?? [],
        [serversByCategoryId],
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
