import { create } from 'zustand';
import type { PluginManifest } from '@/plugins/types';

interface PluginStore {
    manifests: PluginManifest[];
    components: Record<string, React.ComponentType>;
    serverPageComponents: Record<string, React.ComponentType>;
    isLoading: boolean;
    isInitialized: boolean;

    setManifests: (manifests: PluginManifest[]) => void;
    setLoading: (loading: boolean) => void;
    registerComponent: (pluginId: string, component: React.ComponentType) => void;
    registerServerPage: (pageId: string, component: React.ComponentType) => void;
    getComponent: (pluginId: string) => React.ComponentType | undefined;
    init: () => void;
}

export const usePluginStore = create<PluginStore>((set, get) => ({
    manifests: [],
    components: {},
    serverPageComponents: {},
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
        };

        set({ isInitialized: true });
    },
}));
