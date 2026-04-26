import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import en from './en.json';
import fr from './fr.json';

const SUPPORTED = ['en', 'fr'] as const;
type SupportedLocale = typeof SUPPORTED[number];

declare global {
    interface Window {
        __DEFAULT_LOCALE__?: string;
    }
}

// Default language picked by the admin in /admin/settings — injected into
// window by the Blade template so the SPA can boot with it before any
// network request. Falls back to English if the value is missing or not in
// the supported set.
function resolveAdminDefault(): SupportedLocale {
    const raw = (typeof window !== 'undefined' ? window.__DEFAULT_LOCALE__ : undefined) ?? 'en';
    return (SUPPORTED as readonly string[]).includes(raw) ? raw as SupportedLocale : 'en';
}

const adminDefault = resolveAdminDefault();

i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources: {
            en: { translation: en },
            fr: { translation: fr },
        },
        // Detection order: localStorage (user picked one before) > browser
        // language > admin-configured default > English. The admin default
        // wins over English so a French-only deployment doesn't show English
        // to users who haven't picked a language yet.
        fallbackLng: [adminDefault, 'en'],
        supportedLngs: SUPPORTED as unknown as string[],
        interpolation: {
            escapeValue: false,
        },
        detection: {
            order: ['localStorage', 'navigator'],
            caches: ['localStorage'],
        },
    });

/**
 * Fetch a plugin's i18n bundle and register it as a dedicated namespace in
 * i18next. Plugin frontends consume their strings via
 * `useTranslation(pluginId)` so keys live entirely inside the plugin and
 * never pollute the core resource files.
 *
 * The plugin version is included as a query param so the browser HTTP cache
 * is automatically invalidated when the admin updates the plugin. Without
 * this, the 1-hour cache on the i18n endpoint would mask brand new dict
 * entries until the cache expires.
 *
 * Loads the bundle for the currently active language plus English (for
 * fallback). Network failures are swallowed — the plugin still renders, just
 * with raw keys instead of translated labels, which is preferable to
 * blocking the entire SPA on a plugin asset.
 */
export async function loadPluginI18n(pluginId: string, version?: string): Promise<void> {
    const lang = i18n.language || adminDefault;
    const targets = lang === 'en' ? ['en'] : [lang, 'en'];
    const cacheBust = version ? `?v=${encodeURIComponent(version)}` : '';

    await Promise.all(targets.map(async (loc) => {
        try {
            const res = await fetch(`/api/plugins/${pluginId}/i18n/${loc}${cacheBust}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const body = (await res.json()) as Record<string, unknown>;
            i18n.addResourceBundle(loc, pluginId, body, true, true);
        } catch {
            // Plugin renders with raw keys — degraded but functional.
        }
    }));
}

export default i18n;
