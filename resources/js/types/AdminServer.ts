import type { Server } from '@/types/Server';
import type { NodeHealthSeverity, NodeHealthStatusValue } from '@/types/NodeStatus';

export interface ServerOwner {
    id: number;
    name: string;
    email: string;
}

export interface AdminServerNode {
    id: number;
    name: string;
    /** Cached Wings verdict — null until the deferred probe has run once. */
    health: {
        status: NodeHealthStatusValue;
        severity: NodeHealthSeverity;
        latency_ms: number | null;
        wings_version: string | null;
        checked_at: string | null;
    } | null;
}

/**
 * The server shape returned by GET /api/admin/servers. Owner is always
 * eager-loaded by AdminServersController; egg/plan are included when the
 * relations exist.
 */
export type AdminServer = Server & {
    owner: ServerOwner;
    node?: AdminServerNode | null;
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
