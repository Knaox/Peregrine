<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_PREFIX = 'settings.';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            function () use ($key, $default): mixed {
                // Guarded: during `composer package:discover` / early boot the
                // DB may not be reachable (Docker build, fresh install). Fall
                // back to the caller-provided default rather than crashing.
                try {
                    $setting = Setting::where('key', $key)->first();

                    return $setting?->value ?? $default;
                } catch (\Throwable) {
                    return $default;
                }
            },
        );
    }

    /**
     * Set a setting value. Creates or updates the record.
     */
    public function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        Cache::put(self::CACHE_PREFIX . $key, $value, self::CACHE_TTL);
    }

    /**
     * Delete a setting from the database and cache.
     */
    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Get all branding-related settings.
     *
     * @return array{app_name: string|null, logo_url: string|null, logo_url_light: string|null, favicon_url: string|null}
     */
    public function getBranding(): array
    {
        return Cache::remember('branding_full', self::CACHE_TTL, function (): array {
            $logo = $this->get('app_logo_path', '/images/logo.webp');
            // Light-mode logo: optional. When unset, falls back to the main
            // logo so existing installs keep working.
            $logoLight = $this->get('app_logo_path_light', '');
            $favicon = $this->get('app_favicon_path', '/images/favicon.ico');

            $headerLinks = json_decode($this->get('header_links', '[]') ?? '[]', true) ?: [];

            $logoUrl = $this->resolveAssetUrl($logo);
            $logoUrlLight = $logoLight !== '' && $logoLight !== null
                ? $this->resolveAssetUrl($logoLight)
                : $logoUrl;

            return [
                'app_name' => $this->get('app_name', 'Peregrine'),
                'show_app_name' => $this->get('show_app_name', 'true') === 'true',
                'logo_height' => (int) $this->get('logo_height', '40'),
                'logo_url' => $logoUrl,
                'logo_url_light' => $logoUrlLight,
                'favicon_url' => $this->resolveAssetUrl($favicon),
                'header_links' => $headerLinks,
                'default_locale' => $this->get('default_locale', 'en'),
            ];
        });
    }

    /**
     * Return an absolute URL to the image used in transactional emails.
     *
     * Emails are rendered by mail clients that can't resolve relative paths,
     * so the URL must carry an absolute scheme + host.
     *
     * Resolution order:
     *   1. If the admin uploaded a custom favicon (app_favicon_path is NOT
     *      the bundled default), use that — favicons are square and size
     *      correctly at the 48px email header.
     *   2. Otherwise, fall back to the bundled Peregrine logo.
     */
    public function getEmailLogoUrl(): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $favicon = $this->get('app_favicon_path', null);

        if ($favicon !== null && $favicon !== '' && ! str_starts_with((string) $favicon, '/images/')) {
            $relative = $this->resolveAssetUrl($favicon);

            return str_starts_with($relative, 'http') ? $relative : $appUrl . $relative;
        }

        return $appUrl . '/images/logo.webp';
    }

    /**
     * Convert a storage path to a public URL.
     * Paths starting with / or http are returned as-is (already absolute).
     * Other paths are prefixed with /storage/ (Filament FileUpload paths).
     */
    private function resolveAssetUrl(?string $path): string
    {
        if (! $path || $path === '') {
            return '/images/logo.webp';
        }

        // Already an absolute URL or public path
        if (str_starts_with($path, '/') || str_starts_with($path, 'http')) {
            return $path;
        }

        return '/storage/' . $path;
    }

    /**
     * Clear cached settings. If a key is given, only that key is cleared;
     * otherwise all settings matching the prefix are flushed.
     */
    public function clearCache(?string $key = null): void
    {
        if ($key !== null) {
            Cache::forget(self::CACHE_PREFIX . $key);

            return;
        }

        // Flush all known setting keys from cache.
        $keys = Setting::pluck('key');

        foreach ($keys as $settingKey) {
            Cache::forget(self::CACHE_PREFIX . $settingKey);
        }

        Cache::forget('branding_full');
    }
}
