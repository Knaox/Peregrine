import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { request } from '@/services/http';
import type { ThemeDraft } from '@/types/themeStudio.types';

interface PresetEntry {
    label: string;
    dark: Record<string, string>;
    light: Record<string, string>;
}

interface PresetsResponse {
    presets: Record<string, PresetEntry>;
}

interface ThemePresetSelectorProps {
    activePreset: string;
    activeMode: 'dark' | 'light' | 'auto';
    onApply: (presetId: string, values: Partial<ThemeDraft>) => void;
}

/**
 * Visual brand picker. Each tile shows the preset's primary + surface so the
 * admin can compare swatches at a glance instead of guessing from a dropdown.
 * Click → emit a partial ThemeDraft holding every theme_* value defined by
 * the preset for the resolved mode (light/dark; 'auto' falls back to dark).
 */
export function ThemePresetSelector({
    activePreset,
    activeMode,
    onApply,
}: ThemePresetSelectorProps) {
    const { t } = useTranslation();
    const { data } = useQuery({
        queryKey: ['admin', 'theme', 'presets'],
        queryFn: () => request<PresetsResponse>('/api/admin/theme/presets'),
        staleTime: Infinity,
    });

    const resolvedMode: 'dark' | 'light' = activeMode === 'light' ? 'light' : 'dark';

    if (!data) {
        return (
            <div className="grid grid-cols-2 gap-2">
                {[0, 1, 2, 3].map((i) => (
                    <div
                        key={i}
                        className="h-16 rounded-md bg-[var(--color-surface-hover)] animate-pulse"
                    />
                ))}
            </div>
        );
    }

    const entries = Object.entries(data.presets);

    return (
        <div className="grid grid-cols-2 gap-2">
            {entries.map(([id, preset]) => {
                const variant = preset[resolvedMode];
                const isActive = activePreset === id;
                return (
                    <button
                        key={id}
                        type="button"
                        onClick={() => onApply(id, variant as Partial<ThemeDraft>)}
                        className={[
                            'group relative overflow-hidden rounded-md border text-left transition-all',
                            isActive
                                ? 'border-[var(--color-primary)] shadow-[0_0_0_1px_var(--color-primary)]'
                                : 'border-[var(--color-border)] hover:border-[var(--color-border-hover)]',
                        ].join(' ')}
                        aria-pressed={isActive}
                    >
                        <div
                            className="h-12 w-full"
                            style={{
                                background: `linear-gradient(135deg, ${variant.theme_primary} 0%, ${variant.theme_secondary} 100%)`,
                            }}
                        />
                        <div
                            className="flex items-center justify-between gap-2 px-2.5 py-1.5"
                            style={{
                                background: variant.theme_surface,
                                color: variant.theme_text_primary,
                            }}
                        >
                            <span className="text-xs font-medium truncate">{preset.label}</span>
                            {isActive && (
                                <span className="text-[10px] uppercase tracking-wider text-[var(--color-primary)]">
                                    {t('theme_studio.preset_active', 'Active')}
                                </span>
                            )}
                        </div>
                    </button>
                );
            })}
        </div>
    );
}
