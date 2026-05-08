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
                // Even on the cache-hot path, double-check the local
                // mirror — the egg id is in Pelican but might not be in
                // Peregrine's `eggs` table yet (cache survives DB wipes,
                // operator re-installed Peregrine, …). Cheap indexed
                // lookup; the heavy syncEggs() only fires on a true miss.
                $this->ensureLocalMirror((int) $cachedId);

                return (int) $cachedId;
            }
        }

        // Cache miss / fingerprint changed / forced re-import. Before POSTing
        // the import endpoint, check whether Pelican already has an egg
        // matching our UUID — re-importing a UUID that already exists raises
        // HTTP 500 UniqueConstraintViolationException because Pelican's
        // import service does a blind `Egg::create()`.
        //
        // Two paths from here :
        //  - $force = false : trust the existing row, refresh our cache and
        //    move on. Cheap and idempotent ; what every install job hits.
        //  - $force = true  : we're deliberately pushing fresh script bytes
        //    (admin clicked "Importer l'egg" or someone ran the artisan
        //    command with --force after editing the .sh). Delete the egg
        //    first so the import below succeeds — without this step the
        //    button silently looked like it worked while Pelican kept
        //    serving the old script forever.
        $existingId = $this->pelican->findEggIdByUuid(self::EGG_UUID);
        if ($existingId !== null) {
            if (! $force) {
                $this->cache->forever(self::CACHE_KEY, $existingId);
                $this->cache->forever(self::PAYLOAD_FINGERPRINT_KEY, $fingerprint);
                $this->ensureLocalMirror($existingId);

                return $existingId;
            }

            try {
                $this->pelican->deleteEgg($existingId);
            } catch (\Throwable $e) {
                // Most likely cause: a server is currently using this egg
                // (mid-install). Surface the exact reason so the operator
                // can wait for the install to complete or clean up by
                // hand instead of staring at a silent failure.
                throw new RuntimeException(
                    "force re-import requires deleting Pelican egg #{$existingId} "
                    .'(UUID '.self::EGG_UUID.'), which Pelican refused: '.$e->getMessage(),
                    previous: $e,
                );
            }

            // Drop our local cache too — the egg id we just deleted will
            // not be reused by Pelican on the re-import below, so callers
            // must not see the stale id even if the import step throws
            // before we update the cache.
            $this->cache->forget(self::CACHE_KEY);
            $this->cache->forget(self::PAYLOAD_FINGERPRINT_KEY);
        }

        $pelicanEggId = $this->pelican->importEgg($payload);

        $this->cache->forever(self::CACHE_KEY, $pelicanEggId);
        $this->cache->forever(self::PAYLOAD_FINGERPRINT_KEY, $fingerprint);
        $this->ensureLocalMirror($pelicanEggId);

        return $pelicanEggId;
    }

    /**
     * Make sure Peregrine's local `eggs` mirror has a row for the given
     * Pelican egg id. Called on every import path so the moment
     * `ensureImported()` returns, downstream code (Filament admin egg
     * picker, InstallModpackJob's `syncLocalEggId`, manifest enrichers,
     * …) can resolve `pelican_egg_id → eggs.id` without going through
     * EggResolver's lazy retry path.
     *
     * Cheap indexed lookup first; the full `InfrastructureSync::syncEggs`
     * (which fetches every egg from Pelican) only fires on a true miss.
     * Failures are swallowed — best effort — because the EggResolver path
     * still catches the egg on first use, so a transient outage here
     * just delays the local mirror by one install cycle.
     */
    private function ensureLocalMirror(int $pelicanEggId): void
    {
        if ($pelicanEggId <= 0) {
            return;
        }
        if (\App\Models\Egg::where('pelican_egg_id', $pelicanEggId)->exists()) {
            return;
        }
        try {
            app(\App\Services\Sync\InfrastructureSync::class)->syncEggs();
        } catch (\Throwable $e) {
            // Best-effort: the EggResolver fallback path will catch this
            // on first lookup. Log via the framework logger if available
            // so an operator running the import command still sees it.
            if (function_exists('logger')) {
                logger()->info('modpack: ensureLocalMirror sync failed (will retry on first egg-id resolve)', [
                    'pelican_egg_id' => $pelicanEggId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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

        // Pelican stores `eggs.script_install` as a MySQL `TEXT` column,
        // which silently truncates anything past 65,535 bytes. Our raw
        // .sh weighs ~66 KB once the multi-loader / multi-provider /
        // robust-cascade logic landed, so the persisted script ended
        // mid-function and the install failed at startup with
        // `syntax error: unexpected end of file`. Strip pure comment
        // lines and blank lines on the wire ONLY — the on-disk source
        // stays fully commented for development. Heredoc bodies are
        // tracked so the EULA template's `# ...` lines (literal text)
        // aren't accidentally removed.
        $decoded['scripts']['installation']['script'] = $this->minifyForPelican($script);

        return $decoded;
    }

    /**
     * Strip purely cosmetic content from a bash script before persisting
     * it to Pelican: empty lines and full-line `#` comments. Keeps the
     * shebang and any in-line trailing comments (those are never on a
     * line by themselves, so the regex doesn't match them) and skips
     * anything between a `<<MARK` opener and its matching `MARK` closer
     * so heredoc bodies pass through verbatim.
     *
     * Output is always still valid bash; the only invariant lost is
     * formatting / readability inside the install container.
     */
    private function minifyForPelican(string $script): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $script) ?: [];
        $out = [];
        $heredocCloser = null;
        $first = true;

        foreach ($lines as $line) {
            // Preserve the shebang verbatim (always the first line).
            if ($first) {
                $first = false;
                if (str_starts_with($line, '#!')) {
                    $out[] = $line;

                    continue;
                }
            }

            // Inside a heredoc → pass through, looking for the closer.
            if ($heredocCloser !== null) {
                $out[] = $line;
                if (rtrim($line) === $heredocCloser) {
                    $heredocCloser = null;
                }
                continue;
            }

            // Heredoc opener — match `<<EOF`, `<<'EOF'`, `<<"EOF"`,
            // `<<-EOF`, with optional trailing redirections (we only
            // care about the marker name itself).
            if (preg_match('/<<-?\s*[\'"]?([A-Za-z_][A-Za-z0-9_]*)[\'"]?\s*(?:\|[^|]*)?\s*$/', $line, $m)) {
                $out[] = $line;
                $heredocCloser = $m[1];

                continue;
            }

            // Pure comment line — strip.
            if (preg_match('/^\s*#/', $line)) {
                continue;
            }

            // Blank line — strip.
            if (trim($line) === '') {
                continue;
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }
}
