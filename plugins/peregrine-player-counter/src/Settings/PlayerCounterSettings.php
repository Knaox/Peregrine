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
    /**
     * @param  list<int>  $eggWhitelist  Egg ids the card is limited to ([] = all eggs).
     */
    public function __construct(
        public bool $enabled,
        public string $sidecarUrl,
        public string $sidecarToken,
        public array $eggWhitelist = [],
    ) {}

    public static function make(): self
    {
        $store = app(PluginSettings::class);
        $id = PlayerCounterServiceProvider::PLUGIN_ID;

        return new self(
            enabled: (bool) $store->getSetting($id, 'enabled', false),
            sidecarUrl: rtrim((string) $store->getSetting($id, 'sidecar_url', 'http://127.0.0.1:9899'), '/'),
            sidecarToken: self::decrypt((string) $store->getSetting($id, 'sidecar_token', '')),
            eggWhitelist: self::normalizeEggIds($store->getSetting($id, 'egg_whitelist', [])),
        );
    }

    /**
     * Whether the player-count card should be shown for a server on this egg.
     * An empty whitelist means "every egg" (the default); otherwise only the
     * explicitly-listed egg ids are allowed and all others are hidden.
     */
    public function allowsEgg(?int $eggId): bool
    {
        if ($this->eggWhitelist === []) {
            return true;
        }

        return $eggId !== null && in_array($eggId, $this->eggWhitelist, true);
    }

    /**
     * @return list<int>
     */
    public static function normalizeEggIds(mixed $value): array
    {
        $ids = [];
        foreach ((array) $value as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[(int) $id] = true; // dedupe
            }
        }

        return array_keys($ids);
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
