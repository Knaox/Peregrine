import { useTranslation } from 'react-i18next';
import { SelectField } from './fields/SelectField';
import { SliderField } from './fields/SliderField';
import { ToggleField } from './fields/ToggleField';
import type { CardConfig } from '@/hooks/useCardConfig';

interface ThemeCardsSectionProps {
    card: CardConfig;
    onField: <K extends keyof CardConfig>(key: K, value: CardConfig[K]) => void;
}

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

    const styleOptions = STYLE_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.card_style.${o.value}`, o.label),
    }));
    const sortOptions = SORT_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.card_sort.${o.value}`, o.label),
    }));
    const groupOptions = GROUP_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.card_group.${o.value}`, o.label),
    }));

    return (
        <div className="flex flex-col gap-4">
            <SelectField
                label={t('theme_studio.fields.card_style', 'Card style')}
                value={card.card_style}
                options={styleOptions}
                onChange={(v) => onField('card_style', v)}
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
                options={sortOptions}
                onChange={(v) => onField('sort_default', v)}
            />
            <SelectField
                label={t('theme_studio.fields.group_by', 'Group by')}
                value={card.group_by}
                options={groupOptions}
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
