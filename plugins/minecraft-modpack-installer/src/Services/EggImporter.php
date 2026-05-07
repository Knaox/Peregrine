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

    /** Bumped whenever the egg template OR install script changes shape so
     *  cached imports get re-pushed to Pelican on the next install. The
     *  hash is derived from the artifact bytes; pairs with `findEggIdByUuid`
     *  on the Pelican client so a payload change doesn't try to re-POST a
     *  UUID that already exists (which Pelican rejects with HTTP 500). */
    private const PAYLOAD_FINGERPRINT_KEY = 'modpacks:installer_pelican_egg_fingerprint';

    private const TEMPLATE_RELATIVE = 'plugins/minecraft-modpack-installer/resources/eggs/peregrine-modpack-installer.json';

    private const SCRIPT_RELATIVE = 'plugins/minecraft-modpack-installer/resources/eggs/peregrine-modpack-installer.sh';

    public function __construct(
        private readonly PelicanClient $pelican,
        private readonly Repository $cache,
    ) {}

    public function ensureImported(bool $force = false): int
    {
        $payload = $this->buildPayload();
        $fingerprint = $this->fingerprint($payload);

        if (! $force) {
            $cachedId = $this->cache->get(self::CACHE_KEY);
            $cachedFingerprint = $this->cache->get(self::PAYLOAD_FINGERPRINT_KEY);
            if ((is_int($cachedId) || (is_string($cachedId) && ctype_digit($cachedId)))
                && $cachedFingerprint === $fingerprint) {
                return (int) $cachedId;
            }
        }

        // Cache miss / fingerprint changed / forced re-import. Before POSTing
        // the import endpoint, check whether Pelican already has an egg
        // matching our UUID — re-importing a UUID that already exists raises
        // HTTP 500 UniqueConstraintViolationException because Pelican's
        // import service does a blind `Egg::create()`. When the egg is
        // already there, we trust the existing row and just refresh our
        // local cache so the next install reuses it without scanning Pelican
        // again. Operators who need to push genuine egg-template changes
        // should delete the egg in the Pelican admin UI; the next ensure()
        // call will then re-import cleanly.
        $existingId = $this->pelican->findEggIdByUuid(self::EGG_UUID);
        if ($existingId !== null) {
            $this->cache->forever(self::CACHE_KEY, $existingId);
            $this->cache->forever(self::PAYLOAD_FINGERPRINT_KEY, $fingerprint);

            return $existingId;
        }

        $pelicanEggId = $this->pelican->importEgg($payload);

        $this->cache->forever(self::CACHE_KEY, $pelicanEggId);
        $this->cache->forever(self::PAYLOAD_FINGERPRINT_KEY, $fingerprint);

        return $pelicanEggId;
    }

    /** @param  array<string, mixed>  $payload */
    private function fingerprint(array $payload): string
    {
        return sha1(json_encode($payload, JSON_THROW_ON_ERROR));
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
        $this->cache->forget(self::PAYLOAD_FINGERPRINT_KEY);
    }

    /**
     * Hard-reimport: delete the existing Pelican egg (if any) and re-create
     * it from scratch with the bundled template. Used to recover from
     * states where Pelican's egg variables have drifted from what the
     * plugin expects (e.g. after a half-applied import that left the
     * variable rows out of sync with their `env_variable` names — the
     * symptom is a 422 "The X variable field is required" on PATCH /startup
     * even though the install job sends every key the bundled template
     * declares).
     *
     * NOTE: this DELETEs the egg in Pelican. Any servers currently bound
     * to it will fail to start until they're swapped to a different egg —
     * not an issue for the modpack installer egg specifically, since it's
     * a short-lived "swap-in for the install, swap-out at the end" egg
     * that no server should be sitting on long-term.
     */
    public function hardReimport(): int
    {
        $existingId = $this->pelican->findEggIdByUuid(self::EGG_UUID);
        if ($existingId !== null) {
            $this->pelican->deleteEgg($existingId);
        }

        $payload = $this->buildPayload();
        $pelicanEggId = $this->pelican->importEgg($payload);

        $this->cache->forever(self::CACHE_KEY, $pelicanEggId);
        $this->cache->forever(self::PAYLOAD_FINGERPRINT_KEY, $this->fingerprint($payload));

        return $pelicanEggId;
    }

    /**
     * Diagnostic: list every env_variable name the egg declares in Pelican
     * right now, alongside the names declared in the bundled template.
     * The console command surfaces a diff so an operator can see at a glance
     * whether the live egg has drifted.
     *
     * @return array{pelican: list<string>, expected: list<string>, missing_in_pelican: list<string>, extra_in_pelican: list<string>}
     */
    public function diagnose(): array
    {
        $expected = $this->expectedEnvNames();

        $eggId = $this->pelican->findEggIdByUuid(self::EGG_UUID);
        $pelican = $eggId !== null ? $this->pelican->getEggVariableEnvNames($eggId) : [];

        sort($expected);
        sort($pelican);

        return [
            'pelican' => $pelican,
            'expected' => $expected,
            'missing_in_pelican' => array_values(array_diff($expected, $pelican)),
            'extra_in_pelican' => array_values(array_diff($pelican, $expected)),
        ];
    }

    /** @return list<string> */
    private function expectedEnvNames(): array
    {
        $payload = $this->buildPayload();
        $names = [];
        foreach ($payload['variables'] ?? [] as $var) {
            $env = $var['env_variable'] ?? null;
            if (is_string($env) && $env !== '') {
                $names[] = $env;
            }
        }

        return $names;
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
