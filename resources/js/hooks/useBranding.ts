import { useQuery } from '@tanstack/react-query';
import { fetchBranding } from '@/services/api';
import type { Branding } from '@/types/Branding';

const DEFAULT_BRANDING: Branding = {
    app_name: 'Peregrine',
    logo_url: '/images/logo.svg',
    favicon_url: '/images/favicon.svg',
};

export function useBranding() {
    const { data } = useQuery({
        queryKey: ['branding'],
        queryFn: fetchBranding,
        staleTime: 60 * 60 * 1000, // 1 hour
    });

    return data?.data ?? DEFAULT_BRANDING;
}
