import type { Server } from '@/types/Server';

export interface ServerOwner {
    id: number;
    name: string;
    email: string;
}

/**
 * The server shape returned by GET /api/admin/servers. Owner is always
 * eager-loaded by AdminServersController; egg/plan are included when the
 * relations exist.
 */
export type AdminServer = Server & {
    owner: ServerOwner;
};

export interface AdminServersResponse {
    data: AdminServer[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links?: {
        first?: string;
        last?: string;
        prev?: string | null;
        next?: string | null;
    };
}

export interface AdminServersFilters {
    search?: string;
    status?: string;
    user_id?: number;
    per_page?: number;
    page?: number;
}
