import type { ServerResources } from '@/types/ServerResources';

export interface ServerResourceCardsProps {
    resources: ServerResources | undefined;
    plan: { ram?: number; cpu?: number; disk?: number } | null;
    isLoading: boolean;
}
