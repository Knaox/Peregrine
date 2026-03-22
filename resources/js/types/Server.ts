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
    plan: {
        id: number;
        name: string;
        ram?: number;
        cpu?: number;
        disk?: number;
    } | null;
    created_at: string;
}
