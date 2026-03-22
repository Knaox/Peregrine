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
        pelican: state.pelican,
        auth: state.auth,
        bridge: state.bridge,
        locale: state.locale,
    });
}

export async function detectDocker(): Promise<{ is_docker: boolean; defaults: Partial<DatabaseConfig> }> {
    const response = await fetch(`${API_BASE}/docker-detect`, {
        headers: { 'Accept': 'application/json' },
    });
    return response.json() as Promise<{ is_docker: boolean; defaults: Partial<DatabaseConfig> }>;
}
