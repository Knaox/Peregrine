import type { PowerSignal } from '@/types/PowerSignal';

export interface ServerQuickActionsProps {
    serverId: number;
    state: string | undefined;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPending: boolean;
}
