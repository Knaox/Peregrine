import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { request, ApiError } from '@/services/http';
import { fetchServers } from '@/services/api';
import { useThemeStudioPreview } from '@/hooks/useThemeStudioPreview';
import { buildExportPayload, type ThemeExport, type ParsedImport } from '@/lib/themeStudio/themeTransfer';
import type {
    PreviewBreakpoint,
    PreviewMode,
    PreviewScene,
    ThemeDraft,
} from '@/types/themeStudio.types';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

interface ThemeStudioStateResponse {
    draft: ThemeDraft;
    card_config: CardConfig;
    sidebar_config: SidebarConfig;
    revision?: number;
}

interface SaveResponse {
    revision?: number;
}

interface PresetEntry {
    label: string;
    dark: Record<string, string>;
    light: Record<string, string>;
}

interface PresetsResponse {
    presets: Record<string, PresetEntry>;
}

interface UseThemeStudioReturn {
    draft: ThemeDraft | null;
    cardDraft: CardConfig | null;
    sidebarDraft: SidebarConfig | null;
    /** First user-owned server id (stringified) — gates the server scenes. */
    sampleServerId: string | null;
    isLoading: boolean;
    isDirty: boolean;
    isSaving: boolean;
    saveError: string | null;
    /** True if the initial /state load failed. The page should render an error fallback. */
    isError: boolean;
    /** Last load error message (or null when there was no error). */
    loadError: string | null;
    /** Re-fires the /state query — used by the error fallback "Retry" button. */
    refetch: () => void;
    scene: PreviewScene;
    previewMode: PreviewMode;
    breakpoint: PreviewBreakpoint;
    iframeRef: React.RefObject<HTMLIFrameElement | null>;
    setScene: (s: PreviewScene) => void;
    setPreviewMode: (m: PreviewMode) => void;
    setBreakpoint: (b: PreviewBreakpoint) => void;
    setField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
    setCardField: <K extends keyof CardConfig>(key: K, value: CardConfig[K]) => void;
    setSidebarField: <K extends keyof SidebarConfig>(key: K, value: SidebarConfig[K]) => void;
    save: () => Promise<void>;
    reset: () => Promise<void>;
    discard: () => void;
    /** Folds a parsed import into the draft — leaves it dirty for Publish. */
    loadFromExport: (parsed: ParsedImport) => void;
    /** Serialisable snapshot of the current draft (CLI-compatible shape). */
    exportPayload: () => ThemeExport | null;
}

