import { useTranslation } from 'react-i18next';
import { SliderField } from './fields/SliderField';
import { ToggleField } from './fields/ToggleField';
import type { ThemeDraft } from '@/types/themeStudio.types';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

interface ThemeSidebarSectionProps {
    draft: ThemeDraft;
    /** Read-only — needed to gate floating against `position` (left/top/dock). */
    sidebar: SidebarConfig | null;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
}

/**
 * Sidebar in-server controls (Vague 3 complète). Only governs the
 * /servers/:id sidebar (LeftSidebar / DockBar / TopTabsBar share the blur
 * value). Position + entries + style are still in the legacy
 * `sidebar_server_config` JSON edited from the Filament page.
 */
export function ThemeSidebarSection({ draft, sidebar, onField }: ThemeSidebarSectionProps) {
    const { t } = useTranslation();
    const position = sidebar?.position ?? 'left';
    const sidebarStyle = sidebar?.style ?? 'default';
    // The `:root[data-sidebar-floating="true"] .server-sidebar` CSS hook only
    // exists on LeftSidebar — DockBar/TopTabsBar are floating by design and
    // would silently ignore the toggle. Hide it instead of letting the user
    // tick a no-op switch.
    const floatingApplies = position === 'left';
    // Rail width only renders when the LeftSidebar is in compact mode. We
    // surface the requirement on the slider so the admin doesn't slide it
    // and wonder why nothing happens in the preview.
    const railVisibleNow = position === 'left' && sidebarStyle === 'compact';

    return (
        <div className="flex flex-col gap-4">
            <SliderField
                label={t('theme_studio.fields.theme_sidebar_classic_width', 'Classic width')}
                value={draft.theme_sidebar_classic_width}
                min={180}
                max={280}
                step={4}
                suffix=" px"
                onChange={(v) => onField('theme_sidebar_classic_width', v)}
            />
            <SliderField
                label={t('theme_studio.fields.theme_sidebar_rail_width', 'Rail width')}
                value={draft.theme_sidebar_rail_width}
                min={56}
                max={96}
                step={2}
                suffix=" px"
                description={
                    railVisibleNow
                        ? undefined
                        : t(
                              'theme_studio.fields.theme_sidebar_rail_help',
                              'Only visible when the Server sidebar nav style is set to "Compact (rail)" with Left position.',
                          )
                }
                onChange={(v) => onField('theme_sidebar_rail_width', v)}
            />
            <SliderField
                label={t('theme_studio.fields.theme_sidebar_mobile_width', 'Mobile drawer width')}
                value={draft.theme_sidebar_mobile_width}
                min={200}
                max={320}
                step={8}
                suffix=" px"
                onChange={(v) => onField('theme_sidebar_mobile_width', v)}
            />
            <SliderField
                label={t('theme_studio.fields.theme_sidebar_blur_intensity', 'Glass blur intensity')}
                value={draft.theme_sidebar_blur_intensity}
                min={0}
                max={32}
                step={1}
                suffix=" px"
                description={t(
                    'theme_studio.fields.theme_sidebar_blur_help',
                    'Affects LeftSidebar, DockBar and TopTabsBar.',
                )}
                onChange={(v) => onField('theme_sidebar_blur_intensity', v)}
            />
            {floatingApplies ? (
                <ToggleField
                    label={t('theme_studio.fields.theme_sidebar_floating', 'Floating glass card')}
                    value={draft.theme_sidebar_floating}
                    description={t(
                        'theme_studio.fields.theme_sidebar_floating_help',
                        'Detach the sidebar from the viewport edge with a margin and shadow.',
                    )}
                    onChange={(v) => onField('theme_sidebar_floating', v)}
                />
            ) : (
                <p className="text-[11px] text-[var(--color-text-muted)]">
                    {t(
                        'theme_studio.fields.theme_sidebar_floating_unavailable',
                        'Floating glass card is only available with Left sidebar position. Dock and Top navigation are already floating by design.',
                    )}
                </p>
            )}
        </div>
    );
}
