export interface Server {
    id: number;
    name: string;
    status: 'active' | 'suspended' | 'terminated' | 'running' | 'stopped' | 'offline';
    pelican_server_id: number | null;
    egg: {
        id: number;
        name: string;
        banner_image?: string | null;
    } | null;
    allocation?: {
        ip: string;
        port: number;
    } | null;
    sftp_details?: {
        ip: string;
        port: number;
        username: string;
    } | null;
    plan: {
        id: number;
        name: string;
        ram?: number;
        cpu?: number;
        disk?: number;
    } | null;
    /** Server resource limits from Pelican API (fallback when plan is null) */
    limits?: {
        memory: number;
        cpu: number;
        disk: number;
    } | null;
    created_at: string;
}
