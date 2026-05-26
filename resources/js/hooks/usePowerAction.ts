import { useMutation, useQueryClient } from '@tanstack/react-query';
import { sendPowerSignal } from '@/services/serverApi';
import { usePowerTransitionStore } from '@/stores/powerTransitionStore';
import type { PowerSignal } from '@/types/PowerSignal';

export function usePowerAction() {
    const queryClient = useQueryClient();

    const mutation = useMutation({
        mutationFn: ({ serverId, signal }: { serverId: number; signal: PowerSignal }) =>
            sendPowerSignal(serverId, signal),
        onMutate: ({ serverId, signal }: { serverId: number; signal: PowerSignal }) => {
            // Optimistic transitional state ("starting…/stopping…") so the
            // dashboard card reacts instantly, before the next 10s stats poll.
            usePowerTransitionStore.getState().setFromSignal(serverId, signal);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['servers', 'stats'] });
        },
    });

    return {
        sendPower: mutation.mutate,
        isPending: mutation.isPending,
    };
}
