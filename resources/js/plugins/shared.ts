import React from 'react';
import ReactDOM from 'react-dom/client';
import * as ReactQuery from '@tanstack/react-query';
import * as ReactRouterDom from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import i18n from '@/i18n/config';

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
        };
        __PEREGRINE_PLUGINS__: {
            register: (pluginId: string, component: React.ComponentType) => void;
            registerServerPage: (pageId: string, component: React.ComponentType) => void;
            registerServerHomeSection: (sectionId: string, component: React.ComponentType<{ serverId: number }>) => void;
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
};

// Plugin registration bridge — filled by pluginStore
window.__PEREGRINE_PLUGINS__ = {
    register: () => {},
    registerServerPage: () => {},
    registerServerHomeSection: () => {},
};
