import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

// ---------------------------------------------------------------------------
// Eager-loaded namespaces — bundled into the main entry chunk so the very
// first paint already has translated strings. Everything else is lazy-loaded
// on the route mount via `loadNamespace()` (see useNamespace hook).
//
// `common`     — shared chrome (nav, errors, common buttons), used by every
//                layout including the auth shell.
// `auth-login` — first public page when the user lands logged-out; eager
//                loading avoids a one-frame flash of raw keys on /login.
// ---------------------------------------------------------------------------
import enCommon from './locales/en/common.json';
import frCommon from './locales/fr/common.json';
import enAuthLogin from './locales/en/auth-login.json';
import frAuthLogin from './locales/fr/auth-login.json';

const SUPPORTED = ['en', 'fr'] as const;
type SupportedLocale = (typeof SUPPORTED)[number];

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
    return (SUPPORTED as readonly string[]).includes(raw) ? (raw as SupportedLocale) : 'en';
}

const adminDefault = resolveAdminDefault();

// ---------------------------------------------------------------------------
// Lazy namespace loader — discovers every `./locales/<lng>/<ns>.json` at build
// time via Vite's static glob import. Each file becomes its own chunk; the
// dynamic `import()` triggers the network/cache fetch only when the namespace
// is actually requested by a page.
// ---------------------------------------------------------------------------
const lazyLoaders = import.meta.glob<{ default: Record<string, unknown> }>(
    './locales/*/*.json',
);

const loaded = new Set<string>();

export async function loadNamespace(ns: string, locale?: string): Promise<void> {
    const targets = locale
        ? [locale]
        : i18n.language === 'en'
            ? ['en']
            : [i18n.language || adminDefault, 'en'];
    await Promise.all(
        targets.map(async (loc) => {
            const cacheKey = `${loc}:${ns}`;
            if (loaded.has(cacheKey)) return;
            const path = `./locales/${loc}/${ns}.json`;
            const loader = lazyLoaders[path];
            if (!loader) {
                // Missing namespace degrades to raw keys (fallback behavior of
                // i18next when fallbackNS is set). Never throws — better a
                // visible label regression than a broken SPA.
                return;
            }
            try {
                const mod = await loader();
                i18n.addResourceBundle(loc, ns, mod.default, true, true);
                loaded.add(cacheKey);
            } catch {
                // Network/parse error — see comment above.
            }
        }),
    );
}

i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources: {
            en: {
                common: enCommon,
                'auth-login': enAuthLogin,
            },
            fr: {
                common: frCommon,
                'auth-login': frAuthLogin,
            },
        },
        ns: ['common', 'auth-login'],
        defaultNS: 'common',
        fallbackNS: 'common',
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
        react: {
            useSuspense: false,
        },
    });

// When the user flips locale in /profile, re-fetch every already-loaded
// namespace into the new language so no stale string sticks around.
i18n.on('languageChanged', () => {
    Array.from(loaded).forEach((cacheKey) => {
        const ns = cacheKey.split(':')[1] ?? '';
        if (!ns) return;
        // Trigger a load for the new language; existing entries stay registered.
        void loadNamespace(ns);
    });
});

// Public re-exports — keep the loadPluginI18n contract identical so plugin
// authors don't have to update their imports.
export { loadPluginI18n } from './pluginLoader';
export default i18n;
