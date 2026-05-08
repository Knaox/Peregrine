import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';
import type { ServerResources } from '@/types/ServerResources';
import type { WebSocketCredentials } from '@/types/WebSocketCredentials';
import type { PowerSignal } from '@/types/PowerSignal';
import type { StartupVariable } from '@/types/StartupVariable';
import { request } from '@/services/http';

interface ServerResponse {
    data: Server;
    allocation?: { ip: string; port: number } | null;
    sftp_details?: { ip: string; port: number; username: string } | null;
    limits?: { memory: number; cpu: number; disk: number } | null;
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
    server.role = response.role ?? 'owner';
    server.permissions = response.permissions ?? null;
    return server;
}

export async function fetchServerStats(): Promise<ServerStatsMap> {
    const { data } = await request<{ data: ServerStatsMap }>('/api/servers/stats');
    return data;
}

export async function fetchServerResources(id: number): Promise<ServerResources> {
    const { data } = await request<{ data: ServerResources }>(`/api/servers/${id}/resources`);
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
