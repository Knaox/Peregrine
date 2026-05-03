import { useTranslation } from 'react-i18next';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import { ToggleField } from './fields/ToggleField';
import type { CardConfig } from '@/hooks/useCardConfig';

interface ThemeCardsSectionProps {
    card: CardConfig;
    onField: <K extends keyof CardConfig>(key: K, value: CardConfig[K]) => void;
}

const LAYOUT_VARIANT_OPTIONS = [
    { value: 'classic', label: 'Classic cards (grid)' },
    { value: 'command-bar', label: 'Command bar (dense list)' },
    { value: 'bento', label: 'Bento mosaic (asymmetric tiles)' },
    { value: 'pulse-grid', label: 'Pulse grid (heatmap)' },
] as const;

const STYLE_OPTIONS = [
    { value: 'default', label: 'Default' },
    { value: 'elevated', label: 'Elevated' },
    { value: 'glass', label: 'Glass' },
    { value: 'minimal', label: 'Minimal' },
] as const;

const SORT_OPTIONS = [
    { value: 'name', label: 'Name' },
    { value: 'status', label: 'Status' },
    { value: 'created_at', label: 'Most recent' },
    { value: 'egg', label: 'Egg' },
] as const;

const GROUP_OPTIONS = [
    { value: 'none', label: 'No grouping' },
    { value: 'egg', label: 'By egg' },
    { value: 'status', label: 'By status' },
    { value: 'plan', label: 'By plan' },
] as const;

const DENSITY_OPTIONS = [
    { value: 'compact', label: 'Compact' },
    { value: 'comfortable', label: 'Comfortable' },
    { value: 'spacious', label: 'Spacious' },
] as const;

const HEADER_STYLE_OPTIONS = [
    { value: 'banner', label: 'Banner image (egg)' },
    { value: 'gradient', label: 'Brand gradient' },
    { value: 'solid', label: 'Solid surface' },
    { value: 'minimal', label: 'Minimal (no header)' },
] as const;

const STATUS_POSITION_OPTIONS = [
    { value: 'inline', label: 'Inline (next to name)' },
    { value: 'top-right', label: 'Top-right corner' },
    { value: 'top-left', label: 'Top-left corner' },
    { value: 'corner-ribbon', label: 'Corner ribbon' },
] as const;

const ACCENT_OPTIONS = [
    { value: 'none', label: 'None (flat)' },
    { value: 'subtle', label: 'Subtle (default)' },
    { value: 'bold', label: 'Bold (strong glow)' },
] as const;

const BORDER_STYLE_OPTIONS = [
    { value: 'full', label: 'Full border' },
    { value: 'accent-left', label: 'Accent bar (left)' },
    { value: 'none', label: 'No border' },
] as const;

const QUICK_ACTIONS_OPTIONS = [
    { value: 'full', label: 'Full (labels + icons)' },
    { value: 'compact', label: 'Compact' },
    { value: 'icon-only', label: 'Icon only' },
] as const;

const HOVER_EFFECT_OPTIONS = [
    { value: 'lift', label: 'Lift (translate up)' },
    { value: 'scale', label: 'Scale (zoom)' },
    { value: 'glow', label: 'Glow (ring)' },
    { value: 'none', label: 'None' },
] as const;

const VISIBILITY_FIELDS: ReadonlyArray<keyof CardConfig> = [
    'show_egg_icon',
    'show_egg_name',
    'show_plan_name',
    'show_status_badge',
    'show_stats_bars',
    'show_quick_actions',
    'show_ip_port',
    'show_uptime',
];

/**
 * Server cards configuration — visible on the dashboard. Mirrors the
 * Filament Cards tab so the studio is the single source of truth.
 */
