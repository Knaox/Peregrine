import { forwardRef, useMemo } from 'react';
import {
    BREAKPOINT_WIDTHS,
    resolveScenePath,
    type PreviewBreakpoint,
    type PreviewScene,
} from '@/types/themeStudio.types';

interface ThemePreviewFrameProps {
    scene: PreviewScene;
    breakpoint: PreviewBreakpoint;
    /** Identifier of the server used for in-server scenes. Null disables them. */
    sampleServerId: string | null;
}

/**
 * Iframe wrapper that renders a chosen SPA route in preview mode. Width is
 * driven by the breakpoint (centered with horizontal scroll-margin), and
 * each scene change forces a remount via the `key` prop so the freshly-loaded
 * iframe announces `peregrine:theme:ready` and the parent re-pushes the
 * current draft. Otherwise React Router would keep the old route mounted
 * with a stale theme listener.
 *
 * If the selected scene needs a server but none is available, the iframe
 * is suppressed and a message is rendered in its place — the toolbar should
 * already disable that scene button, so this is a defensive fallback.
 */
export const ThemePreviewFrame = forwardRef<HTMLIFrameElement, ThemePreviewFrameProps>(
    ({ scene, breakpoint, sampleServerId }, ref) => {
        const path = useMemo(
            () => resolveScenePath(scene, sampleServerId),
            [scene, sampleServerId],
        );
        const width = BREAKPOINT_WIDTHS[breakpoint];

        return (
            <div className="flex flex-1 items-start justify-center overflow-auto bg-[var(--color-background)] p-8">
                <div
                    className="theme-studio-frame relative overflow-hidden rounded-xl border border-[var(--color-border)]/60"
                    style={{
                        width: `${width}px`,
                        maxWidth: '100%',
                        height: 'calc(100vh - 200px)',
                        minHeight: '480px',
                        boxShadow: [
                            '0 1px 0 rgba(255, 255, 255, 0.04) inset',
                            '0 12px 32px rgba(0, 0, 0, 0.25)',
                            '0 4px 8px rgba(0, 0, 0, 0.12)',
                        ].join(', '),
                    }}
                >
                    {path === null ? (
                        <div className="flex h-full w-full items-center justify-center bg-[var(--color-surface)] p-8 text-center text-sm text-[var(--color-text-muted)]">
                            {/* Defensive — toolbar should already disable this scene */}
                            <span>{scene}</span>
                        </div>
                    ) : (
                        <iframe
                            ref={ref}
                            key={`${scene}-${width}-${sampleServerId ?? 'noserver'}`}
                            src={`${path}?preview=1`}
                            title={`Preview: ${scene}`}
                            className="h-full w-full border-0 bg-[var(--color-background)]"
                            sandbox="allow-same-origin allow-scripts allow-forms"
                        />
                    )}
                </div>
            </div>
        );
    },
);

ThemePreviewFrame.displayName = 'ThemePreviewFrame';
