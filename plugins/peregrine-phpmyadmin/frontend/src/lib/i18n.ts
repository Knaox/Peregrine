import { useTranslation } from 'react-i18next';
import { PLUGIN_ID } from '../shared';

/** Plugin-scoped translation function (namespace = the plugin id). */
export function useT(): (key: string, opts?: Record<string, unknown>) => string {
    const { t } = useTranslation(PLUGIN_ID);

    return t;
}
