<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanFileService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Psr\Log\LoggerInterface;
use Throwable;

class JavaVersionDetectionService
{
    private const MCJARS_URL = 'https://versions.mcjars.app/api/v2/build';

    private const CACHE_TTL = 60 * 60 * 24 * 30;

    /**
     * Sentinel cached when MCJars couldn't identify the jar. Stored in
     * the cache to avoid hammering the API on every poll cycle for the
     * same modded jar. Translates to a `null` return from `detect()` so
     * the caller can prefer its own prediction (the modpack's
     * predicted_java_version, derived from MC version + loader).
     */
    private const SENTINEL_UNKNOWN = -1;

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly PelicanFileService $files,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve the Java major required to run the given jar.
     *
     * Returns an int Java major (8, 11, 17, 21, …) when MCJars
     * identified the jar with confidence, OR null when the jar is
     * unknown / unreadable / MCJars unreachable. The `null` return is
     * the important shift — callers can then fall back to a smarter
     * default (modpack-predicted Java) instead of getting silently
     * pinned to Java 17 like the previous incarnation of this method
     * did. RLCraft 1.12.2's Forge launcher isn't in the MCJars catalog,
     * which is exactly the case where the silent-17 fallback ended up
     * booting the swapped-back server with a Docker image incompatible
     * with the modpack's Java requirement.
     */
    public function detect(Server $server, string $jarFile = 'server.jar'): ?int
    {
        try {
            $content = $this->files->getFileContent($server->identifier, '/'.ltrim($jarFile, '/'));
        } catch (Throwable $e) {
            $this->logger->info('modpack: jar read failed — caller should use predicted Java', [
                'server' => $server->id, 'jar' => $jarFile, 'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($content === '' || strlen($content) > 200 * 1024 * 1024) {
            $this->logger->info('modpack: jar empty / too large for MCJars — caller should use predicted Java', [
                'server' => $server->id, 'jar' => $jarFile, 'bytes' => strlen($content),
            ]);

            return null;
        }

        $hash = hash('sha256', $content);
        $cacheKey = "mcjars:lookup:sha256:{$hash}";

        $cached = (int) $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): int => $this->lookup($hash) ?? self::SENTINEL_UNKNOWN,
        );

        if ($cached === self::SENTINEL_UNKNOWN) {
            return null;
        }

        return $cached >= 8 ? $cached : null;
    }

    private function lookup(string $sha256): ?int
    {
        try {
            $response = $this->http
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Peregrine'])
                ->timeout(10)
                ->post(self::MCJARS_URL, ['hash' => ['sha256' => $sha256]]);

            if ($response->status() === 404) {
                return null;
            }

            $response->throw();

            // MCJars' Rust backend (mcjars/www) serialises with serde, which
            // historically rename_all = "camelCase". The field is `javaVersion`
            // in production responses but the schema has gone back and forth ;
            // probe both casings to stay forward/backward compatible without
            // requiring a redeploy of the plugin if MCJars flips again.
            $java = (int) (
                $response->json('build.javaVersion')
                ?? $response->json('build.java_version')
                ?? 0
            );

            return $java >= 8 ? $java : null;
        } catch (Throwable $e) {
            $this->logger->info('modpack: MCJars lookup failed — caller should use predicted Java', [
                'sha256' => $sha256, 'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
