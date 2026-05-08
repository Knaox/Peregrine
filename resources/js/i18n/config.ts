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
 * Two cache-bust query params are appended to the URL :
 *  - `v={plugin.version}`        flips on a formal plugin release
 *  - `h={i18n_etag}` (optional)  flips on every JSON edit, even without a
 *                                version bump (computed server-side from
 *                                the mtime of `frontend/i18n/*.json`)
 *
 * Either one changing is enough for the browser to bypass its 1-hour HTTP
 * cache on the i18n endpoint. Without `h`, fixing a typo in `fr.json` would
 * stay invisible to anyone who already opened the plugin during the last
 * hour — which is exactly the bug we hit when shipping a translation patch
 * without bumping `plugin.json`.
 *
 * Loads the bundle for the currently active language plus English (for
 * fallback). Network failures are swallowed — the plugin still renders, just
 * with raw keys instead of translated labels, which is preferable to
 * blocking the entire SPA on a plugin asset.
 */
export async function loadPluginI18n(
    pluginId: string,
    version?: string,
    i18nEtag?: string | null,
): Promise<void> {
    const lang = i18n.language || adminDefault;
    const targets = lang === 'en' ? ['en'] : [lang, 'en'];
    const params = new URLSearchParams();
    if (version) params.set('v', version);
    if (i18nEtag) params.set('h', i18nEtag);
    const cacheBust = params.toString() ? `?${params.toString()}` : '';

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
