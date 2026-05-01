import { useTranslation } from 'react-i18next';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import { ToggleField } from './fields/ToggleField';
import { ImageUploadField } from './fields/ImageUploadField';
import { MultiImageUploadField } from './fields/MultiImageUploadField';
import type { ThemeDraft } from '@/types/themeStudio.types';

const TEMPLATE_OPTIONS = [
    { value: 'centered', label: 'Centered (default)' },
    { value: 'split', label: 'Split-screen with image' },
    { value: 'overlay', label: 'Fullscreen image overlay' },
    { value: 'minimal', label: 'Minimal' },
] as const;

const PATTERN_OPTIONS = [
    { value: 'gradient', label: 'Animated gradient' },
    { value: 'mesh', label: 'Mesh gradient' },
    { value: 'orbs', label: 'Floating orbs' },
    { value: 'aurora', label: 'Aurora flow' },
    { value: 'dots', label: 'Dots pattern' },
    { value: 'grid', label: 'Grid lines' },
    { value: 'noise', label: 'Noise texture' },
    { value: 'none', label: 'None (solid)' },
] as const;

interface ThemeLoginSectionProps {
    draft: ThemeDraft;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
}

/**
 * Login template + background asset(s). The image / carousel is only used
 * by `split` and `overlay` templates — `centered` and `minimal` ignore them.
 *
 * Carousel and single-image are mutually informative: when the carousel is
 * enabled we surface the multi-image picker; otherwise we keep the single
 * image picker so existing installs keep their workflow.
 */
export function ThemeLoginSection({ draft, onField }: ThemeLoginSectionProps) {
    const { t } = useTranslation();
    const usesImage = draft.theme_login_template === 'split' || draft.theme_login_template === 'overlay';

    const templateOptions = TEMPLATE_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.login_templates.${o.value}`, o.label),
    }));
    const patternOptions = PATTERN_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.login_patterns.${o.value}`, o.label),
    }));

    return (
        <div className="flex flex-col gap-4">
            <SelectField
                label={t('theme_studio.fields.theme_login_template', 'Template')}
                value={draft.theme_login_template}
                options={templateOptions}
                onChange={(v) => onField('theme_login_template', v)}
            />
            <SelectField
                label={t('theme_studio.fields.theme_login_background_pattern', 'Background pattern')}
                value={draft.theme_login_background_pattern}
                options={patternOptions}
                onChange={(v) => onField('theme_login_background_pattern', v)}
                description={t(
                    'theme_studio.fields.theme_login_background_pattern_help',
                    'Decorative background. Ignored by the Fullscreen overlay template.',
                )}
            />
            {usesImage && (
                <>
                    <ToggleField
                        label={t('theme_studio.fields.theme_login_carousel_enabled', 'Image carousel')}
                        value={draft.theme_login_carousel_enabled}
                        description={t(
                            'theme_studio.fields.theme_login_carousel_help',
                            'Cycle through multiple background images. When off, the single Background image below is used.',
                        )}
                        onChange={(v) => onField('theme_login_carousel_enabled', v)}
                    />
                    {draft.theme_login_carousel_enabled ? (
                        <>
                            <MultiImageUploadField
                                label={t('theme_studio.fields.theme_login_background_images', 'Carousel images')}
                                value={draft.theme_login_background_images}
                                slot="login_background"
                                max={8}
                                onChange={(v) => onField('theme_login_background_images', v)}
                                description={t(
                                    'theme_studio.fields.theme_login_background_images_help',
                                    'Up to 8 images. JPG / PNG / WEBP, max 5 MB each.',
                                )}
                            />
                            <SliderField
                                label={t('theme_studio.fields.theme_login_carousel_interval', 'Interval')}
                                value={draft.theme_login_carousel_interval}
                                min={2000}
                                max={30000}
                                step={500}
                                suffix=" ms"
                                onChange={(v) => onField('theme_login_carousel_interval', v)}
                            />
                            <ToggleField
                                label={t('theme_studio.fields.theme_login_carousel_random', 'Random order')}
                                value={draft.theme_login_carousel_random}
                                onChange={(v) => onField('theme_login_carousel_random', v)}
                            />
                        </>
                    ) : (
                        <ImageUploadField
                            label={t('theme_studio.fields.theme_login_background_image', 'Background image')}
                            value={draft.theme_login_background_image}
                            slot="login_background"
                            onChange={(v) => onField('theme_login_background_image', v)}
                            description={t(
                                'theme_studio.fields.theme_login_background_image_help',
                                'JPG / PNG / WEBP, max 5 MB. Hidden on mobile for split layout.',
                            )}
                        />
                    )}
                    <SliderField
                        label={t('theme_studio.fields.theme_login_background_blur', 'Background blur')}
                        value={draft.theme_login_background_blur}
                        min={0}
                        max={24}
                        step={1}
                        suffix=" px"
                        onChange={(v) => onField('theme_login_background_blur', v)}
                    />
                    <SliderField
                        label={t('theme_studio.fields.theme_login_background_opacity', 'Background opacity')}
                        value={draft.theme_login_background_opacity}
                        min={0}
                        max={100}
                        step={5}
                        suffix=" %"
                        onChange={(v) => onField('theme_login_background_opacity', v)}
                    />
                </>
            )}
        </div>
    );
}
