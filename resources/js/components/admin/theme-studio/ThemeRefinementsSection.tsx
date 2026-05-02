import { useTranslation } from 'react-i18next';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import type { ThemeDraft } from '@/types/themeStudio.types';

interface ThemeRefinementsSectionProps {
    draft: ThemeDraft;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
}

const ANIMATION_OPTIONS = [
    { value: 'instant', label: 'Instant (0 ms)' },
    { value: 'faster', label: 'Faster (150 ms)' },
    { value: 'default', label: 'Default (250 ms)' },
    { value: 'slower', label: 'Slower (450 ms)' },
] as const;

const HOVER_OPTIONS = [
    { value: 'subtle', label: 'Subtle (1.02×)' },
    { value: 'default', label: 'Default (1.05×)' },
    { value: 'pronounced', label: 'Pronounced (1.1×)' },
] as const;

const FONT_SIZE_OPTIONS = [
    { value: 'small', label: 'Small (14 px)' },
    { value: 'default', label: 'Default (16 px)' },
    { value: 'large', label: 'Large (18 px)' },
    { value: 'xl', label: 'XL (20 px)' },
] as const;

const APP_PATTERN_OPTIONS = [
    { value: 'none', label: 'None (animated constellation)' },
    { value: 'gradient', label: 'Animated gradient' },
    { value: 'mesh', label: 'Mesh gradient' },
    { value: 'orbs', label: 'Floating orbs' },
    { value: 'aurora', label: 'Aurora flow' },
    { value: 'dots', label: 'Dots pattern' },
    { value: 'grid', label: 'Grid lines' },
    { value: 'noise', label: 'Noise texture' },
] as const;

/**
 * Cross-cutting "plus de perso" knobs — pace of animations, hover scale,
 * border width, glass blur intensity, base font size. Each maps to a CSS
 * variable consumed across components.
 */
export function ThemeRefinementsSection({ draft, onField }: ThemeRefinementsSectionProps) {
    const { t } = useTranslation();

    const animationOptions = ANIMATION_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.animation_speed.${o.value}`, o.label),
    }));
    const hoverOptions = HOVER_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.hover_scale.${o.value}`, o.label),
    }));
    const fontSizeOptions = FONT_SIZE_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.font_size_scale.${o.value}`, o.label),
    }));
    const appPatternOptions = APP_PATTERN_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.app_pattern.${o.value}`, o.label),
    }));

    return (
        <div className="flex flex-col gap-4">
            <SelectField
                label={t('theme_studio.fields.theme_animation_speed', 'Animation speed')}
                value={draft.theme_animation_speed}
                options={animationOptions}
                onChange={(v) => onField('theme_animation_speed', v)}
            />
            <SelectField
                label={t('theme_studio.fields.theme_hover_scale', 'Hover scale')}
                value={draft.theme_hover_scale}
                options={hoverOptions}
                onChange={(v) => onField('theme_hover_scale', v)}
            />
            <SliderField
                label={t('theme_studio.fields.theme_border_width', 'Border width')}
                value={draft.theme_border_width}
                min={1}
                max={3}
                step={1}
                suffix=" px"
                onChange={(v) => onField('theme_border_width', v)}
            />
            <SliderField
                label={t('theme_studio.fields.theme_glass_blur_global', 'Glass blur (global)')}
                value={draft.theme_glass_blur_global}
                min={0}
                max={48}
                step={2}
                suffix=" px"
                onChange={(v) => onField('theme_glass_blur_global', v)}
            />
            <SelectField
                label={t('theme_studio.fields.theme_font_size_scale', 'Base font size')}
                value={draft.theme_font_size_scale}
                options={fontSizeOptions}
                onChange={(v) => onField('theme_font_size_scale', v)}
            />
            <SelectField
                label={t('theme_studio.fields.theme_app_background_pattern', 'App background')}
                value={draft.theme_app_background_pattern}
                options={appPatternOptions}
                onChange={(v) => onField('theme_app_background_pattern', v)}
                description={t(
                    'theme_studio.fields.theme_app_background_pattern_help',
                    'Replaces the default animated constellation behind dashboard / profile / etc.',
                )}
            />
        </div>
    );
}
