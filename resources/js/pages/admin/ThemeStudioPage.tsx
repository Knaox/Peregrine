import { useEffect, useState } from 'react';
import { Navigate, Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuthStore } from '@/stores/authStore';
import { useThemeStudio } from '@/hooks/useThemeStudio';
import { LoadingScreen } from '@/components/LoadingScreen';
import { Button } from '@/components/ui/Button';
import { ThemeEditorPanel } from '@/components/admin/theme-studio/ThemeEditorPanel';
import { ThemePreviewToolbar } from '@/components/admin/theme-studio/ThemePreviewToolbar';
import { ThemePreviewFrame } from '@/components/admin/theme-studio/ThemePreviewFrame';
import { ThemeResetDialog } from '@/components/admin/theme-studio/ThemeResetDialog';
import { ThemeStudioMobileGuard } from '@/components/admin/theme-studio/ThemeStudioMobileGuard';
import { ThemeStudioErrorScreen } from '@/components/admin/theme-studio/ThemeStudioErrorScreen';
import type { ThemeDraft } from '@/types/themeStudio.types';

const Icon = ({ d, size = 14 }: { d: string; size?: number }) => (
    <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
    >
        <path d={d} />
    </svg>
);

const ARROW_LEFT = 'M19 12H5 M12 19l-7-7 7-7';
const SAVE_ICON = 'M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z M17 21v-8H7v8 M7 3v5h8';
const RESET_ICON = 'M3 12a9 9 0 1015-7 M3 4v5h5';
const UNDO_ICON = 'M3 7v6h6 M21 17a9 9 0 00-15-6.7L3 13';

const PRESET_KEYS: ReadonlyArray<keyof ThemeDraft> = [
    'theme_primary',
    'theme_primary_hover',
    'theme_secondary',
    'theme_ring',
    'theme_danger',
    'theme_warning',
    'theme_success',
    'theme_info',
    'theme_suspended',
    'theme_installing',
    'theme_background',
    'theme_surface',
    'theme_surface_hover',
    'theme_surface_elevated',
    'theme_border',
    'theme_border_hover',
    'theme_text_primary',
    'theme_text_secondary',
    'theme_text_muted',
];

