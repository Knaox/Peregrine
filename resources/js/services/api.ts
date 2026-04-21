import type { User } from '@/types/User';
import type { Server } from '@/types/Server';
import type { Branding } from '@/types/Branding';
import type { LoginResponse } from '@/types/TwoFactor';
import { request, ApiError } from '@/services/http';

export { ApiError };

export async function login(email: string, password: string, remember: boolean = false): Promise<LoginResponse> {
    return request('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password, remember }),
    });
}

export async function register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    locale?: string;
}): Promise<{ user: User }> {
    return request('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function logout(): Promise<void> {
    await request('/api/auth/logout', { method: 'POST' });
}

export async function fetchCurrentUser(): Promise<{ user: User | null }> {
    return request('/api/auth/user');
}

export async function fetchBranding(): Promise<{ data: Branding }> {
    return request('/api/settings/branding');
}

export async function fetchServers(): Promise<{ data: Server[] }> {
    return request('/api/servers');
}
