<?php

declare(strict_types=1);

namespace App\Services\Loader;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Cascading loader resolver used when the primary fingerprint path
 * (SHA-512 of server.jar → MCJars lookup) is either unavailable
 * (Wings unpatched) or fails to identify the runtime.
 *
 * Sources, in confidence order:
 *
 *   1. Filesystem probe via Wings `/files/list` — paper / purpur /
 *      folia / fabric / quilt / forge / neoforge are all detectable
 *      from immutable on-disk artefacts (version_history.json,
 *      fabric-server-launcher.properties, libraries/.../<MC>-<LOADER>/,
 *      paper-*.jar at root, etc.). Richest data when it hits.
 *
 *   2. ModpackInstallation row (if the optional Modpack Installer
 *      plugin is present and the server was provisioned through it).
 *      Authoritative for `loader`; usually lacks loader_version.
 *
 *   3. Egg name parsing — last-ditch heuristic. Eggs are operator-
 *      created so naming is not standardised, but stock egg names
 *      ("Paper 1.20.1", "Forge 1.19.2", …) are common enough to be
 *      a useful safety net.
 *
 * Returns the same shape consumed by the React `LoaderBanner`.
 * Returns `null` when no source can produce a confident `status: 'ok'`
 * answer — the calling detector then surfaces `null` to the React side
 * and the banner is hidden (matching the user's UX contract: "on
 * n'affiche pas si fingerprint non détecté").
 *
 * @phpstan-type RuntimeArr array{status: string, loader: ?string, loader_name: ?string, minecraft_version: ?string, version: ?string, build_number: ?int, jar_filename: ?string, source?: string}
 * @phpstan-type LoaderHit array{loader: string, loader_name: string, minecraft_version: ?string, version: ?string, build_number: ?int}
 */
final class FallbackLoaderResolver
{
    public function __construct(
        private readonly PelicanFileService $files,
    ) {}

    /** @return RuntimeArr|null */
    public function resolve(Server $server, ?string $jarFilename = null): ?array
    {
        foreach ([
            ['fromFilesystem', 'filesystem_probe'],
            ['fromModpackInstallation', 'modpack_installation'],
            ['fromEggName', 'egg_name'],
        ] as [$method, $source]) {
            $hit = $this->{$method}($server);
            if ($hit !== null) {
                return $this->finalise($hit, $jarFilename, $source);
            }
        }

        return null;
    }

    /** @return LoaderHit|null */
    private function fromFilesystem(Server $server): ?array
    {
        $rootNames = $this->listRootNames($server);

        // Paper / Purpur / Folia: version_history.json is the cleanest signal.
        if (in_array('version_history.json', $rootNames, true)) {
            $hit = $this->fromVersionHistory($server);
            if ($hit !== null) {
                return $hit;
            }
        }

        // Fabric.
        if (in_array('fabric-server-launcher.properties', $rootNames, true)) {
            return [
                'loader' => 'fabric',
                'loader_name' => 'Fabric',
                'minecraft_version' => $this->fabricMinecraftVersion($server),
                'version' => null,
                'build_number' => null,
            ];
        }

        // Quilt.
        if (in_array('quilt-server-launcher.properties', $rootNames, true)) {
            return [
                'loader' => 'quilt',
                'loader_name' => 'Quilt',
                'minecraft_version' => null,
                'version' => null,
                'build_number' => null,
            ];
        }

        // Forge / NeoForge (libraries/ probe).
        $libProbe = $this->probeLoaderLibraries($server);
        if ($libProbe !== null) {
            return $libProbe;
        }

        // Filename patterns at root (paper-*.jar, forge-*.jar, …).
        foreach ($rootNames as $name) {
            $hit = self::matchJarFilename($name);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function listRootNames(Server $server): array
    {
        try {
            $entries = $this->files->listFiles($server->identifier, '/');
        } catch (Throwable $e) {
            Log::debug('FallbackLoaderResolver: root list failed', [
                'server' => $server->identifier, 'message' => $e->getMessage(),
            ]);

            return [];
        }
        $names = [];
        foreach ($entries as $entry) {
            $attrs = $entry['attributes'] ?? $entry;
            if (! is_array($attrs)) {
                continue;
            }
            $name = (string) ($attrs['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return LoaderHit|null */
    private function fromVersionHistory(Server $server): ?array
    {
        try {
            $body = $this->files->getFileContent($server->identifier, '/version_history.json');
        } catch (Throwable) {
            return null;
        }
        $data = json_decode($body, true);
        if (! is_array($data)) {
            return null;
        }
        $current = (string) ($data['currentVersion'] ?? '');
        if (! preg_match('/git-(\w+)-(\S+)\s+\(MC:\s+(\S+)\)/i', $current, $m)) {
            return null;
        }
        $type = strtoupper($m[1]);

        return [
            'loader' => strtolower($type),
            'loader_name' => self::humanise($type),
            'minecraft_version' => $m[3],
            'version' => 'build '.$m[2],
            'build_number' => is_numeric($m[2]) ? (int) $m[2] : null,
        ];
    }

    private function fabricMinecraftVersion(Server $server): ?string
    {
        try {
            $body = $this->files->getFileContent($server->identifier, '/fabric-server-launcher.properties');
        } catch (Throwable) {
            return null;
        }
        if (preg_match('/serverJar\s*=\s*\S*minecraft-server-(\S+)\.jar/i', $body, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @return LoaderHit|null */
    private function probeLoaderLibraries(Server $server): ?array
    {
        $paths = [
            ['/libraries/net/minecraftforge/forge', 'forge', 'Forge'],
            ['/libraries/net/neoforged/neoforge', 'neoforge', 'NeoForge'],
            ['/libraries/net/neoforged/forge', 'neoforge', 'NeoForge'],
        ];
        foreach ($paths as [$dir, $slug, $label]) {
            try {
                $entries = $this->files->listFiles($server->identifier, $dir);
            } catch (Throwable) {
                continue;
            }
            foreach ($entries as $entry) {
                $attrs = $entry['attributes'] ?? $entry;
                if (! is_array($attrs)) {
                    continue;
                }
                if (($attrs['is_file'] ?? false) === true) {
                    continue;
                }
                $name = (string) ($attrs['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                if ($slug === 'forge') {
                    // basename like "1.20.1-47.2.0"
                    if (preg_match('/^([0-9.]+)-([0-9.]+)$/', $name, $m)) {
                        return [
                            'loader' => 'forge',
                            'loader_name' => $label,
                            'minecraft_version' => $m[1],
                            'version' => $m[2],
                            'build_number' => null,
                        ];
                    }
                } else {
                    // NeoForge: "21.4.123" → MC 1.21.4; or "1.20.1-47.x.y" legacy
                    if (preg_match('/^1\./', $name)) {
                        $parts = explode('-', $name, 2);

                        return [
                            'loader' => 'neoforge',
                            'loader_name' => $label,
                            'minecraft_version' => $parts[0],
                            'version' => $parts[1] ?? null,
                            'build_number' => null,
                        ];
                    }
                    if (preg_match('/^(\d+)\.(\d+)\./', $name, $m)) {
                        return [
                            'loader' => 'neoforge',
                            'loader_name' => $label,
                            'minecraft_version' => '1.'.$m[1].'.'.$m[2],
                            'version' => $name,
                            'build_number' => null,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /** @return LoaderHit|null */
    private function fromModpackInstallation(Server $server): ?array
    {
        /** @var class-string<Model>|string $modelClass */
        $modelClass = 'Plugins\\MinecraftModpackInstaller\\Models\\ModpackInstallation';
        if (! class_exists($modelClass)) {
            return null;
        }
        try {
            if (! Schema::hasTable('modpack_installations')) {
                return null;
            }
            $row = $modelClass::query()
                ->where('server_id', $server->id)
                ->latest('id')
                ->first();
        } catch (Throwable $e) {
            Log::debug('FallbackLoaderResolver: modpack lookup failed', [
                'server' => $server->identifier, 'message' => $e->getMessage(),
            ]);

            return null;
        }
        if (! $row) {
            return null;
        }
        $loader = is_string($row->loader ?? null) ? strtolower((string) $row->loader) : null;
        if ($loader === null || $loader === '') {
            return null;
        }

        return [
            'loader' => $loader,
            'loader_name' => self::humanise(strtoupper($loader)),
            'minecraft_version' => null,
            'version' => null,
            'build_number' => null,
        ];
    }

    /** @return LoaderHit|null */
    private function fromEggName(Server $server): ?array
    {
        try {
            $egg = $server->egg;
            $name = (string) ($egg?->name ?? '');
        } catch (Throwable) {
            return null;
        }
        if ($name === '') {
            return null;
        }
        $known = [
            'neoforge' => 'NeoForge',
            'paper' => 'Paper',
            'purpur' => 'Purpur',
            'folia' => 'Folia',
            'pufferfish' => 'Pufferfish',
            'spigot' => 'Spigot',
            'bukkit' => 'Bukkit',
            'forge' => 'Forge',
            'fabric' => 'Fabric',
            'quilt' => 'Quilt',
            'vanilla' => 'Vanilla',
        ];
        $lc = strtolower($name);
        foreach ($known as $slug => $label) {
            if (str_contains($lc, $slug)) {
                $mc = null;
                if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $name, $m)) {
                    $mc = $m[1];
                }

                return [
                    'loader' => $slug,
                    'loader_name' => $label,
                    'minecraft_version' => $mc,
                    'version' => null,
                    'build_number' => null,
                ];
            }
        }

        return null;
    }

    /** @return LoaderHit|null */
    private static function matchJarFilename(string $jar): ?array
    {
        $patterns = [
            ['/^paper-(\d+\.\d+(?:\.\d+)?)-(\d+)/i', 'PAPER'],
            ['/^purpur-(\d+\.\d+(?:\.\d+)?)-(\d+)/i', 'PURPUR'],
            ['/^forge-(\d+\.\d+(?:\.\d+)?)-(\S+?)(?:-(?:server|universal))?\.jar$/i', 'FORGE'],
            ['/^neoforge-(\d+\.\d+(?:\.\d+)?)-(\S+?)\.jar$/i', 'NEOFORGE'],
            ['/^fabric-server.*mc\.(\d+\.\d+(?:\.\d+)?)-loader\.(\S+?)-/i', 'FABRIC'],
            ['/^quilt-server.*mc\.(\d+\.\d+(?:\.\d+)?)-loader\.(\S+?)-/i', 'QUILT'],
        ];
        foreach ($patterns as [$re, $type]) {
            if (preg_match($re, $jar, $m)) {
                $build = $m[2] ?? null;

                return [
                    'loader' => strtolower($type),
                    'loader_name' => self::humanise($type),
                    'minecraft_version' => $m[1],
                    'version' => $build !== null ? (is_numeric($build) ? 'build '.$build : $build) : null,
                    'build_number' => is_numeric($build ?? '') ? (int) $build : null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  LoaderHit  $hit
     * @return RuntimeArr
     */
    private function finalise(array $hit, ?string $jarFilename, string $source): array
    {
        return [
            'status' => 'ok',
            'loader' => $hit['loader'],
            'loader_name' => $hit['loader_name'],
            'minecraft_version' => $hit['minecraft_version'],
            'version' => $hit['version'],
            'build_number' => $hit['build_number'],
            'jar_filename' => $jarFilename,
            'source' => $source,
        ];
    }

    private static function humanise(string $upper): string
    {
        $map = ['PAPER' => 'Paper', 'PURPUR' => 'Purpur', 'FOLIA' => 'Folia', 'YOUER' => 'Youer', 'NEOFORGE' => 'NeoForge', 'FORGE' => 'Forge', 'FABRIC' => 'Fabric', 'QUILT' => 'Quilt', 'VANILLA' => 'Vanilla', 'SPIGOT' => 'Spigot', 'BUKKIT' => 'Bukkit', 'VELOCITY' => 'Velocity', 'WATERFALL' => 'Waterfall', 'BUNGEECORD' => 'BungeeCord', 'PUFFERFISH' => 'Pufferfish'];

        return $map[strtoupper($upper)] ?? ucfirst(strtolower($upper));
    }
}
