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
                $setting = Setting::where('key', $key)->first();

                return $setting?->value ?? $default;
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
     * @return array{app_name: string|null, logo_url: string|null, favicon_url: string|null}
     */
    public function getBranding(): array
    {
        return [
            'app_name' => $this->get('app_name'),
            'logo_url' => $this->get('logo_url'),
            'favicon_url' => $this->get('favicon_url'),
        ];
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
    }
}
