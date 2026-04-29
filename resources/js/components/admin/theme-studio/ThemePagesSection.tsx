import { useTranslation } from 'react-i18next';
import { ToggleField } from './fields/ToggleField';
import type { ThemeDraft } from '@/types/themeStudio.types';

interface ThemePagesSectionProps {
    draft: ThemeDraft;
    onField: <K extends keyof ThemeDraft>(key: K, value: ThemeDraft[K]) => void;
}

/**
 * Per-page layout overrides. Defaults: all off → every page follows the
 * global container max-width set in the Layout section.
 */
export function ThemePagesSection({ draft, onField }: ThemePagesSectionProps) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-4">
            <ToggleField
                label={t('theme_studio.fields.theme_page_console_fullwidth', 'Console — full-width')}
                value={draft.theme_page_console_fullwidth}
                description={t(
                    'theme_studio.fields.theme_page_console_fullwidth_help',
                    'Removes side padding around the terminal so it stretches edge-to-edge.',
                )}
                onChange={(v) => onField('theme_page_console_fullwidth', v)}
            />
            <ToggleField
                label={t('theme_studio.fields.theme_page_files_fullwidth', 'Files — full-width')}
                value={draft.theme_page_files_fullwidth}
                description={t(
                    'theme_studio.fields.theme_page_files_fullwidth_help',
                    'Removes side padding around the file browser for denser columns.',
                )}
                onChange={(v) => onField('theme_page_files_fullwidth', v)}
            />
            <ToggleField
                label={t('theme_studio.fields.theme_page_dashboard_expanded', 'Dashboard — 4 columns on ultra-wide')}
                value={draft.theme_page_dashboard_expanded}
                description={t(
                    'theme_studio.fields.theme_page_dashboard_expanded_help',
                    'Adds a 4th column to the server card grid above 1280 px.',
                )}
                onChange={(v) => onField('theme_page_dashboard_expanded', v)}
            />
        </div>
    );
}
