import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, BASE } from '../../shared';
import type { EggOption, TemplateRow } from '../../types';

const LIST_KEY = ['ec-admin-templates'];

interface TemplateDetail {
    id: string;
    raw: string;
    valid: boolean;
    definition: Record<string, unknown> | null;
}

export function useTemplateList() {
    return useQuery({
        queryKey: LIST_KEY,
        queryFn: () => api<{ data: TemplateRow[] }>(`${BASE}/admin/templates`).then((response) => response.data),
    });
}

export function useTemplateDetail(id: string | null) {
    return useQuery({
        queryKey: ['ec-admin-template', id],
        enabled: id !== null,
        queryFn: () => api<{ data: TemplateDetail }>(`${BASE}/admin/templates/${id ?? ''}`).then((response) => response.data),
    });
}

export function useEggCatalog() {
    return useQuery({
        queryKey: ['ec-admin-eggs'],
        staleTime: 5 * 60_000,
        queryFn: () => api<{ data: EggOption[] }>(`${BASE}/admin/eggs`).then((response) => response.data),
    });
}

export function useSaveTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, template }: { id: string | null; template: Record<string, unknown> }) =>
            id !== null
                ? api(`${BASE}/admin/templates/${id}`, { method: 'PUT', body: JSON.stringify({ template }) })
                : api(`${BASE}/admin/templates`, { method: 'POST', body: JSON.stringify({ template }) }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: LIST_KEY }),
    });
}

export function useImportTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (content: string) => api(`${BASE}/admin/templates/import`, { method: 'POST', body: JSON.stringify({ content }) }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: LIST_KEY }),
    });
}

export function useDeleteTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => api(`${BASE}/admin/templates/${id}`, { method: 'DELETE' }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: LIST_KEY }),
    });
}
