import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';
import type { WebSocketCredentials } from '@/types/WebSocketCredentials';
import type { PowerSignal } from '@/types/PowerSignal';
import type { StartupVariable } from '@/types/StartupVariable';
import type { NodeStatusResponse } from '@/types/NodeStatus';
import type { StartupCommandData } from '@/types/StartupCommand';
import { request } from '@/services/http';

interface ServerResponse {
    data: Server;
    allocation?: { ip: string; port: number } | null;
    sftp_details?: { ip: string; port: number; username: string } | null;
    limits?: { memory: number; cpu: number; disk: number } | null;
    feature_limits?: { allocations: number; backups: number; databases: number } | null;
    role?: 'owner' | 'subuser';
    permissions?: string[] | null;
}

export async function fetchServer(id: number): Promise<Server> {
    const response = await request<ServerResponse>(`/api/servers/${id}`);
    const server = response.data;
    // Merge additional() data into the server object
    if (response.allocation) {
        server.allocation = response.allocation;
    }
    if (response.sftp_details) {
        server.sftp_details = response.sftp_details;
    }
    if (response.limits) {
        server.limits = response.limits;
    }
    if (response.feature_limits) {
        server.feature_limits = response.feature_limits;
    }
    server.role = response.role ?? 'owner';
    server.permissions = response.permissions ?? null;
    return server;
}

export async function fetchNodeStatus(id: number): Promise<NodeStatusResponse> {
    return request<NodeStatusResponse>(`/api/servers/${id}/node-status`);
}

export async function fetchStartupCommand(id: number): Promise<StartupCommandData | null> {
    const { data } = await request<{ data: StartupCommandData | null }>(`/api/servers/${id}/startup/command`);
    return data;
}

export async function updateStartupCommand(id: number, name: string): Promise<void> {
    await request(`/api/servers/${id}/startup/command`, {
        method: 'PUT',
        body: JSON.stringify({ name }),
    });
}

export async function fetchServerStats(): Promise<ServerStatsMap> {
    const { data } = await request<{ data: ServerStatsMap }>('/api/servers/stats');
    return data;
}

export async function sendPowerSignal(id: number, signal: PowerSignal): Promise<void> {
    await request(`/api/servers/${id}/power`, {
        method: 'POST',
        body: JSON.stringify({ signal }),
    });
}

export async function sendCommand(id: number, command: string): Promise<void> {
    await request(`/api/servers/${id}/command`, {
        method: 'POST',
        body: JSON.stringify({ command }),
    });
}

/**
 * Fetch a Wings JWT + WebSocket URL.
 *
 * Peregrine caches the response server-side for ~5 min so multiple
 * subscribers (multi-tab, React StrictMode, rapid reconnects) share a
 * single round-trip to Pelican Panel. When the existing token is
 * about to expire (Wings broadcasts a `token expiring` event ~60 s
 * before its `exp` claim fires), pass `force=true` to bypass the
 * Peregrine cache and pull a fresh signed JWT from Pelican — without
 * this flag, the renewal path could land on a near-expired cached
 * token and Wings would close the socket within seconds.
 */
export async function fetchWebSocketCredentials(
    id: number,
    options: { force?: boolean } = {},
): Promise<WebSocketCredentials> {
    const url = options.force === true
        ? `/api/servers/${id}/websocket?fresh=1`
        : `/api/servers/${id}/websocket`;
    const { data } = await request<{ data: WebSocketCredentials }>(url);
    return data;
}

export async function fetchStartupVariables(id: number): Promise<StartupVariable[]> {
    const { data } = await request<{ data: StartupVariable[] }>(`/api/servers/${id}/startup`);
    return data;
}

export async function updateStartupVariable(id: number, key: string, value: string): Promise<void> {
    await request(`/api/servers/${id}/startup/variable`, {
        method: 'PUT',
        body: JSON.stringify({ key, value }),
    });
}

export interface StartupVariableUpdate {
    key: string;
    value: string;
}

/** Per-key failures keyed by env variable name (partial-success model). */
export interface BatchStartupResult {
    success: boolean;
    updated: number;
    errors: Record<string, string>;
}

/**
 * Batch update — backs the unified save bar. Pelican has no bulk endpoint, so
 * the server applies them one by one and returns partial-success info: a 200
 * body with `success:false` + `errors` means some keys failed (the rest saved).
 */
export async function updateStartupVariables(
    id: number,
    variables: StartupVariableUpdate[],
): Promise<BatchStartupResult> {
    return request<BatchStartupResult>(`/api/servers/${id}/startup/variables`, {
        method: 'PUT',
        body: JSON.stringify({ variables }),
    });
}

export async function renameServer(id: number, name: string): Promise<Server> {
    const { data } = await request<{ data: Server }>(`/api/servers/${id}/rename`, {
        method: 'POST',
        body: JSON.stringify({ name }),
    });
    return data;
}

export async function reinstallServer(id: number, options: { wipeData?: boolean } = {}): Promise<void> {
    await request(`/api/servers/${id}/reinstall`, {
        method: 'POST',
        body: JSON.stringify({ wipe_data: !!options.wipeData }),
    });
}

// --- Minecraft console quick-fixes (EULA / Java version) -------------------

export interface DockerImageOption {
    label: string;
    image: string;
    java_major: number | null;
    is_current: boolean;
    is_recommended: boolean;
}

export interface DockerImagesResponse {
    current: string | null;
    images: DockerImageOption[];
}

export async function fetchServerDockerImages(
    id: number,
    requiredJava?: number | null,
): Promise<DockerImagesResponse> {
    const qs = requiredJava && requiredJava > 0 ? `?java=${requiredJava}` : '';
    const { data } = await request<{ data: DockerImagesResponse }>(`/api/servers/${id}/docker-images${qs}`);
    return data;
}

export async function applyServerDockerImage(id: number, image: string): Promise<void> {
    await request(`/api/servers/${id}/docker-image`, {
        method: 'POST',
        body: JSON.stringify({ image }),
    });
}

export async function acceptServerEula(id: number): Promise<void> {
    await request(`/api/servers/${id}/accept-eula`, { method: 'POST' });
}
