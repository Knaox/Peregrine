export interface Server {
    id: number;
    identifier: string;
    name: string;
    status: 'active' | 'suspended' | 'terminated' | 'running' | 'stopped' | 'offline' | 'provisioning' | 'provisioning_failed';
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
    /** User's role on this server: owner has all permissions, subuser has limited */
    role?: 'owner' | 'subuser';
    /** Subuser permissions array — null means owner (all permissions) */
    permissions?: string[] | null;
}
