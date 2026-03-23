export interface ScheduleTask {
    id: number;
    sequence_id: number;
    action: 'command' | 'power' | 'backup';
    payload: string;
    time_offset: number;
    is_queued: boolean;
}

export interface Schedule {
    id: number;
    name: string;
    minute: string;
    hour: string;
    day_of_month: string;
    month: string;
    day_of_week: string;
    is_active: boolean;
    is_processing: boolean;
    only_when_online: boolean;
    last_run_at: string | null;
    next_run_at: string | null;
    tasks: ScheduleTask[];
}
