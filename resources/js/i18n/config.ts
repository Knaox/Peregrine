import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

const SUPPORTED = ['en', 'fr'] as const;
type SupportedLocale = (typeof SUPPORTED)[number];

declare global {
    interface Window {
        __DEFAULT_LOCALE__?: string;
        // Inlined by resources/views/app.blade.php — pre-compiled by
        // App\Services\I18n\I18nBootService for the user's effective locale.
        // Carries every namespace JSON as a single nested map so i18next
        // boots fully populated, no fetch needed for the active locale.
        __I18N_BUNDLE__?: {
            locale: string;
            resources: Record<string, Record<string, unknown>>;
        };
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
// SSR-inlined bundle (the active locale, ~12 KB gzipped). Available
// synchronously before the first React render — no FOUC, no waterfall.
// If the Blade shell didn't inline anything (rare: standalone tools, tests),
// we fall back to an empty object and let lazy loading take over.
// ---------------------------------------------------------------------------
const inlined = (typeof window !== 'undefined' ? window.__I18N_BUNDLE__ : null) ?? {
    locale: adminDefault,
    resources: {},
};

// Build i18next's `resources` map from the inlined bundle. Only the active
// locale ships in the HTML to keep the bundle small; the OTHER locale (the
// one the user can switch to via /profile) is lazy-loaded on demand.
const initialResources: Record<string, Record<string, Record<string, unknown>>> = {
    [inlined.locale]: inlined.resources,
};

// Track which (locale, ns) tuples we've already registered so the lazy loader
// is a no-op when called for namespaces that were inlined.
const loaded = new Set<string>();
for (const ns of Object.keys(inlined.resources)) {
    loaded.add(`${inlined.locale}:${ns}`);
}

// ---------------------------------------------------------------------------
// Lazy namespace loader — used when:
//  (a) the user switches to a locale we did NOT inline (the other supported
//      language), or
//  (b) a namespace gets added between deploys and the cached bundle is stale
//      (the etag-keyed cache on the backend resolves this within 6 hours, but
//      the client-side fallback ensures correctness in the meantime).
//
// Discovers every `./locales/<lng>/<ns>.json` at build time via Vite's static
// glob import. Each file becomes its own chunk; the dynamic `import()`
// triggers the network/cache fetch only when the namespace is actually
// requested.
// ---------------------------------------------------------------------------
const lazyLoaders = import.meta.glob<{ default: Record<string, unknown> }>(
    './locales/*/*.json',
);

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
        resources: initialResources,
        // Boot directly on the inlined locale — the LanguageDetector below
        // can still override (e.g. user toggled in localStorage) but in 99%
        // of cases this is what they want and avoids one extra namespace
        // fetch on first paint.
        lng: inlined.locale,
        ns: Object.keys(inlined.resources).length > 0
            ? Object.keys(inlined.resources)
            : ['common', 'auth-login'],
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
            // Plugin i18n bundles are injected at runtime via
            // `addResourceBundle` (loadPluginI18n), which fires i18next's
            // 'added' event. react-i18next only re-renders on
            // 'languageChanged loaded' by default, so a component mounted
            // before its plugin namespace finishes loading — e.g. the PUBLIC
            // /invite page reading the `invitations` namespace — would stay on
            // raw keys forever. Binding 'added removed' makes it re-render once
            // the bundle lands.
            bindI18n: 'languageChanged loaded added removed',
        },
    });

// When the user flips locale in /profile, lazy-load the target locale (it
// wasn't inlined) for every namespace already in use, so the page flips
// without raw keys. Cached after the first switch.
i18n.on('languageChanged', (newLng) => {
    // Collect distinct namespaces from the loaded set
    const namespaces = new Set<string>();
    for (const cacheKey of loaded) {
        const ns = cacheKey.split(':')[1] ?? '';
        if (ns) namespaces.add(ns);
    }
    namespaces.forEach((ns) => {
        void loadNamespace(ns, newLng);
    });
});

// Public re-exports — keep the loadPluginI18n contract identical so plugin
// authors don't have to update their imports.
export { loadPluginI18n } from './pluginLoader';
export default i18n;
