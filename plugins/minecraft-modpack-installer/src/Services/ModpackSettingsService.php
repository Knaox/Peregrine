<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use App\Services\SettingsService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;

class ModpackSettingsService
{
    private const KEY_CURSEFORGE = 'modpack_curseforge_api_key';

    private const KEY_WHITELIST = 'modpack_whitelisted_egg_ids';

    private const KEY_TIMEOUT = 'modpack_install_timeout_minutes';

    private const KEY_DEFAULT_PROVIDER = 'modpack_default_provider';

    public function __construct(private readonly SettingsService $settings) {}

    public function curseforgeApiKey(): ?string
    {
        $raw = $this->settings->get(self::KEY_CURSEFORGE);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }

        return $decrypted !== '' ? $decrypted : null;
    }

    public function setCurseforgeApiKey(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->settings->forget(self::KEY_CURSEFORGE);

            return;
        }
        $this->settings->set(self::KEY_CURSEFORGE, Crypt::encryptString($value));
    }

    /** @return list<int> */
    public function whitelistedEggIds(): array
    {
        $raw = $this->settings->get(self::KEY_WHITELIST, '[]');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded), static fn (int $id) => $id > 0));
    }

    /** @param list<int> $ids */
    public function setWhitelistedEggIds(array $ids): void
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        $this->settings->set(self::KEY_WHITELIST, json_encode(array_values($clean)) ?: '[]');
        // Bust the manifest enricher cache so the sidebar tab visibility
        // reflects the new whitelist on the very next page load — without
        // this the React shell would lag up to 60s before the tab toggles.
        Cache::forget('modpack_settings.whitelisted_egg_ids');
    }

    public function installTimeoutMinutes(): int
    {
        $raw = (int) ($this->settings->get(self::KEY_TIMEOUT, 30) ?? 30);

        return max(5, min(180, $raw));
    }

    public function setInstallTimeoutMinutes(int $minutes): void
    {
        $clamped = max(5, min(180, $minutes));
        $this->settings->set(self::KEY_TIMEOUT, (string) $clamped);
    }

    public function defaultProvider(): ModpackProvider
    {
        $raw = (string) ($this->settings->get(self::KEY_DEFAULT_PROVIDER, ModpackProvider::Modrinth->value) ?? '');

        return ModpackProvider::tryFrom($raw) ?? ModpackProvider::Modrinth;
    }

    public function setDefaultProvider(ModpackProvider $provider): void
    {
        $this->settings->set(self::KEY_DEFAULT_PROVIDER, $provider->value);
    }
}
