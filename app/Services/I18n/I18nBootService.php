<?php

namespace App\Services\I18n;

use Illuminate\Support\Facades\Cache;

/**
 * Pre-compiles every per-page namespace JSON for the requested locale into a
 * single bundle the Blade SPA shell can inline as `window.__I18N__`. This
 * removes every translation HTTP round-trip on first paint — the SPA boots
 * with all of its strings already in the document.
 *
 * Cache key embeds an mtime-based digest so editing any
 * `resources/js/i18n/locales/{locale}/*.json` file (in dev or via a deploy)
 * busts the cache automatically without a manual `php artisan cache:clear`.
 *
 * Pre-i18n-refactor sizes (measured 2026-05-08):
 *   en bundle: 12,218 bytes gzipped (~50 KB raw, 19 namespaces)
 *   fr bundle: 13,601 bytes gzipped
 * That's smaller than a single hero image — inlining it on every shell load
 * is invisible on the wire, even on 3G mobile.
 */
class I18nBootService
{
    /**
     * Returns the full nested resource bundle for one locale, shaped exactly
     * like i18next's `resources[<locale>]` map :
     *
     *     [
     *         'common'           => ['next' => 'Next', ...],
     *         'auth-login'       => ['title' => 'Sign In', ...],
     *         'server-overview'  => [...],
     *         ...
     *     ]
     *
     * Locales not on disk return an empty array — the caller should fall back
     * to lazy-loading via i18next's existing client-side mechanism (see
     * `loadNamespace` in resources/js/i18n/config.ts), which keeps the locale
     * switcher working when the user picks a language we didn't pre-render.
     */
    public function bootstrap(string $locale): array
    {
        $dir = $this->localeDir($locale);
        if (! is_dir($dir)) {
            return [];
        }

        $cacheKey = sprintf('i18n_boot:%s:%s', $locale, $this->etag($locale));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($dir) {
            $bundle = [];
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $namespace = basename($file, '.json');
                $raw = file_get_contents($file);
                if ($raw === false) {
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $bundle[$namespace] = $decoded;
                }
            }

            return $bundle;
        });
    }

    /**
     * Compute a content-aware digest from the per-file mtimes. Used as part
     * of the cache key so any edit invalidates the cached bundle without
     * needing an explicit clear. Cheap (one stat() per file, ~20 calls).
     */
    private function etag(string $locale): string
    {
        $dir = $this->localeDir($locale);
        $mtimes = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $mtimes[basename($file)] = filemtime($file) ?: 0;
        }
        ksort($mtimes);

        return substr(md5(serialize($mtimes)), 0, 12);
    }

    private function localeDir(string $locale): string
    {
        return resource_path("js/i18n/locales/{$locale}");
    }
}
