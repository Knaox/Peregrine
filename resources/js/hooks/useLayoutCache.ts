import type { DashboardLayout } from '@/types/DashboardLayout';

const CACHE_KEY = 'peregrine.dashboard-layout.v1';
const TTL_MS = 7 * 24 * 60 * 60 * 1000;

/**
 * Read the last-saved layout from localStorage so a hard refresh renders
 * the categorised dashboard immediately with the user's last-known
 * arrangement, before /api/user/dashboard-layout resolves.
 */
export function readLayoutCache(): DashboardLayout | null {
    try {
        const raw = localStorage.getItem(CACHE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as { layout?: DashboardLayout; cachedAt?: number };
        if (parsed.cachedAt && Date.now() - parsed.cachedAt > TTL_MS) return null;
        if (!parsed.layout || !Array.isArray(parsed.layout.categories) || !Array.isArray(parsed.layout.uncategorizedOrder)) {
            return null;
        }
        return parsed.layout;
    } catch {
        return null;
    }
}

export function writeLayoutCache(layout: DashboardLayout | null): void {
    try {
        if (layout === null) {
            localStorage.removeItem(CACHE_KEY);
            return;
        }
        localStorage.setItem(CACHE_KEY, JSON.stringify({ layout, cachedAt: Date.now() }));
    } catch {
        // localStorage full / disabled — silently skip; cache is optional
    }
}
