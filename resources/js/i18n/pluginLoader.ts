import i18n from './config';

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
    const lang = i18n.language || 'en';
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
