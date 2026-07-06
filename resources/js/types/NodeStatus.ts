export type NodeHealthStatusValue =
    | 'healthy'
    | 'degraded'
    | 'unreachable'
    | 'auth_failed'
    | 'maintenance'
    | 'server_unreachable'
    | 'server_errors'
    | 'unknown';

export type NodeHealthSeverity = 'ok' | 'warning' | 'critical';

export interface NodeStatusResponse {
    node: {
        name: string;
        location: string | null;
        maintenance: boolean;
    } | null;
    health: {
        status: NodeHealthStatusValue;
        severity: NodeHealthSeverity;
        latency_ms: number | null;
        wings_version: string | null;
        checked_at: string | null;
        /** Raw Wings error detail — only present for admins. */
        detail?: string | null;
    };
}