export function useThemeStudio(): UseThemeStudioReturn {
    const queryClient = useQueryClient();
    const { data, isLoading, isError, error, refetch } = useQuery({
        queryKey: ['admin', 'theme', 'state'],
        queryFn: () => request<ThemeStudioStateResponse>('/api/admin/theme/state'),
        staleTime: Infinity,
        retry: 1,
    });
    // Presets feed the inverse-mode variant when the admin toggles the
    // preview mode toggle in the toolbar. Same query key as ThemePresetSelector
    // so TanStack dedupes the underlying request.
    const { data: presetsData } = useQuery({
        queryKey: ['admin', 'theme', 'presets'],
        queryFn: () => request<PresetsResponse>('/api/admin/theme/presets'),
        staleTime: Infinity,
    });
    // Sample server gates the in-server scenes (Overview/Console/Files/
    // Databases). The studio picks the first server the admin owns; if none
    // exist the toolbar disables those scene buttons rather than shipping
    // a broken `:id` URL into the iframe.
    const { data: serversData } = useQuery({
        queryKey: ['admin', 'theme', 'sample-server'],
        queryFn: () => fetchServers(),
        staleTime: 60 * 1000,
    });
    const sampleServerId = useMemo<string | null>(() => {
        const first = serversData?.data?.[0];
        return first ? String(first.id) : null;
    }, [serversData]);

    const [draft, setDraft] = useState<ThemeDraft | null>(null);
    const [baseline, setBaseline] = useState<ThemeDraft | null>(null);
    const [cardDraft, setCardDraft] = useState<CardConfig | null>(null);
    const [cardBaseline, setCardBaseline] = useState<CardConfig | null>(null);
    const [sidebarDraft, setSidebarDraft] = useState<SidebarConfig | null>(null);
    const [sidebarBaseline, setSidebarBaseline] = useState<SidebarConfig | null>(null);
    const [scene, setScene] = useState<PreviewScene>('dashboard');
    const [previewMode, setPreviewMode] = useState<PreviewMode>('dark');
    const [breakpoint, setBreakpoint] = useState<PreviewBreakpoint>('desktop');
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [revision, setRevision] = useState<number | null>(null);
    const iframeRef = useRef<HTMLIFrameElement | null>(null);

    useEffect(() => {
        if (!data) return;
        setDraft(data.draft);
        setBaseline(data.draft);
        setCardDraft(data.card_config);
        setCardBaseline(data.card_config);
        setSidebarDraft(data.sidebar_config);
        setSidebarBaseline(data.sidebar_config);
        if (typeof data.revision === 'number') {
            setRevision(data.revision);
        }
    }, [data]);

    const isDirty = useMemo(
        () =>
            (draft !== null && baseline !== null && JSON.stringify(draft) !== JSON.stringify(baseline)) ||
            (cardDraft !== null && cardBaseline !== null && JSON.stringify(cardDraft) !== JSON.stringify(cardBaseline)) ||
            (sidebarDraft !== null && sidebarBaseline !== null && JSON.stringify(sidebarDraft) !== JSON.stringify(sidebarBaseline)),
        [draft, baseline, cardDraft, cardBaseline, sidebarDraft, sidebarBaseline],
    );

    useThemeStudioPreview({
        iframeRef,
        draft,
        previewMode,
        cardDraft,
        sidebarDraft,
        presets: presetsData?.presets,
        fallbackCard: data?.card_config ?? null,
        fallbackSidebar: data?.sidebar_config ?? null,
    });

    const setField = useCallback(<K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => {
        setDraft((cur) => (cur === null ? cur : { ...cur, [key]: value }));
    }, []);

    const setCardField = useCallback(<K extends keyof CardConfig>(key: K, value: CardConfig[K]) => {
        setCardDraft((cur) => (cur === null ? cur : { ...cur, [key]: value }));
    }, []);

    const setSidebarField = useCallback(<K extends keyof SidebarConfig>(key: K, value: SidebarConfig[K]) => {
        setSidebarDraft((cur) => (cur === null ? cur : { ...cur, [key]: value }));
    }, []);

    const save = useCallback(async () => {
        if (!draft) return;
        setIsSaving(true);
        setSaveError(null);
        try {
            const body = {
                ...draft,
                card_config: cardDraft,
                sidebar_config: sidebarDraft,
                expected_revision: revision,
            };
            const response = await request<SaveResponse>('/api/admin/theme/save', {
                method: 'POST',
                body: JSON.stringify(body),
            });
            if (typeof response.revision === 'number') {
                setRevision(response.revision);
            }
            setBaseline(draft);
            if (cardDraft) setCardBaseline(cardDraft);
            if (sidebarDraft) setSidebarBaseline(sidebarDraft);
            await queryClient.invalidateQueries({ queryKey: ['theme'] });
            await queryClient.invalidateQueries({ queryKey: ['branding'] });
        } catch (err) {
            // 409 = another writer (admin tab, Filament, CLI) bumped the
            // revision since /state. Surface a stable error code so the
            // page can render a "reload" CTA, and refetch /state so the
            // next save attempt carries a fresh revision.
            if (err instanceof ApiError && err.status === 409) {
                setSaveError('theme.stale_revision');
                await queryClient.invalidateQueries({ queryKey: ['admin', 'theme', 'state'] });
            } else {
                setSaveError(err instanceof Error ? err.message : 'save_failed');
            }
        } finally {
            setIsSaving(false);
        }
    }, [draft, cardDraft, sidebarDraft, revision, queryClient]);

    const reset = useCallback(async () => {
        setIsSaving(true);
        setSaveError(null);
        try {
            await request('/api/admin/theme/reset', { method: 'POST' });
            const fresh = await request<ThemeStudioStateResponse>('/api/admin/theme/state');
            setDraft(fresh.draft);
            setBaseline(fresh.draft);
            setCardDraft(fresh.card_config);
            setCardBaseline(fresh.card_config);
            setSidebarDraft(fresh.sidebar_config);
            setSidebarBaseline(fresh.sidebar_config);
            if (typeof fresh.revision === 'number') {
                setRevision(fresh.revision);
            }
            await queryClient.invalidateQueries({ queryKey: ['theme'] });
            await queryClient.invalidateQueries({ queryKey: ['admin', 'theme', 'state'] });
        } catch (err) {
            setSaveError(err instanceof Error ? err.message : 'reset_failed');
        } finally {
            setIsSaving(false);
        }
    }, [queryClient]);

    const discard = useCallback(() => {
        setDraft(baseline);
        setCardDraft(cardBaseline);
        setSidebarDraft(sidebarBaseline);
    }, [baseline, cardBaseline, sidebarBaseline]);

    // Merge so keys absent from the import (e.g. older exports missing
    // theme_suspended) keep their current value. Leaves the studio dirty;
    // the admin reviews in the preview, then clicks Publish to persist.
    const loadFromExport = useCallback((parsed: ParsedImport) => {
        setDraft((cur) => (cur === null ? cur : { ...cur, ...parsed.draft }));
        if (parsed.cardConfig) setCardDraft((cur) => (cur === null ? cur : { ...cur, ...parsed.cardConfig }));
        if (parsed.sidebarConfig) setSidebarDraft((cur) => (cur === null ? cur : { ...cur, ...parsed.sidebarConfig }));
    }, []);

    const exportPayload = useCallback(
        (): ThemeExport | null => (draft ? buildExportPayload(draft, cardDraft, sidebarDraft) : null),
        [draft, cardDraft, sidebarDraft],
    );

    return {
        draft,
        cardDraft,
        sidebarDraft,
        sampleServerId,
        isLoading,
        isDirty,
        isSaving,
        saveError,
        isError,
        loadError: error instanceof Error ? error.message : null,
        refetch: () => {
            void refetch();
        },
        scene,
        previewMode,
        breakpoint,
        iframeRef,
        setScene,
        setPreviewMode,
        setBreakpoint,
        setField,
        setCardField,
        setSidebarField,
        save,
        reset,
        discard,
        loadFromExport,
        exportPayload,
    };
}
