import { request } from '@/services/http';
import type { AdminServersFilters, AdminServersResponse } from '@/types/AdminServer';

export async function fetchAdminServers(filters: AdminServersFilters): Promise<AdminServersResponse> {
    const params = new URLSearchParams();

    if (filters.search !== undefined && filters.search !== '') {
        params.set('search', filters.search);
    }
    if (filters.status !== undefined && filters.status !== '') {
        params.set('status', filters.status);
    }
    if (filters.user_id !== undefined) {
        params.set('user_id', String(filters.user_id));
    }
    if (filters.per_page !== undefined) {
        params.set('per_page', String(filters.per_page));
    }
    if (filters.page !== undefined) {
        params.set('page', String(filters.page));
    }

    const qs = params.toString();
    const url = qs === '' ? '/api/admin/servers' : `/api/admin/servers?${qs}`;

    return request(url);
}
