import { useTranslation } from 'react-i18next';
import { PLUGIN_ID } from '../shared';

/** Scoped translator for the plugin's own i18n namespace (frontend/i18n/*.json). */
export function useT(): (key: string, opts?: Record<string, unknown>) => string {
    const { t } = useTranslation(PLUGIN_ID);
    return t;
}
