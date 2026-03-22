import { useMutation, useQueryClient } from '@tanstack/react-query';
import { sendPowerSignal } from '@/services/serverApi';
import type { PowerSignal } from '@/types/PowerSignal';

export function usePowerAction() {
    const queryClient = useQueryClient();

    const mutation = useMutation({
        mutationFn: ({ serverId, signal }: { serverId: number; signal: PowerSignal }) =>
            sendPowerSignal(serverId, signal),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['servers', 'stats'] });
        },
    });

    return {
        sendPower: mutation.mutate,
        isPending: mutation.isPending,
    };
}
