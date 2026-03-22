import type { PowerSignal } from '@/types/PowerSignal';

export interface ServerBulkBarProps {
    selectedCount: number;
    onBulkPower: (signal: PowerSignal) => void;
    onDeselectAll: () => void;
    isPending: boolean;
}
