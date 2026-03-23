import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchSchedules, createSchedule, updateSchedule, executeSchedule, deleteSchedule, createTask, deleteTask } from '@/services/scheduleApi';

export function useSchedules(serverId: number) {
    const queryClient = useQueryClient();
    const queryKey = ['servers', serverId, 'schedules'];

    const list = useQuery({
        queryKey,
        queryFn: () => fetchSchedules(serverId),
        staleTime: 300_000,
        enabled: serverId > 0,
    });

    const create = useMutation({
        mutationFn: (data: Record<string, unknown>) => createSchedule(serverId, data),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const update = useMutation({
        mutationFn: (data: { scheduleId: number; payload: Record<string, unknown> }) =>
            updateSchedule(serverId, data.scheduleId, data.payload),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const execute = useMutation({
        mutationFn: (scheduleId: number) => executeSchedule(serverId, scheduleId),
    });

    const remove = useMutation({
        mutationFn: (scheduleId: number) => deleteSchedule(serverId, scheduleId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const addTask = useMutation({
        mutationFn: (data: { scheduleId: number; payload: Record<string, unknown> }) =>
            createTask(serverId, data.scheduleId, data.payload),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    const removeTask = useMutation({
        mutationFn: (data: { scheduleId: number; taskId: number }) =>
            deleteTask(serverId, data.scheduleId, data.taskId),
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey }); },
    });

    return { ...list, create, update, execute, remove, addTask, removeTask };
}
