import type { DatabaseConfig, PelicanConfig, SetupState } from '../types';

const API_BASE = '/api/setup';

function getCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

async function apiCall<T>(endpoint: string, body: unknown): Promise<T> {
    const response = await fetch(`${API_BASE}${endpoint}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = await response.json() as T;
    return data;
}

export async function testDatabase(config: DatabaseConfig): Promise<{ success: boolean; error?: string }> {
    return apiCall('/test-database', config);
}

export async function testPelican(config: PelicanConfig): Promise<{ success: boolean; error?: string }> {
    return apiCall('/test-pelican', config);
}

export async function install(state: SetupState): Promise<{ success: boolean; error?: string }> {
    return apiCall('/install', {
        database: {
            host: state.database.host,
            port: state.database.port,
            name: state.database.database,
            username: state.database.username,
            password: state.database.password,
        },
        admin: state.admin,
        pelican: {
            url: state.pelican.url,
            api_key: state.pelican.api_key,
            client_api_key: state.pelican.client_api_key,
        },
        auth: state.auth,
        locale: state.locale,
    });
}

export async function detectDocker(): Promise<{ is_docker: boolean; db_ready: boolean; defaults: Partial<DatabaseConfig> }> {
    const response = await fetch(`${API_BASE}/docker-detect`, {
        headers: { 'Accept': 'application/json' },
    });
    return response.json() as Promise<{ is_docker: boolean; db_ready: boolean; defaults: Partial<DatabaseConfig> }>;
}

export interface BackfillResource {
    processed: number;
    total: number;
    completed: boolean;
    started_at: string | null;
    completed_at: string | null;
    last_error: string | null;
}

export interface BackfillStatus {
    resources: Record<string, BackfillResource>;
    all_completed: boolean;
}

export async function startBackfill(): Promise<{ started: boolean }> {
    return apiCall('/backfill/start', {});
}

export async function getBackfillStatus(): Promise<BackfillStatus> {
    const response = await fetch(`${API_BASE}/backfill/status`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
    });
    return await response.json() as BackfillStatus;
}

export async function generateWebhookToken(): Promise<{ token: string; endpoint: string }> {
    return apiCall('/webhook/generate-token', {});
}

export async function getWebhookHeartbeat(): Promise<{ enabled: boolean; token_configured: boolean }> {
    const response = await fetch(`${API_BASE}/webhook/heartbeat`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
    });
    return await response.json() as { enabled: boolean; token_configured: boolean };
}
