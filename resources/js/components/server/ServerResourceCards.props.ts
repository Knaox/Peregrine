import type { ServerResources } from '@/types/ServerResources';

export interface ServerResourceCardsProps {
    resources: ServerResources | undefined;
    plan: { ram?: number; cpu?: number; disk?: number } | null;
    isLoading: boolean;
    /** Only roll the values up when the server is actually running. */
    live?: boolean;
}
