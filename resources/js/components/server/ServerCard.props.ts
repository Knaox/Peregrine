import type { Server } from '@/types/Server';
import type { ServerStats } from '@/types/ServerStats';
import type { PowerSignal } from '@/types/PowerSignal';

export interface ServerCardProps {
    server: Server;
    stats: ServerStats | undefined;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
    isSelectable?: boolean;
    isSelected?: boolean;
    onSelect?: (serverId: number) => void;
    isDragging?: boolean;
}
