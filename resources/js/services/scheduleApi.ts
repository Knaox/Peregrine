import type { Schedule } from '@/types/Schedule';
import { request } from '@/services/http';

export async function fetchSchedules(serverId: number): Promise<Schedule[]> {
    const { data } = await request<{ data: Schedule[] }>(`/api/servers/${serverId}/schedules`);
    return data;
}

export async function createSchedule(serverId: number, payload: Record<string, unknown>): Promise<Schedule> {
    const { data } = await request<{ data: Schedule }>(`/api/servers/${serverId}/schedules`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    return data;
}

export async function updateSchedule(serverId: number, scheduleId: number, payload: Record<string, unknown>): Promise<Schedule> {
    const { data } = await request<{ data: Schedule }>(`/api/servers/${serverId}/schedules/${scheduleId}`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
    return data;
}

export async function executeSchedule(serverId: number, scheduleId: number): Promise<void> {
    await request(`/api/servers/${serverId}/schedules/${scheduleId}/execute`, { method: 'POST' });
}

export async function deleteSchedule(serverId: number, scheduleId: number): Promise<void> {
    await request(`/api/servers/${serverId}/schedules/${scheduleId}`, { method: 'DELETE' });
}

export async function createTask(serverId: number, scheduleId: number, payload: Record<string, unknown>): Promise<void> {
    await request(`/api/servers/${serverId}/schedules/${scheduleId}/tasks`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export async function deleteTask(serverId: number, scheduleId: number, taskId: number): Promise<void> {
    await request(`/api/servers/${serverId}/schedules/${scheduleId}/tasks/${taskId}`, { method: 'DELETE' });
}
