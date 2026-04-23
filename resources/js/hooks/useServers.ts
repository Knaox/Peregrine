import { useQuery } from '@tanstack/react-query';
import { fetchServers } from '@/services/api';
import type { Server } from '@/types/Server';

const CACHE_KEY = 'peregrine.servers.v1';

interface ServersResponse {
    data: Server[];
}

/**
 * Read the last-known server list from localStorage. Used as `initialData`
 * so the dashboard renders cards instantly on refresh, before /api/servers
 * has resolved. The query then revalidates in the background.
 */
function readCache(): ServersResponse | undefined {
    try {
        const raw = localStorage.getItem(CACHE_KEY);
        if (!raw) return undefined;
        const parsed = JSON.parse(raw) as { data?: Server[]; cachedAt?: number };
        // 24h hard expiry — past that, the data is too stale to render
        // without confusing the user (a card for a deleted server, etc.).
        if (parsed.cachedAt && Date.now() - parsed.cachedAt > 24 * 60 * 60 * 1000) return undefined;
        if (!Array.isArray(parsed.data)) return undefined;
        return { data: parsed.data };
    } catch {
        return undefined;
    }
}

function writeCache(response: ServersResponse): void {
    try {
        localStorage.setItem(CACHE_KEY, JSON.stringify({ data: response.data, cachedAt: Date.now() }));
    } catch {
        // localStorage full / disabled — silently skip; cache is optional
    }
}

export function useServers() {
    return useQuery({
        queryKey: ['servers'],
        queryFn: async () => {
            const response = await fetchServers();
            writeCache(response);
            return response;
        },
        staleTime: 60_000, // 1 minute
        // Bootstrap from localStorage so refresh feels instant. Marked stale
        // so React Query revalidates in the background on the next tick.
        initialData: readCache,
        initialDataUpdatedAt: 0,
    });
}
