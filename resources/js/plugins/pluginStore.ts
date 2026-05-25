import { create } from 'zustand';
import type { PluginManifest } from '@/plugins/types';
import type { Database } from '@/types/Database';
import { useSaveCoordinatorStore } from '@/stores/saveCoordinatorStore';

interface PluginStore {
    manifests: PluginManifest[];
    components: Record<string, React.ComponentType>;
    serverPageComponents: Record<string, React.ComponentType>;
    serverHomeSectionComponents: Record<string, React.ComponentType<{ serverId: number }>>;
    databaseRowActionComponents: Record<string, React.ComponentType<{ serverId: number; database: Database }>>;
    isLoading: boolean;
    isInitialized: boolean;

    setManifests: (manifests: PluginManifest[]) => void;
    setLoading: (loading: boolean) => void;
    registerComponent: (pluginId: string, component: React.ComponentType) => void;
    registerServerPage: (pageId: string, component: React.ComponentType) => void;
    registerServerHomeSection: (sectionId: string, component: React.ComponentType<{ serverId: number }>) => void;
    registerDatabaseRowAction: (id: string, component: React.ComponentType<{ serverId: number; database: Database }>) => void;
    getComponent: (pluginId: string) => React.ComponentType | undefined;
    init: () => void;
}

export const usePluginStore = create<PluginStore>((set, get) => ({
    manifests: [],
    components: {},
    serverPageComponents: {},
    serverHomeSectionComponents: {},
    databaseRowActionComponents: {},
    isLoading: true,
    isInitialized: false,

    setManifests: (manifests) => set({ manifests }),
    setLoading: (isLoading) => set({ isLoading }),

    registerComponent: (pluginId, component) => {
        set((state) => ({
            components: { ...state.components, [pluginId]: component },
        }));
    },

    registerServerPage: (pageId, component) => {
        set((state) => ({
            serverPageComponents: { ...state.serverPageComponents, [pageId]: component },
        }));
    },

    registerServerHomeSection: (sectionId, component) => {
        set((state) => ({
            serverHomeSectionComponents: { ...state.serverHomeSectionComponents, [sectionId]: component },
        }));
    },

    registerDatabaseRowAction: (id, component) => {
        set((state) => ({
            databaseRowActionComponents: { ...state.databaseRowActionComponents, [id]: component },
        }));
    },

    getComponent: (pluginId) => get().components[pluginId],

    init: () => {
        if (get().isInitialized) return;

        window.__PEREGRINE_PLUGINS__ = {
            register: (pluginId: string, component: React.ComponentType) => {
                get().registerComponent(pluginId, component);
            },
            registerServerPage: (pageId: string, component: React.ComponentType) => {
                get().registerServerPage(pageId, component);
            },
            registerServerHomeSection: (sectionId: string, component: React.ComponentType<{ serverId: number }>) => {
                get().registerServerHomeSection(sectionId, component);
            },
            registerDatabaseRowAction: (id: string, component: React.ComponentType<{ serverId: number; database: Database }>) => {
                get().registerDatabaseRowAction(id, component);
            },
            // Plugin → shell bridge for long-running operation lifecycle.
            // Implemented as plain DOM CustomEvents so the listener side
            // (resources/js/hooks/useServerOperationLifecycle.ts) doesn't
            // need a direct dependency on this store, and so plugin bundles
            // can fire from any context (queueMicrotask, mutation onSuccess,
            // polling watchers, …) without importing anything.
            notifyOperationStart: (type, opts) => {
                window.dispatchEvent(new CustomEvent('peregrine:operation-start', {
                    detail: { type, ...opts },
                }));
            },
            notifyOperationComplete: (type, opts) => {
                window.dispatchEvent(new CustomEvent('peregrine:operation-complete', {
                    detail: { type, ...opts },
                }));
            },
            // Unified save bar bridge. Delegates to the core save coordinator so
            // a plugin can register its dirty changes without importing the
            // store (and the store stays plugin-agnostic). Plugins feature-detect
            // these before use, so older shells simply lack them.
            registerSaveSource: (id, source) => {
                useSaveCoordinatorStore.getState().registerSource(id, source);
            },
            unregisterSaveSource: (id) => {
                useSaveCoordinatorStore.getState().unregisterSource(id);
            },
        };

        set({ isInitialized: true });
    },
}));
