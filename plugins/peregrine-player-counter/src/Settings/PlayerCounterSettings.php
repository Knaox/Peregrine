<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Settings;

use App\Services\Plugin\PluginSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;
use Throwable;

/**
 * Strongly-typed snapshot of the plugin's connection settings, read from the
 * `plugins.settings` JSON column via the core PluginSettings KV store. The
 * optional sidecar token is stored encrypted and decrypted lazily here.
 */
class PlayerCounterSettings
{
    public function __construct(
        public bool $enabled,
        public string $sidecarUrl,
        public string $sidecarToken,
    ) {}

    public static function make(): self
    {
        $store = app(PluginSettings::class);
        $id = PlayerCounterServiceProvider::PLUGIN_ID;

        return new self(
            enabled: (bool) $store->getSetting($id, 'enabled', false),
            sidecarUrl: rtrim((string) $store->getSetting($id, 'sidecar_url', 'http://127.0.0.1:9899'), '/'),
            sidecarToken: self::decrypt((string) $store->getSetting($id, 'sidecar_token', '')),
        );
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /** Encrypt + persist the sidecar shared secret (admin context only). */
    public static function storeToken(string $plain): void
    {
        app(PluginSettings::class)->setSetting(
            PlayerCounterServiceProvider::PLUGIN_ID,
            'sidecar_token',
            $plain === '' ? '' : Crypt::encryptString($plain),
        );
    }

    private static function decrypt(string $envelope): string
    {
        if ($envelope === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($envelope);
        } catch (Throwable) {
            return '';
        }
    }
}
