import React from 'react';
import ReactDOM from 'react-dom/client';
import * as ReactQuery from '@tanstack/react-query';
import * as ReactRouterDom from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import i18n from '@/i18n/config';
import { getEcho } from '@/services/echo';
import type { SaveSource } from '@/stores/saveCoordinatorStore';

declare global {
    interface Window {
        __PEREGRINE_SHARED__: {
            React: typeof React;
            ReactDOM: typeof ReactDOM;
            ReactQuery: typeof ReactQuery;
            ReactRouterDom: typeof ReactRouterDom;
            useTranslation: typeof useTranslation;
            // i18next instance — exposed so plugin bundles can call
            // `getResource()` on the namespaced bundles they shipped
            // (used to read structured dictionaries like
            // `params.<key>.{label,type,...}` rather than just simple
            // string translations).
            i18n: typeof i18n;
            // Lazy Echo singleton accessor. Exposed so plugin bundles
            // can subscribe their own queries to `private-server.{id}` /
            // `private-user.{id}` / `private-admin-mirror` for live
            // updates instead of falling back to TanStack Query polling.
            // Returns null when Reverb is unavailable (admin hasn't set
            // it up, meta tags empty) — plugins must degrade gracefully.
            getEcho: typeof getEcho;
        };
        __PEREGRINE_PLUGINS__: {
            register: (pluginId: string, component: React.ComponentType) => void;
            registerServerPage: (pageId: string, component: React.ComponentType) => void;
            registerServerHomeSection: (sectionId: string, component: React.ComponentType<{ serverId: number }>) => void;
            /**
             * Notify the shell that a long-running, plugin-managed operation
             * has just started on the given server. The shell currently uses
             * this to suppress its own server-status-driven redirects until
             * the matching `notifyOperationComplete` (or a 2s cooldown) clears.
             */
            notifyOperationStart: (
                type: 'modpack' | 'modpack_uninstall' | string,
                opts: { serverId: number; name?: string | null }
            ) => void;
            /**
             * Notify the shell that the operation just finished. The shell
             * redirects the user to the server overview and renders a one-shot
             * success Alert with the operation type + optional name.
             */
            notifyOperationComplete: (
                type: 'modpack' | 'modpack_uninstall' | string,
                opts: { serverId: number; name?: string | null }
            ) => void;
            /**
             * Register a "save source" with the unified save bar so a plugin's
             * dirty changes are flushed together with the core's in a single
             * click. Optional on purpose: a plugin must feature-detect it
             * (`typeof registerSaveSource === 'function'`) and fall back to its
             * own save UI when running on an older shell. Core never imports the
             * plugin; the plugin never imports the store — this bridge is the
             * only contract.
             */
            registerSaveSource: (id: string, source: SaveSource) => void;
            unregisterSaveSource: (id: string) => void;
        };
    }
}

// Expose shared dependencies for plugin bundles
window.__PEREGRINE_SHARED__ = {
    React,
    ReactDOM,
    ReactQuery,
    ReactRouterDom,
    useTranslation,
    i18n,
    getEcho,
};

// Plugin registration bridge — filled by pluginStore
window.__PEREGRINE_PLUGINS__ = {
    register: () => {},
    registerServerPage: () => {},
    registerServerHomeSection: () => {},
    notifyOperationStart: () => {},
    notifyOperationComplete: () => {},
    registerSaveSource: () => {},
    unregisterSaveSource: () => {},
};
