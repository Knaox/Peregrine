import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, BASE } from '../../shared';
import type { EggOption, ServerOption, TemplateRow } from '../../types';

/** Result of importing a config file from a server: a scaffolded template file block. */
export interface ImportedFile {
    file: Record<string, unknown>;
    parameter_count: number;
}

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

/**
 * The bundled reference template (`samples/example-template.json`), fetched only
 * when the admin opens the "example" route. Used to seed the editor with a
 * complete, schema-valid starting point.
 */
export function useExampleTemplate(enabled: boolean) {
    return useQuery({
        queryKey: ['ec-admin-example-template'],
        enabled,
        staleTime: Infinity,
        queryFn: () => api<{ data: { definition: Record<string, unknown> } }>(`${BASE}/admin/templates/example`).then((response) => response.data.definition),
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
        // Invalidate BOTH the list and every detail query: without the detail
        // invalidation, reopening a just-saved template re-hydrates the form from
        // the stale cached definition and a re-save clobbers the new edits.
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: LIST_KEY });
            void queryClient.invalidateQueries({ queryKey: ['ec-admin-template'] });
        },
    });
}

export function useServerCatalog() {
    return useQuery({
        queryKey: ['ec-admin-servers'],
        staleTime: 60_000,
        queryFn: () => api<{ data: ServerOption[] }>(`${BASE}/admin/servers`).then((response) => response.data),
    });
}

export function useImportConfig() {
    return useMutation({
        mutationFn: (input: { server_id: number; path: string; format?: string }) =>
            api<{ data: ImportedFile }>(`${BASE}/admin/import-config`, {
                method: 'POST',
                body: JSON.stringify(input),
            }).then((response) => response.data),
    });
}

export function useImportTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (content: string) => api(`${BASE}/admin/templates/import`, { method: 'POST', body: JSON.stringify({ content }) }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: LIST_KEY }),
    });
}

/** Result of the official-catalog import: ids newly written vs. left untouched. */
export interface OfficialImportResult {
    imported: string[];
    skipped: string[];
}

/**
 * One-click import of the 9 bundled official templates. Egg-agnostic on import;
 * existing templates of the same id are skipped, never overwritten.
 */
/** Result of pushing a template's bundled egg into Pelican. */
export interface EggImportResult {
    updated: boolean;
    pelican_egg_id: number | null;
    attached_egg_id: number | null;
    /** Existing servers of this egg whose startup command was resynced. */
    startup_synced: number;
    startup_skipped: number;
    startup_failed: number;
}

/**
 * Push the egg bundled with a template into Pelican. Pelican upserts by the
 * egg's uuid, so a re-import updates the already-imported egg in place. On
 * success the backend also attaches the local egg to the template's
 * target_eggs — invalidate both list and detail so the badge counts refresh.
 */
export function useImportTemplateEgg() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) =>
            api<{ data: EggImportResult }>(`${BASE}/admin/templates/${id}/egg/import`, { method: 'POST' }).then((response) => response.data),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: LIST_KEY });
            void queryClient.invalidateQueries({ queryKey: ['ec-admin-template'] });
        },
    });
}

export function useImportOfficialTemplates() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () =>
            api<{ data: OfficialImportResult }>(`${BASE}/admin/templates/import-official`, { method: 'POST' }).then((response) => response.data),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: LIST_KEY });
            void queryClient.invalidateQueries({ queryKey: ['ec-admin-template'] });
        },
    });
}

export function useDeleteTemplate() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: string) => api(`${BASE}/admin/templates/${id}`, { method: 'DELETE' }),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: LIST_KEY }),
    });
}

/** Payload to annotate one discovered parameter into a template file. */
export interface AnnotateParameterInput {
    file_id: string;
    section: string | null;
    key: string;
    display_type: string;
    label?: Record<string, string> | null;
    description?: Record<string, string> | null;
    config?: Record<string, unknown> | null;
    env_var?: string | null;
}

/**
 * Promote a discovered (inferred) parameter into the template so it gains a
 * curated label/description/type for every server of the egg. Invalidates the
 * server config so the field re-renders documented (no longer inferred).
 */
export function useAnnotateTemplateParameter(serverId: number) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ templateId, param }: { templateId: string; param: AnnotateParameterInput }) =>
            api(`${BASE}/admin/templates/${templateId}/parameters`, { method: 'POST', body: JSON.stringify(param) }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['ec-config', serverId] });
            void queryClient.invalidateQueries({ queryKey: LIST_KEY });
        },
    });
}
