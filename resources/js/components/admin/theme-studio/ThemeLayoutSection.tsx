import { useTranslation } from 'react-i18next';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import { ToggleField } from './fields/ToggleField';
import type { ThemeDraft } from '@/types/themeStudio.types';

const SHELL_VARIANT_OPTIONS = [
    { value: 'default', label: 'Top navbar (classic)' },
    { value: 'workspace', label: 'Workspace rail (left vertical)' },
] as const;

const HEADER_ALIGN_OPTIONS = [
    { value: 'default', label: 'Logo left' },
    { value: 'centered', label: 'Centered nav' },
    { value: 'split', label: 'Split' },
] as const;

const CONTAINER_MAX_OPTIONS = [
    { value: '1280', label: '1280 px' },
    { value: '1440', label: '1440 px' },
    { value: '1536', label: '1536 px' },
    { value: 'full', label: 'Full width' },
] as const;

const PAGE_PADDING_OPTIONS = [
    { value: 'compact', label: 'Compact' },
    { value: 'comfortable', label: 'Comfortable' },
    { value: 'spacious', label: 'Spacious' },
] as const;

interface ThemeLayoutSectionProps {
    draft: ThemeDraft;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
}

/**
 * Layout shell controls — Vague 3 démarrage. Pilots header geometry,
 * container max-width and page padding via CSS variables emitted by
 * `buildPreviewVariables` (TS) and `CssVariableBuilder::layoutVariables`
 * (PHP). Per-page overrides are NOT exposed here — that comes in Vague 3
 * complète.
 */
export function ThemeLayoutSection({ draft, onField }: ThemeLayoutSectionProps) {
    const { t } = useTranslation();

    const shellOptions = SHELL_VARIANT_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.app_shell_variant.${o.value}`, o.label),
    }));
    const alignOptions = HEADER_ALIGN_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.layout_align.${o.value}`, o.label),
    }));
    const containerOptions = CONTAINER_MAX_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.container_max.${o.value}`, o.label),
    }));
    const paddingOptions = PAGE_PADDING_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.page_padding.${o.value}`, o.label),
    }));

    const isWorkspace = draft.theme_app_shell_variant === 'workspace';

    return (
        <div className="flex flex-col gap-4">
            <SelectField
                label={t('theme_studio.fields.theme_app_shell_variant', 'App shell')}
                value={draft.theme_app_shell_variant}
                options={shellOptions}
                onChange={(v) => onField('theme_app_shell_variant', v as ThemeDraft['theme_app_shell_variant'])}
            />

            {isWorkspace ? (
                <>
                    <p className="-mt-2 rounded-[var(--radius-md)] border border-[var(--color-border)]/40 bg-[var(--color-surface)]/60 px-3 py-2 text-[11px] leading-relaxed text-[var(--color-text-muted)]">
                        {t(
                            'theme_studio.fields.theme_app_shell_variant_help_ws',
                            'Workspace mode shows the rail-specific controls below. Container width and page padding still apply to both shells.',
                        )}
                    </p>
                    <SliderField
                        label={t('theme_studio.fields.theme_workspace_rail_width', 'Rail width')}
                        value={draft.theme_workspace_rail_width}
                        min={60}
                        max={120}
                        step={2}
                        suffix=" px"
                        onChange={(v) => onField('theme_workspace_rail_width', v)}
                    />
                </>
            ) : (
                <>
                    <SliderField
                        label={t('theme_studio.fields.theme_layout_header_height', 'Header height')}
                        value={draft.theme_layout_header_height}
                        min={48}
                        max={96}
                        step={2}
                        suffix=" px"
                        onChange={(v) => onField('theme_layout_header_height', v)}
                    />
                    <ToggleField
                        label={t('theme_studio.fields.theme_layout_header_sticky', 'Sticky header')}
                        value={draft.theme_layout_header_sticky}
                        onChange={(v) => onField('theme_layout_header_sticky', v)}
                    />
                    <SelectField
                        label={t('theme_studio.fields.theme_layout_header_align', 'Header layout')}
                        value={draft.theme_layout_header_align}
                        options={alignOptions}
                        onChange={(v) => onField('theme_layout_header_align', v)}
                    />
                </>
            )}

            <SelectField
                label={t('theme_studio.fields.theme_layout_container_max', 'Container width')}
                value={draft.theme_layout_container_max}
                options={containerOptions}
                onChange={(v) => onField('theme_layout_container_max', v)}
            />
            <SelectField
                label={t('theme_studio.fields.theme_layout_page_padding', 'Page padding')}
                value={draft.theme_layout_page_padding}
                options={paddingOptions}
                onChange={(v) => onField('theme_layout_page_padding', v)}
            />
        </div>
    );
}
