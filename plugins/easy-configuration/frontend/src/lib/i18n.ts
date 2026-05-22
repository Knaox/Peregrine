import { useTranslation } from 'react-i18next';
import { PLUGIN_ID } from '../shared';
import type { LocaleLabel } from '../types';

/** Plugin-scoped translation hook + the current 2-letter language. */
export function useT(): { t: (key: string, opts?: Record<string, unknown>) => string; lang: string } {
    const { t, i18n } = useTranslation(PLUGIN_ID);

    return { t, lang: (i18n.language ?? 'en').slice(0, 2) };
}

/** Resolve a localised label, falling back across the current lang -> en -> fr -> first. */
export function pickLabel(label: LocaleLabel | null | undefined, lang: string, fallback = ''): string {
    if (!label) {
        return fallback;
    }

    return label[lang] ?? label.en ?? label.fr ?? Object.values(label)[0] ?? fallback;
}
