import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import {
    SCENE_DEFINITIONS,
    type PreviewBreakpoint,
    type PreviewMode,
    type PreviewScene,
} from '@/types/themeStudio.types';

interface ThemePreviewToolbarProps {
    scene: PreviewScene;
    mode: PreviewMode;
    breakpoint: PreviewBreakpoint;
    /** When null, all scenes that need a server are rendered disabled. */
    sampleServerId: string | null;
    onScene: (s: PreviewScene) => void;
    onMode: (m: PreviewMode) => void;
    onBreakpoint: (b: PreviewBreakpoint) => void;
}

const USER_SCENES: ReadonlyArray<{ key: PreviewScene; labelKey: string }> = [
    { key: 'dashboard', labelKey: 'theme_studio.scenes.dashboard' },
    { key: 'login', labelKey: 'theme_studio.scenes.login' },
    { key: 'profile', labelKey: 'theme_studio.scenes.profile' },
    { key: 'security', labelKey: 'theme_studio.scenes.security' },
];

const SERVER_SCENES: ReadonlyArray<{ key: PreviewScene; labelKey: string }> = [
    { key: 'server_overview', labelKey: 'theme_studio.scenes.server_overview' },
    { key: 'server_console', labelKey: 'theme_studio.scenes.server_console' },
    { key: 'server_files', labelKey: 'theme_studio.scenes.server_files' },
    { key: 'server_databases', labelKey: 'theme_studio.scenes.server_databases' },
];

const BREAKPOINTS: ReadonlyArray<{ key: PreviewBreakpoint; labelKey: string }> = [
    { key: 'mobile', labelKey: 'theme_studio.breakpoints.mobile' },
    { key: 'tablet', labelKey: 'theme_studio.breakpoints.tablet' },
    { key: 'desktop', labelKey: 'theme_studio.breakpoints.desktop' },
];

export function ThemePreviewToolbar({
    scene,
    mode,
    breakpoint,
    sampleServerId,
    onScene,
    onMode,
    onBreakpoint,
}: ThemePreviewToolbarProps) {
    const { t } = useTranslation();
    const serverScenesDisabled = sampleServerId === null;
    const disabledTooltip = serverScenesDisabled
        ? t('theme_studio.server_scenes_disabled')
        : undefined;

    return (
        <div className="flex flex-wrap items-center gap-5 border-b border-[var(--color-border)]/60 bg-[var(--color-surface-elevated)]/80 px-6 py-3.5 backdrop-blur-sm">
            <SegmentedGroup label={t('theme_studio.toolbar.scene', 'Scene')}>
                {USER_SCENES.map((s) => (
                    <SegmentedButton
                        key={s.key}
                        active={scene === s.key}
                        onClick={() => onScene(s.key)}
                    >
                        {t(s.labelKey)}
                    </SegmentedButton>
                ))}
                <span
                    className="mx-1.5 h-1 w-1 rounded-full bg-[var(--color-text-muted)]/40"
                    aria-hidden
                />
                {SERVER_SCENES.map((s) => {
                    const def = SCENE_DEFINITIONS[s.key];
                    const disabled = def.needsServer && serverScenesDisabled;
                    return (
                        <SegmentedButton
                            key={s.key}
                            active={scene === s.key}
                            disabled={disabled}
                            title={disabled ? disabledTooltip : undefined}
                            onClick={() => onScene(s.key)}
                        >
                            {t(s.labelKey)}
                        </SegmentedButton>
                    );
                })}
            </SegmentedGroup>

            <SegmentedGroup label={t('theme_studio.toolbar.mode', 'Mode')}>
                <SegmentedButton active={mode === 'dark'} onClick={() => onMode('dark')}>
                    {t('theme_studio.modes.dark', 'Dark')}
                </SegmentedButton>
                <SegmentedButton active={mode === 'light'} onClick={() => onMode('light')}>
                    {t('theme_studio.modes.light', 'Light')}
                </SegmentedButton>
            </SegmentedGroup>

            <SegmentedGroup label={t('theme_studio.toolbar.breakpoint', 'Breakpoint')}>
                {BREAKPOINTS.map((b) => (
                    <SegmentedButton
                        key={b.key}
                        active={breakpoint === b.key}
                        onClick={() => onBreakpoint(b.key)}
                    >
                        {t(b.labelKey)}
                    </SegmentedButton>
                ))}
            </SegmentedGroup>
        </div>
    );
}

function SegmentedGroup({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-2.5">
            <span className="text-[10px] font-semibold uppercase tracking-[0.1em] text-[var(--color-text-muted)]">
                {label}
            </span>
            <div className="inline-flex items-center gap-0.5 rounded-lg border border-[var(--color-border)]/60 bg-[var(--color-surface)]/60 p-1 backdrop-blur-sm">
                {children}
            </div>
        </div>
    );
}

function SegmentedButton({
    active,
    disabled = false,
    title,
    onClick,
    children,
}: {
    active: boolean;
    disabled?: boolean;
    title?: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            title={title}
            className={clsx(
                'rounded-md px-3 py-1.5 text-[11px] font-medium transition-all duration-150',
                disabled && 'cursor-not-allowed opacity-40',
                !disabled && active &&
                    'bg-[var(--color-primary)] text-white shadow-[0_0_0_1px_var(--color-primary)]',
                !disabled && !active &&
                    'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]/80 hover:text-[var(--color-text-primary)]',
            )}
            aria-pressed={active}
        >
            {children}
        </button>
    );
}