export function ThemeCardsSection({ card, onField }: ThemeCardsSectionProps) {
    const { t } = useTranslation();

    const opt = (
        items: ReadonlyArray<{ value: string; label: string }>,
        prefix: string,
    ) =>
        items.map((o) => ({
            value: o.value,
            label: t(`theme_studio.${prefix}.${o.value}`, o.label),
        }));

    const isClassic = card.card_layout_variant === 'classic';

    return (
        <div className="flex flex-col gap-4">
            <SelectField
                label={t('theme_studio.fields.card_layout_variant', 'Dashboard layout')}
                value={card.card_layout_variant}
                options={opt(LAYOUT_VARIANT_OPTIONS, 'card_layout_variant')}
                onChange={(v) => onField('card_layout_variant', v as CardConfig['card_layout_variant'])}
            />
            {!isClassic && (
                <p className="-mt-2 rounded-[var(--radius-md)] border border-[var(--color-border)]/40 bg-[var(--color-surface)]/60 px-3 py-2 text-[11px] leading-relaxed text-[var(--color-text-muted)]">
                    {t(
                        'theme_studio.fields.card_layout_variant_help',
                        'Visibility toggles (egg name, IP, stats) and quick actions still apply, but card-specific style fields below (header, accent, border, hover) only affect the Classic variant.',
                    )}
                </p>
            )}
            <SelectField
                label={t('theme_studio.fields.card_style', 'Card style')}
                value={card.card_style}
                options={opt(STYLE_OPTIONS, 'card_style')}
                onChange={(v) => onField('card_style', v)}
            />
            <SelectField
                label={t('theme_studio.fields.card_density', 'Density')}
                value={card.card_density}
                options={opt(DENSITY_OPTIONS, 'card_density')}
                onChange={(v) => onField('card_density', v as CardConfig['card_density'])}
            />
            <SelectField
                label={t('theme_studio.fields.card_header_style', 'Header style')}
                value={card.card_header_style}
                options={opt(HEADER_STYLE_OPTIONS, 'card_header_style')}
                onChange={(v) => onField('card_header_style', v as CardConfig['card_header_style'])}
            />
            <SelectField
                label={t('theme_studio.fields.card_border_style', 'Border')}
                value={card.card_border_style}
                options={opt(BORDER_STYLE_OPTIONS, 'card_border_style')}
                onChange={(v) => onField('card_border_style', v as CardConfig['card_border_style'])}
            />
            <SelectField
                label={t('theme_studio.fields.card_status_position', 'Status indicator position')}
                value={card.card_status_position}
                options={opt(STATUS_POSITION_OPTIONS, 'card_status_position')}
                onChange={(v) => onField('card_status_position', v as CardConfig['card_status_position'])}
            />
            <SelectField
                label={t('theme_studio.fields.card_accent_strength', 'Accent / glow')}
                value={card.card_accent_strength}
                options={opt(ACCENT_OPTIONS, 'card_accent_strength')}
                onChange={(v) => onField('card_accent_strength', v as CardConfig['card_accent_strength'])}
            />
            <SelectField
                label={t('theme_studio.fields.card_hover_effect', 'Hover effect')}
                value={card.card_hover_effect}
                options={opt(HOVER_EFFECT_OPTIONS, 'card_hover_effect')}
                onChange={(v) => onField('card_hover_effect', v as CardConfig['card_hover_effect'])}
            />
            <SelectField
                label={t('theme_studio.fields.card_quick_actions_layout', 'Quick actions layout')}
                value={card.card_quick_actions_layout}
                options={opt(QUICK_ACTIONS_OPTIONS, 'card_quick_actions_layout')}
                onChange={(v) =>
                    onField('card_quick_actions_layout', v as CardConfig['card_quick_actions_layout'])
                }
            />
            <div className="grid grid-cols-3 gap-3">
                <SliderField
                    label={t('theme_studio.fields.cols_desktop', 'Desktop')}
                    value={card.columns.desktop}
                    min={1}
                    max={4}
                    step={1}
                    onChange={(v) => onField('columns', { ...card.columns, desktop: v })}
                />
                <SliderField
                    label={t('theme_studio.fields.cols_tablet', 'Tablet')}
                    value={card.columns.tablet}
                    min={1}
                    max={3}
                    step={1}
                    onChange={(v) => onField('columns', { ...card.columns, tablet: v })}
                />
                <SliderField
                    label={t('theme_studio.fields.cols_mobile', 'Mobile')}
                    value={card.columns.mobile}
                    min={1}
                    max={2}
                    step={1}
                    onChange={(v) => onField('columns', { ...card.columns, mobile: v })}
                />
            </div>
            <SelectField
                label={t('theme_studio.fields.sort_default', 'Default sort')}
                value={card.sort_default}
                options={opt(SORT_OPTIONS, 'card_sort')}
                onChange={(v) => onField('sort_default', v)}
            />
            <SelectField
                label={t('theme_studio.fields.group_by', 'Group by')}
                value={card.group_by}
                options={opt(GROUP_OPTIONS, 'card_group')}
                onChange={(v) => onField('group_by', v)}
            />
            <div className="border-t border-[var(--color-border)]/40 pt-3">
                <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">
                    {t('theme_studio.fields.card_visibility', 'Show on cards')}
                </p>
                <div className="flex flex-col gap-3">
                    {VISIBILITY_FIELDS.map((key) => (
                        <ToggleField
                            key={key}
                            label={t(`theme_studio.fields.${String(key)}`, String(key))}
                            value={card[key] as boolean}
                            onChange={(v) => onField(key, v as CardConfig[typeof key])}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
