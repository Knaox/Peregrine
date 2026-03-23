import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchBranding } from '@/services/api';
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

    const branding = data?.data ?? INITIAL_BRANDING;

    useEffect(() => {
        const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
        if (link && branding.favicon_url) link.href = branding.favicon_url;
        if (branding.app_name) document.title = branding.app_name;
    }, [branding.favicon_url, branding.app_name]);

    return branding;
}