export function ThemeStudioPage() {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const { user, isLoading: authLoading, loadUser } = useAuthStore();
    const studio = useThemeStudio();
    const [resetOpen, setResetOpen] = useState(false);
    const [isWide, setIsWide] = useState(
        () => typeof window !== 'undefined' && window.innerWidth >= 1200,
    );

    useEffect(() => {
        loadUser();
    }, [loadUser]);

    useEffect(() => {
        const onResize = () => setIsWide(window.innerWidth >= 1200);
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    // `beforeunload` covers tab close and hard refresh — the most common
    // ways an admin loses unpublished work. We can't use `useBlocker`
    // here because the app is wired with `<BrowserRouter>` (not the
    // data-router stack), so the only in-app navigation we explicitly
    // intercept is the studio's own back-chevron button below.
    useEffect(() => {
        const onBeforeUnload = (e: BeforeUnloadEvent): void => {
            if (studio.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', onBeforeUnload);
        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [studio.isDirty]);

    const handleBack = (e: React.MouseEvent<HTMLAnchorElement>): void => {
        if (!studio.isDirty || studio.isSaving) return;
        e.preventDefault();
        const ok = window.confirm(
            t(
                'theme_studio.unsaved_leave_confirm',
                'You have unpublished theme changes. Leave anyway and lose them?',
            ),
        );
        if (ok) navigate('/dashboard');
    };

    if (authLoading || studio.isLoading) {
        return <LoadingScreen />;
    }
    if (!user) {
        return <Navigate to="/login" replace />;
    }
    if (!user.is_admin) {
        return <Navigate to="/dashboard" replace />;
    }
    if (!isWide) {
        return <ThemeStudioMobileGuard />;
    }
    if (studio.isError) {
        return <ThemeStudioErrorScreen loadError={studio.loadError} onRetry={studio.refetch} />;
    }
    if (!studio.draft) {
        return <LoadingScreen />;
    }

    const handleApplyPreset = (
        presetId: string,
        values: Partial<ThemeDraft>,
    ): void => {
        studio.setField('theme_preset', presetId);
        for (const key of PRESET_KEYS) {
            const next = values[key];
            if (typeof next === 'string') {
                studio.setField(key, next as ThemeDraft[typeof key]);
            }
        }
        const radius = values.theme_radius;
        if (typeof radius === 'string') studio.setField('theme_radius', radius);
        const font = values.theme_font;
        if (typeof font === 'string') studio.setField('theme_font', font);
    };

    const statusVariant: 'saved' | 'dirty' | 'saving' = studio.isSaving
        ? 'saving'
        : studio.isDirty
            ? 'dirty'
            : 'saved';
    const statusColor =
        statusVariant === 'saved'
            ? 'var(--color-success)'
            : statusVariant === 'saving'
                ? 'var(--color-info)'
                : 'var(--color-warning)';
    const statusLabel =
        statusVariant === 'saving'
            ? t('theme_studio.saving', 'Saving…')
            : statusVariant === 'dirty'
                ? t('theme_studio.unsaved', 'Unsaved changes')
                : t('theme_studio.saved', 'Up to date');

    return (
        <div className="flex h-screen flex-col bg-[var(--color-background)] text-[var(--color-text-primary)]">
            <header className="flex items-center justify-between gap-4 border-b border-[var(--color-border)]/60 bg-[var(--color-surface)]/80 px-6 py-3 backdrop-blur-sm">
                <div className="flex items-center gap-4">
                    <Link
                        to="/dashboard"
                        onClick={handleBack}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-[var(--color-text-secondary)] transition-colors hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]"
                        aria-label={t('theme_studio.back', 'Back to dashboard')}
                    >
                        <Icon d={ARROW_LEFT} size={16} />
                    </Link>
                    <div className="flex items-center gap-3">
                        <h1 className="text-[13px] font-semibold tracking-tight">
                            {t('theme_studio.title', 'Theme Studio')}
                        </h1>
                        <span
                            className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border)]/50 bg-[var(--color-surface-hover)]/40 px-2 py-0.5 text-[11px] font-medium text-[var(--color-text-secondary)]"
                            role="status"
                            aria-live="polite"
                        >
                            <span
                                className="h-1.5 w-1.5 rounded-full"
                                style={{
                                    background: statusColor,
                                    boxShadow:
                                        statusVariant === 'saved'
                                            ? 'none'
                                            : `0 0 6px ${statusColor}`,
                                }}
                            />
                            {statusLabel}
                        </span>
                    </div>
                </div>

                <div className="flex items-center gap-1.5">
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={!studio.isDirty || studio.isSaving}
                        onClick={studio.discard}
                    >
                        <Icon d={UNDO_ICON} />
                        {t('theme_studio.discard', 'Discard')}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={studio.isSaving}
                        onClick={() => setResetOpen(true)}
                    >
                        <Icon d={RESET_ICON} />
                        {t('theme_studio.reset', 'Reset to defaults')}
                    </Button>
                    <span className="mx-1 h-5 w-px bg-[var(--color-border)]/50" aria-hidden />
                    <Button
                        variant="primary"
                        size="sm"
                        isLoading={studio.isSaving}
                        disabled={!studio.isDirty || studio.isSaving}
                        onClick={() => void studio.save()}
                    >
                        <Icon d={SAVE_ICON} />
                        {t('theme_studio.publish', 'Publish')}
                    </Button>
                </div>
            </header>

            {studio.saveError && (
                <div className="border-b border-[var(--color-danger)] bg-[var(--color-danger-glow)] px-5 py-2 text-xs text-[var(--color-danger)]">
                    {studio.saveError === 'theme.stale_revision'
                        ? t(
                              'theme_studio.conflict.stale',
                              'Another admin published a theme change while you were editing. The studio refreshed — review your changes and publish again.',
                          )
                        : `${t('theme_studio.save_error', 'Save failed:')} ${studio.saveError}`}
                </div>
            )}

            <div className="flex flex-1 overflow-hidden">
                <aside className="w-[400px] shrink-0 overflow-y-auto border-r border-[var(--color-border)]/60 bg-[var(--color-surface)]/40">
                    <ThemeEditorPanel
                        draft={studio.draft}
                        cardDraft={studio.cardDraft}
                        sidebarDraft={studio.sidebarDraft}
                        onField={studio.setField}
                        onCardField={studio.setCardField}
                        onSidebarField={studio.setSidebarField}
                        onApplyPreset={handleApplyPreset}
                    />
                </aside>

                <main className="flex flex-1 flex-col overflow-hidden">
                    <ThemePreviewToolbar
                        scene={studio.scene}
                        mode={studio.previewMode}
                        breakpoint={studio.breakpoint}
                        sampleServerId={studio.sampleServerId}
                        onScene={studio.setScene}
                        onMode={studio.setPreviewMode}
                        onBreakpoint={studio.setBreakpoint}
                    />
                    <ThemePreviewFrame
                        ref={studio.iframeRef}
                        scene={studio.scene}
                        breakpoint={studio.breakpoint}
                        sampleServerId={studio.sampleServerId}
                    />
                </main>
            </div>

            <ThemeResetDialog
                open={resetOpen}
                isResetting={studio.isSaving}
                customCssLength={(studio.draft.theme_custom_css ?? '').length}
                hasCustomUploads={
                    (studio.draft.theme_login_background_image ?? '') !== '' ||
                    (studio.draft.theme_login_background_images ?? []).length > 0
                }
                onCancel={() => setResetOpen(false)}
                onConfirm={() => {
                    void studio.reset().finally(() => setResetOpen(false));
                }}
            />
        </div>
    );
}
