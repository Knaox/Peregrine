import type { User } from '@/types/User';
import { request } from '@/services/http';

export async function fetchProfile(): Promise<User> {
    const { data } = await request<{ data: User }>('/api/user/profile');
    return data;
}

export async function updateProfile(data: { name?: string; locale?: string }): Promise<User> {
    const response = await request<{ data: User }>('/api/user/profile', {
        method: 'PUT',
        body: JSON.stringify(data),
    });
    return response.data;
}

export async function changePassword(data: {
    current_password: string;
    password: string;
    password_confirmation: string;
}): Promise<void> {
    await request('/api/user/change-password', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

export async function setSftpPassword(data: {
    password: string;
    password_confirmation: string;
}): Promise<void> {
    await request('/api/user/sftp-password', {
        method: 'POST',
        body: JSON.stringify(data),
    });
}
