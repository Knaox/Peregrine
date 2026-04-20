import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchBranding } from '@/services/api';
import { useThemeModeStore } from '@/stores/themeModeStore';
import type { Branding } from '@/types/Branding';

declare global {
    interface Window {
        __BRANDING__?: Branding;
    }
}

const DEFAULT_BRANDING: Branding = {
    app_name: 'Peregrine',
    show_app_name: true,
    logo_height: 40,
    logo_url: '/images/logo.webp',
    logo_url_light: '/images/logo.webp',
    favicon_url: '/images/favicon.ico',
    header_links: [],
};

// Read server-injected branding immediately (no API wait)
const INITIAL_BRANDING: Branding = window.__BRANDING__ ?? DEFAULT_BRANDING;

export function useBranding() {
    const { data } = useQuery({
        queryKey: ['branding'],
        queryFn: fetchBranding,
        staleTime: 60 * 60 * 1000,
        // Use server-injected data as initial data — instant render
        initialData: INITIAL_BRANDING ? { data: INITIAL_BRANDING } : undefined,
    });
    const effectiveMode = useThemeModeStore((s) => s.effective);

    const raw = data?.data ?? INITIAL_BRANDING;

    // Swap the effective logo based on the active mode; fall back to the main
    // logo when the admin hasn't uploaded a light-mode variant.
    const effectiveLogo = effectiveMode === 'light' && raw.logo_url_light
        ? raw.logo_url_light
        : raw.logo_url;

    const branding: Branding = { ...raw, logo_url: effectiveLogo };

    useEffect(() => {
        const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
        if (link && branding.favicon_url) link.href = branding.favicon_url;
        if (branding.app_name) document.title = branding.app_name;
    }, [branding.favicon_url, branding.app_name]);

    return branding;
}
