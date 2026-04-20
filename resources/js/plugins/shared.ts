import React from 'react';
import ReactDOM from 'react-dom/client';
import * as ReactQuery from '@tanstack/react-query';
import * as ReactRouterDom from 'react-router-dom';
import { useTranslation } from 'react-i18next';

declare global {
    interface Window {
        __PEREGRINE_SHARED__: {
            React: typeof React;
            ReactDOM: typeof ReactDOM;
            ReactQuery: typeof ReactQuery;
            ReactRouterDom: typeof ReactRouterDom;
            useTranslation: typeof useTranslation;
        };
        __PEREGRINE_PLUGINS__: {
            register: (pluginId: string, component: React.ComponentType) => void;
            registerServerPage: (pageId: string, component: React.ComponentType) => void;
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
};

// Plugin registration bridge — filled by pluginStore
window.__PEREGRINE_PLUGINS__ = {
    register: () => {},
    registerServerPage: () => {},
};
