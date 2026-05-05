<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use Illuminate\Contracts\Cache\Repository;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use RuntimeException;

class EggImporter
{
    public const EGG_UUID = 'd8a3f1b9-2e4c-4b7a-8f6d-3c9e5d2b1a4f';

    public const EGG_NAME = 'Peregrine Modpack Installer';

    public const SCRIPT_PLACEHOLDER = '@@INSTALL_SCRIPT@@';

    private const CACHE_KEY = 'modpacks:installer_pelican_egg_id';

    private const TEMPLATE_RELATIVE = 'plugins/minecraft-modpack-installer/resources/eggs/peregrine-modpack-installer.json';

    private const SCRIPT_RELATIVE = 'plugins/minecraft-modpack-installer/resources/eggs/peregrine-modpack-installer.sh';

    public function __construct(
        private readonly PelicanClient $pelican,
        private readonly Repository $cache,
    ) {}

    public function ensureImported(bool $force = false): int
    {
        if (! $force) {
            $cached = $this->cache->get(self::CACHE_KEY);
            if (is_int($cached) || (is_string($cached) && ctype_digit($cached))) {
                return (int) $cached;
            }
        }

        $payload = $this->buildPayload();
        $pelicanEggId = $this->pelican->importEgg($payload);

        $this->cache->forever(self::CACHE_KEY, $pelicanEggId);

        return $pelicanEggId;
    }

    public function pelicanEggIdOrNull(): ?int
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_int($cached)) {
            return $cached;
        }
        if (is_string($cached) && ctype_digit($cached)) {
            return (int) $cached;
        }

        return null;
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function buildPayload(): array
    {
        $jsonPath = base_path(self::TEMPLATE_RELATIVE);
        $shellPath = base_path(self::SCRIPT_RELATIVE);

        if (! is_file($jsonPath)) {
            throw new RuntimeException("Egg template not found: {$jsonPath}");
        }
        if (! is_file($shellPath)) {
            throw new RuntimeException("Install script not found: {$shellPath}");
        }

        $json = file_get_contents($jsonPath);
        $script = file_get_contents($shellPath);

        if ($json === false || $script === false) {
            throw new RuntimeException('Failed to read egg artifacts.');
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Egg template is not a JSON object.');
        }

        $existing = $decoded['scripts']['installation']['script'] ?? null;
        if ($existing !== self::SCRIPT_PLACEHOLDER) {
            throw new RuntimeException('Egg template script placeholder missing or has been replaced.');
        }
        $decoded['scripts']['installation']['script'] = $script;

        return $decoded;
    }
}
