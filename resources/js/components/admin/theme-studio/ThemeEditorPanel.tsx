import { useTranslation } from 'react-i18next';
import { ColorField } from './fields/ColorField';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import { TextareaField } from './fields/TextareaField';
import { ThemePresetSelector } from './ThemePresetSelector';
import { ThemeLayoutSection } from './ThemeLayoutSection';
import { ThemeSidebarSection } from './ThemeSidebarSection';
import { ThemeSidebarNavSection } from './ThemeSidebarNavSection';
import { ThemeCardsSection } from './ThemeCardsSection';
import { ThemeLoginSection } from './ThemeLoginSection';
import { ThemePagesSection } from './ThemePagesSection';
import { ThemeFooterSection } from './ThemeFooterSection';
import { ThemeRefinementsSection } from './ThemeRefinementsSection';
import type { ThemeDraft } from '@/types/themeStudio.types';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

const COLOR_GROUPS: Array<{
    titleKey: string;
    fields: ReadonlyArray<keyof ThemeDraft>;
}> = [
    {
        titleKey: 'theme_studio.section_brand',
        fields: [
            'theme_primary',
            'theme_primary_hover',
            'theme_secondary',
            'theme_ring',
        ] as const,
    },
    {
        titleKey: 'theme_studio.section_status',
        fields: [
            'theme_danger',
            'theme_warning',
            'theme_success',
            'theme_info',
            'theme_suspended',
            'theme_installing',
        ] as const,
    },
    {
        titleKey: 'theme_studio.section_surfaces',
        fields: [
            'theme_background',
            'theme_surface',
            'theme_surface_hover',
            'theme_surface_elevated',
            'theme_border',
            'theme_border_hover',
        ] as const,
    },
    {
        titleKey: 'theme_studio.section_text',
        fields: ['theme_text_primary', 'theme_text_secondary', 'theme_text_muted'] as const,
    },
];

const FONT_OPTIONS = [
    { value: 'Inter', label: 'Inter' },
    { value: 'Plus Jakarta Sans', label: 'Plus Jakarta Sans' },
    { value: 'Space Grotesk', label: 'Space Grotesk' },
    { value: 'Outfit', label: 'Outfit' },
    { value: 'Manrope', label: 'Manrope' },
    { value: 'Lexend', label: 'Lexend' },
    { value: 'DM Sans', label: 'DM Sans' },
    { value: 'Figtree', label: 'Figtree' },
    { value: 'system-ui', label: 'System UI' },
] as const;

const RADIUS_OPTIONS = [
    { value: '0', label: 'None' },
    { value: '0.25rem', label: 'Small' },
    { value: '0.375rem', label: 'Medium' },
    { value: '0.75rem', label: 'Large' },
    { value: '1rem', label: 'XL' },
    { value: '1.5rem', label: '2XL' },
] as const;

const DENSITY_OPTIONS = [
    { value: 'compact', label: 'Compact' },
    { value: 'comfortable', label: 'Comfortable' },
    { value: 'spacious', label: 'Spacious' },
] as const;

interface ThemeEditorPanelProps {
    draft: ThemeDraft;
    cardDraft: CardConfig | null;
    sidebarDraft: SidebarConfig | null;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
    onCardField: <K extends keyof CardConfig>(key: K, value: CardConfig[K]) => void;
    onSidebarField: <K extends keyof SidebarConfig>(key: K, value: SidebarConfig[K]) => void;
    onApplyPreset: (presetId: string, values: Partial<ThemeDraft>) => void;
}

