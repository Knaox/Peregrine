import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { SelectField } from './fields/SelectField';
import { ToggleField } from './fields/ToggleField';
import type { SidebarConfig, SidebarEntry } from '@/hooks/useSidebarConfig';

interface ThemeSidebarNavSectionProps {
    sidebar: SidebarConfig;
    onField: <K extends keyof SidebarConfig>(key: K, value: SidebarConfig[K]) => void;
}

const POSITION_OPTIONS = [
    { value: 'left', label: 'Left sidebar' },
    { value: 'top', label: 'Top tabs' },
    { value: 'dock', label: 'Floating dock' },
] as const;

const STYLE_OPTIONS = [
    { value: 'default', label: 'Default' },
    { value: 'compact', label: 'Compact (rail)' },
    { value: 'pills', label: 'Pills' },
] as const;

/**
 * In-server sidebar nav config — the section the player sees on every
 * /servers/:id/* route. Mirrors the Filament Sidebar tab including the
 * 8-entry repeater (drag to reorder + on/off + per-item icon preview).
 */
export function ThemeSidebarNavSection({ sidebar, onField }: ThemeSidebarNavSectionProps) {
    const { t } = useTranslation();

    const positionOptions = POSITION_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.sidebar_position.${o.value}`, o.label),
    }));
    const styleOptions = STYLE_OPTIONS.map((o) => ({
        value: o.value,
        label: t(`theme_studio.sidebar_style_legacy.${o.value}`, o.label),
    }));

    const updateEntry = (index: number, patch: Partial<SidebarEntry>): void => {
        const next = sidebar.entries.map((e, i) => (i === index ? { ...e, ...patch } : e));
        onField('entries', next);
    };

    const moveEntry = (index: number, direction: -1 | 1): void => {
        const target = index + direction;
        if (target < 0 || target >= sidebar.entries.length) return;
        const next = [...sidebar.entries];
        const a = next[index];
        const b = next[target];
        if (!a || !b) return;
        next[index] = b;
        next[target] = a;
        onField('entries', next.map((e, i) => ({ ...e, order: i })));
    };

    return (
        <div className="flex flex-col gap-4">
            <SelectField
                label={t('theme_studio.fields.sidebar_position', 'Position')}
                value={sidebar.position}
                options={positionOptions}
                onChange={(v) => onField('position', v)}
            />
            <SelectField
                label={t('theme_studio.fields.sidebar_style_legacy', 'Style')}
                value={sidebar.style}
                options={styleOptions}
                onChange={(v) => onField('style', v)}
            />
            <ToggleField
                label={t('theme_studio.fields.show_server_status', 'Show server status')}
                value={sidebar.show_server_status}
                onChange={(v) => onField('show_server_status', v)}
            />
            <ToggleField
                label={t('theme_studio.fields.show_server_name', 'Show server name')}
                value={sidebar.show_server_name}
                onChange={(v) => onField('show_server_name', v)}
            />

            <div className="border-t border-[var(--color-border)]/40 pt-3">
                <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">
                    {t('theme_studio.fields.sidebar_entries', 'Navigation entries')}
                </p>
                <ul className="flex flex-col gap-1.5">
                    {sidebar.entries.map((entry, i) => (
                        <li
                            key={entry.id}
                            className="flex items-center gap-2 rounded-lg border border-[var(--color-border)]/60 bg-[var(--color-surface)]/40 px-2 py-1.5"
                        >
                            <div className="flex flex-col">
                                <button
                                    type="button"
                                    onClick={() => moveEntry(i, -1)}
                                    disabled={i === 0}
                                    aria-label="Move up"
                                    className="h-3 text-[10px] text-[var(--color-text-muted)] disabled:opacity-30 hover:text-[var(--color-text-primary)]"
                                >
                                    ▲
                                </button>
                                <button
                                    type="button"
                                    onClick={() => moveEntry(i, 1)}
                                    disabled={i === sidebar.entries.length - 1}
                                    aria-label="Move down"
                                    className="h-3 text-[10px] text-[var(--color-text-muted)] disabled:opacity-30 hover:text-[var(--color-text-primary)]"
                                >
                                    ▼
                                </button>
                            </div>
                            <span className="flex-1 truncate text-[12px] text-[var(--color-text-primary)]">
                                {t(entry.label_key, entry.id)}
                            </span>
                            <button
                                type="button"
                                onClick={() => updateEntry(i, { enabled: !entry.enabled })}
                                aria-pressed={entry.enabled}
                                className={clsx(
                                    'rounded-md px-2 py-1 text-[10px] font-semibold transition-colors',
                                    entry.enabled
                                        ? 'bg-[var(--color-success)]/20 text-[var(--color-success)]'
                                        : 'bg-[var(--color-surface-hover)] text-[var(--color-text-muted)]',
                                )}
                            >
                                {entry.enabled
                                    ? t('theme_studio.entry_on', 'On')
                                    : t('theme_studio.entry_off', 'Off')}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
