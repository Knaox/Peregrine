import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { usePluginStore } from '@/plugins/pluginStore';
import type { PluginApiResponse } from '@/plugins/types';
import { request } from '@/services/http';

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

        // Load all plugin bundles
        const bundles = manifests
            .filter((m) => m.bundle_url)
            .map((m) => loadScript(m.bundle_url as string));

        Promise.allSettled(bundles).then(() => {
            setLoading(false);
        });
    }, [data, setManifests, setLoading]);

    // This component renders nothing — it only loads plugins
    return null;
}
