import { useTranslation } from 'react-i18next';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import { ImageUploadField } from './fields/ImageUploadField';
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
 * Login template + background asset. The image is only used by `split`
 * and `overlay` templates — `centered` and `minimal` ignore it.
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
                    <SliderField
                        label={t('theme_studio.fields.theme_login_background_blur', 'Background blur')}
                        value={draft.theme_login_background_blur}
                        min={0}
                        max={24}
                        step={1}
                        suffix=" px"
                        onChange={(v) => onField('theme_login_background_blur', v)}
                    />
                </>
            )}
        </div>
    );
}
