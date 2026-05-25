<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Settings;

use App\Services\Plugin\PluginSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Plugins\PeregrinePhpmyadmin\PhpMyAdminServiceProvider;
use Throwable;

/**
 * Strongly-typed snapshot of the plugin configuration, read from the
 * `plugins.settings` JSON column via the core PluginSettings KV store.
 *
 * The shared secret is stored encrypted (`Crypt`) and decrypted lazily here;
 * an empty/undecryptable secret yields '' so the redeem middleware fails
 * closed. The IP allowlist is persisted as a textarea string (one entry per
 * line) and parsed into an array of IPs/CIDRs.
 */
class PmaSettings
{
    /**
     * @param  array<int, string>  $ipAllowlist
     */
    public function __construct(
        public bool $enabled,
        public string $pmaUrl,
        public string $sharedSecret,
        public int $tokenTtl,
        public bool $autoSelectDb,
        public bool $autoLogin,
        public int $serverIndex,
        public array $ipAllowlist,
        public int $rateLimitPerUser,
    ) {}

    public static function make(): self
    {
        $store = app(PluginSettings::class);
        $id = PhpMyAdminServiceProvider::PLUGIN_ID;

        return new self(
            enabled: (bool) $store->getSetting($id, 'enabled', false),
            pmaUrl: rtrim((string) $store->getSetting($id, 'pma_url', ''), '/'),
            sharedSecret: self::decrypt((string) $store->getSetting($id, 'shared_secret', '')),
            tokenTtl: (int) $store->getSetting($id, 'token_ttl', 30),
            autoSelectDb: (bool) $store->getSetting($id, 'auto_select_db', true),
            autoLogin: (bool) $store->getSetting($id, 'auto_login', true),
            serverIndex: (int) $store->getSetting($id, 'pma_server_index', 0),
            ipAllowlist: self::parseAllowlist((string) $store->getSetting($id, 'ip_allowlist', '')),
            rateLimitPerUser: (int) $store->getSetting($id, 'rate_limit_per_user', 20),
        );
    }

    public static function generateSecret(): string
    {
        return Str::random(64);
    }

    /** Encrypt + persist a fresh shared secret (admin context only). */
    public static function storeSecret(string $plain): void
    {
        app(PluginSettings::class)->setSetting(
            PhpMyAdminServiceProvider::PLUGIN_ID,
            'shared_secret',
            Crypt::encryptString($plain),
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

    /**
     * @return array<int, string>
     */
    private static function parseAllowlist(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
    }
}
