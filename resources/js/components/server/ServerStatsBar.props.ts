import type { ServerStats } from '@/types/ServerStats';

export interface ServerStatsBarProps {
    stats: ServerStats | undefined;
    isLoading?: boolean;
}
