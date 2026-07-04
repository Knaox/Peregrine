import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchSchedules, createSchedule, updateSchedule, executeSchedule, deleteSchedule, createTask, updateTask, deleteTask, copySchedule } from '@/services/scheduleApi';

export function useSchedules(serverId: number) {
    const queryClient = useQueryClient();
    const queryKey = ['servers', serverId, 'schedules'];

    const list = useQuery({
        queryKey,
        queryFn: () => fetchSchedules(serverId),
        staleTime: 300_000,
        enabled: serverId > 0,
    });

    // Returning the invalidation promise from onSuccess makes mutateAsync wait
    // for the refetch to COMPLETE — callers that chain mutations (create a
    // schedule, then add its preset task) are guaranteed the list they leave
    // behind is the final, freshest one, not an intermediate snapshot.
    const invalidate = () => queryClient.invalidateQueries({ queryKey });

    const create = useMutation({
        mutationFn: (data: Record<string, unknown>) => createSchedule(serverId, data),
        onSuccess: invalidate,
    });

    const update = useMutation({
        mutationFn: (data: { scheduleId: number; payload: Record<string, unknown> }) =>
            updateSchedule(serverId, data.scheduleId, data.payload),
        onSuccess: invalidate,
    });

    const execute = useMutation({
        mutationFn: (scheduleId: number) => executeSchedule(serverId, scheduleId),
        // "Run now" touches last_run_at / is_processing — refresh those too.
        onSuccess: invalidate,
    });

    const remove = useMutation({
        mutationFn: (scheduleId: number) => deleteSchedule(serverId, scheduleId),
        onSuccess: invalidate,
    });

    const addTask = useMutation({
        mutationFn: (data: { scheduleId: number; payload: Record<string, unknown> }) =>
            createTask(serverId, data.scheduleId, data.payload),
        onSuccess: invalidate,
    });

    const editTask = useMutation({
        mutationFn: (data: { scheduleId: number; taskId: number; payload: Record<string, unknown> }) =>
            updateTask(serverId, data.scheduleId, data.taskId, data.payload),
        onSuccess: invalidate,
    });

    const removeTask = useMutation({
        mutationFn: (data: { scheduleId: number; taskId: number }) =>
            deleteTask(serverId, data.scheduleId, data.taskId),
        onSuccess: invalidate,
    });

    const copy = useMutation({
        mutationFn: (data: { scheduleId: number; targetServerIds: number[] }) =>
            copySchedule(serverId, data.scheduleId, data.targetServerIds),
        // The source list is untouched, but every TARGET server just gained a
        // schedule — drop their cached lists so visiting them shows the copy.
        onSuccess: (_result, variables) => {
            for (const targetId of variables.targetServerIds) {
                void queryClient.invalidateQueries({ queryKey: ['servers', targetId, 'schedules'] });
            }
        },
    });

    return { ...list, create, update, execute, remove, addTask, editTask, removeTask, copy };
}
