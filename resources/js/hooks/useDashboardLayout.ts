import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchDashboardLayout, saveDashboardLayout } from '@/services/userApi';
import type { DashboardLayout, DashboardCategory } from '@/types/DashboardLayout';
import type { Server } from '@/types/Server';
import { readLayoutCache, writeLayoutCache } from './useLayoutCache';
import { useLayoutMutations } from './useLayoutMutations';

const DEBOUNCE_MS = 400;
const QUERY_KEY = ['dashboard-layout'] as const;

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
        initialData: readLayoutCache,
        initialDataUpdatedAt: 0,
    });

    const [localLayout, setLocalLayout] = useState<DashboardLayout | null>(() => readLayoutCache());
    const [isSaving, setIsSaving] = useState(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingLayoutRef = useRef<DashboardLayout | null>(null);

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

    useEffect(() => () => {
        if (timerRef.current !== null) clearTimeout(timerRef.current);
    }, []);

    const updateLayout = useCallback(
        (updater: (prev: DashboardLayout) => DashboardLayout) => {
            setLocalLayout((prev) => {
                const next = updater(reconcileLayout(prev, servers));
                scheduleSave(next);
                writeLayoutCache(next);
                return next;
            });
        },
        [servers, scheduleSave],
    );

    const { createCategory, renameCategory, deleteCategory, moveServer, moveCategory } =
        useLayoutMutations({ updateLayout });

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

    // Pre-compute every category's server list once per (layout, servers) so
    // child components keep stable references and don't re-render needlessly.
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
