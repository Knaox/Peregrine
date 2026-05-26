import { useCallback, useEffect, useRef } from 'react';
import { buildModeVariants } from '@/lib/themeStudio/buildModeVariants';
import { buildPreviewPayload } from '@/lib/themeStudio/buildPreviewPayload';
import type { PreviewMode, ThemeDraft } from '@/types/themeStudio.types';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

const PEER_ORIGIN = typeof window !== 'undefined' ? window.location.origin : '';

interface PresetEntry {
    label: string;
    dark: Record<string, string>;
    light: Record<string, string>;
}

interface PreviewArgs {
    iframeRef: React.RefObject<HTMLIFrameElement | null>;
    draft: ThemeDraft | null;
    previewMode: PreviewMode;
    cardDraft: CardConfig | null;
    sidebarDraft: SidebarConfig | null;
    presets: Record<string, PresetEntry> | undefined;
    fallbackCard: CardConfig | null;
    fallbackSidebar: SidebarConfig | null;
}

/**
 * Owns the studio↔preview-iframe postMessage bridge. Folds the flat draft
 * into the nested ThemeData payload the iframe expects and pushes it on
 * every draft / mode / config change (and once the iframe signals ready).
 *
 * Extracted from `useThemeStudio` so that hook stays under the 300-line cap
 * once the import/export actions land.
 */
export function useThemeStudioPreview({
    iframeRef, draft, previewMode, cardDraft, sidebarDraft, presets, fallbackCard, fallbackSidebar,
}: PreviewArgs): void {
    const iframeReadyRef = useRef(false);

    const sendThemeToIframe = useCallback(
        (next: ThemeDraft, mode: PreviewMode): void => {
            const win = iframeRef.current?.contentWindow;
            if (!win) return;
            const preset = presets?.[next.theme_preset] ?? null;
            const variants = buildModeVariants(next, preset);
            const payload = buildPreviewPayload(
                next,
                mode,
                variants,
                cardDraft ?? fallbackCard,
                sidebarDraft ?? fallbackSidebar,
            );
            win.postMessage({ type: 'peregrine:theme:update', payload }, PEER_ORIGIN);
            win.postMessage({ type: 'peregrine:theme:setMode', payload: mode }, PEER_ORIGIN);
        },
        [iframeRef, presets, cardDraft, sidebarDraft, fallbackCard, fallbackSidebar],
    );

    useEffect(() => {
        const onMessage = (event: MessageEvent): void => {
            if (event.origin !== PEER_ORIGIN) return;
            const msg = event.data as { type?: unknown } | null;
            if (msg && typeof msg === 'object' && msg.type === 'peregrine:theme:ready') {
                iframeReadyRef.current = true;
                if (draft) sendThemeToIframe(draft, previewMode);
            }
        };
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, [draft, previewMode, sendThemeToIframe]);

    useEffect(() => {
        if (!draft || !iframeReadyRef.current) return;
        sendThemeToIframe(draft, previewMode);
    }, [draft, previewMode, cardDraft, sidebarDraft, sendThemeToIframe]);
}