export function ThemeEditorPanel({
    draft,
    cardDraft,
    sidebarDraft,
    onField,
    onCardField,
    onSidebarField,
    onApplyPreset,
}: ThemeEditorPanelProps) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col">
            <Section title={t('theme_studio.section_preset', 'Preset')}>
                <div className="flex flex-col gap-4">
                    <ThemePresetSelector
                        activePreset={draft.theme_preset}
                        activeMode={draft.theme_mode}
                        onApply={onApplyPreset}
                    />
                    <SelectField
                        label={t('theme_studio.fields.theme_mode', 'Default colour mode')}
                        value={draft.theme_mode}
                        options={[
                            { value: 'auto', label: t('theme_studio.modes.auto', 'Auto (follow system)') },
                            { value: 'dark', label: t('theme_studio.modes.dark', 'Dark') },
                            { value: 'light', label: t('theme_studio.modes.light', 'Light') },
                        ]}
                        onChange={(v) => onField('theme_mode', v)}
                        description={t(
                            'theme_studio.fields.theme_mode_help',
                            'Default for users who haven’t explicitly picked one in their profile.',
                        )}
                    />
                </div>
            </Section>

            {COLOR_GROUPS.map((group) => (
                <Section key={group.titleKey} title={t(group.titleKey)}>
                    <div className="grid grid-cols-2 gap-x-3 gap-y-4">
                        {group.fields.map((key) => (
                            <ColorField
                                key={key}
                                label={t(`theme_studio.fields.${key}`, key)}
                                value={(draft[key] as string) || '#000000'}
                                onChange={(v) => onField(key, v as ThemeDraft[typeof key])}
                            />
                        ))}
                    </div>
                </Section>
            ))}

            <Section title={t('theme_studio.section_layout', 'Layout')}>
                <ThemeLayoutSection draft={draft} onField={onField} />
            </Section>

            <Section title={t('theme_studio.section_sidebar', 'Server sidebar')}>
                <ThemeSidebarSection
                    draft={draft}
                    sidebar={sidebarDraft}
                    onField={onField}
                />
            </Section>

            {sidebarDraft && (
                <Section title={t('theme_studio.section_sidebar_nav', 'Server sidebar nav')}>
                    <ThemeSidebarNavSection sidebar={sidebarDraft} onField={onSidebarField} />
                </Section>
            )}

            {cardDraft && (
                <Section title={t('theme_studio.section_cards', 'Server cards')}>
                    <ThemeCardsSection card={cardDraft} onField={onCardField} />
                </Section>
            )}

            <Section title={t('theme_studio.section_login', 'Login page')}>
                <ThemeLoginSection draft={draft} onField={onField} />
            </Section>

            <Section title={t('theme_studio.section_pages', 'Page overrides')}>
                <ThemePagesSection draft={draft} onField={onField} />
            </Section>

            <Section title={t('theme_studio.section_footer', 'Footer')}>
                <ThemeFooterSection draft={draft} onField={onField} />
            </Section>

            <Section title={t('theme_studio.section_refinements', 'Refinements')}>
                <ThemeRefinementsSection draft={draft} onField={onField} />
            </Section>

            <Section title={t('theme_studio.section_typography', 'Typography')}>
                <div className="flex flex-col gap-4">
                    <SelectField
                        label={t('theme_studio.fields.theme_font', 'Font family')}
                        value={draft.theme_font}
                        options={FONT_OPTIONS}
                        onChange={(v) => onField('theme_font', v)}
                    />
                    <SelectField
                        label={t('theme_studio.fields.theme_radius', 'Border radius')}
                        value={draft.theme_radius}
                        options={RADIUS_OPTIONS}
                        onChange={(v) => onField('theme_radius', v)}
                    />
                    <SelectField
                        label={t('theme_studio.fields.theme_density', 'Density')}
                        value={draft.theme_density}
                        options={DENSITY_OPTIONS}
                        onChange={(v) => onField('theme_density', v)}
                    />
                    <SliderField
                        label={t('theme_studio.fields.theme_shadow_intensity', 'Shadow intensity')}
                        value={draft.theme_shadow_intensity}
                        min={0}
                        max={100}
                        step={5}
                        suffix="%"
                        onChange={(v) => onField('theme_shadow_intensity', v)}
                    />
                </div>
            </Section>

            <Section title={t('theme_studio.section_custom_css', 'Custom CSS')}>
                <TextareaField
                    label={t('theme_studio.fields.theme_custom_css', 'Inline CSS overrides')}
                    value={draft.theme_custom_css}
                    onChange={(v) => onField('theme_custom_css', v)}
                    rows={8}
                    placeholder={t(
                        'theme_studio.custom_css_placeholder',
                        'body { background: var(--color-background); }',
                    )}
                    description={t(
                        'theme_studio.custom_css_help',
                        'Injected as-is into a <style> tag on every page.',
                    )}
                />
            </Section>
        </div>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="border-b border-[var(--color-border)]/40 px-6 py-5 last:border-b-0">
            <div className="mb-4 flex items-center gap-2">
                <span
                    className="h-1 w-1 rounded-full bg-[var(--color-text-muted)]"
                    aria-hidden
                />
                <h3 className="text-[12px] font-semibold tracking-tight text-[var(--color-text-primary)]">
                    {title}
                </h3>
            </div>
            {children}
        </section>
    );
}
