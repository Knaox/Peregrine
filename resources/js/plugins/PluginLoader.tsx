import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { usePluginStore } from '@/plugins/pluginStore';
import type { PluginApiResponse } from '@/plugins/types';
import { request } from '@/services/http';
import { loadPluginI18n } from '@/i18n/config';

function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        // Skip if already loaded
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load plugin: ${src}`));
        document.head.appendChild(script);
    });
}

export function PluginLoader() {
    const { init, setManifests, setLoading } = usePluginStore();

    // Initialize the global registration bridge
    useEffect(() => {
        init();
    }, [init]);

    const { data } = useQuery({
        queryKey: ['plugins'],
        queryFn: () => request<PluginApiResponse>('/api/plugins'),
        staleTime: 60 * 60 * 1000, // 1 hour
        retry: 1,
    });

    useEffect(() => {
        if (!data?.data) return;

        const manifests = data.data;
        setManifests(manifests);

        // Plugin i18n bundles must be in i18next BEFORE the plugin script
        // executes — plugins call useTranslation() at first render and would
        // briefly show raw keys otherwise. We await both groups in parallel
        // (they're independent) but only flip isLoading after both finish.
        // Pass the manifest version as a cache-bust so brand-new dict
        // entries land immediately after a plugin update instead of waiting
        // out the 1-hour HTTP cache.
        const i18nLoads = manifests.map((m) => loadPluginI18n(m.id, m.version));
        const bundleLoads = manifests
            .filter((m) => m.bundle_url)
            .map((m) => loadScript(m.bundle_url as string));

        Promise.allSettled([...i18nLoads, ...bundleLoads]).then(() => {
            setLoading(false);
        });
    }, [data, setManifests, setLoading]);

    // This component renders nothing — it only loads plugins
    return null;
}
