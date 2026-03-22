import type { Server } from '@/types/Server';
import type { ServerStatsMap } from '@/types/ServerStats';
import type { ServerResources } from '@/types/ServerResources';
import type { WebSocketCredentials } from '@/types/WebSocketCredentials';
import type { PowerSignal } from '@/types/PowerSignal';
import { request } from '@/services/http';

export async function fetchServer(id: number): Promise<Server> {
    const { data } = await request<{ data: Server }>(`/api/servers/${id}`);
    return data;
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

export async function fetchWebSocketCredentials(id: number): Promise<WebSocketCredentials> {
    const { data } = await request<{ data: WebSocketCredentials }>(`/api/servers/${id}/websocket`);
    return data;
}
