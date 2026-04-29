import { useCallback } from 'react';
import type { DashboardLayout } from '@/types/DashboardLayout';

function generateCategoryId(): string {
    return 'cat_' + Math.random().toString(36).slice(2, 10);
}

function insertAt<T>(arr: T[], index: number, item: T): T[] {
    const idx = Math.min(index, arr.length);
    return [...arr.slice(0, idx), item, ...arr.slice(idx)];
}

interface UseLayoutMutationsParams {
    updateLayout: (updater: (prev: DashboardLayout) => DashboardLayout) => void;
}

interface LayoutMutations {
    createCategory: (name: string) => void;
    renameCategory: (categoryId: string, name: string) => void;
    deleteCategory: (categoryId: string) => void;
    moveServer: (serverId: number, targetZoneId: string, insertIndex: number) => void;
    moveCategory: (categoryId: string, newIndex: number) => void;
}

/**
 * Layout CRUD mutations factored out of useDashboardLayout to keep that
 * hook under the 300-line plafond CLAUDE.md. All mutations go through the
 * single `updateLayout(prev => next)` callback the parent hook owns —
 * keeping the debounce + localStorage mirror logic centralised.
 */
export function useLayoutMutations({ updateLayout }: UseLayoutMutationsParams): LayoutMutations {
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
                const categories = layout.categories.map((cat) => ({
                    ...cat,
                    serverIds: cat.serverIds.filter((id) => id !== serverId),
                }));
                const uncategorized = layout.uncategorizedOrder.filter((id) => id !== serverId);

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

    return { createCategory, renameCategory, deleteCategory, moveServer, moveCategory };
}
