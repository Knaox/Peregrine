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

    private const FALLBACK_JAVA = 17;

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly PelicanFileService $files,
        private readonly LoggerInterface $logger,
    ) {}

    public function detect(Server $server, string $jarFile = 'server.jar'): int
    {
        try {
            $content = $this->files->getFileContent($server->identifier, '/'.ltrim($jarFile, '/'));
        } catch (Throwable $e) {
            $this->logger->info('modpack: jar read failed — using fallback', [
                'server' => $server->id, 'jar' => $jarFile, 'error' => $e->getMessage(),
            ]);

            return self::FALLBACK_JAVA;
        }

        if ($content === '' || strlen($content) > 200 * 1024 * 1024) {
            return self::FALLBACK_JAVA;
        }

        $hash = hash('sha256', $content);
        $cacheKey = "mcjars:lookup:sha256:{$hash}";

        return (int) $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): int => $this->lookup($hash),
        );
    }

    private function lookup(string $sha256): int
    {
        try {
            $response = $this->http
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Peregrine'])
                ->timeout(10)
                ->post(self::MCJARS_URL, ['hash' => ['sha256' => $sha256]]);

            if ($response->status() === 404) {
                return self::FALLBACK_JAVA;
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

            return $java >= 8 ? $java : self::FALLBACK_JAVA;
        } catch (Throwable $e) {
            $this->logger->info('modpack: MCJars lookup failed — using fallback', [
                'sha256' => $sha256, 'error' => $e->getMessage(),
            ]);

            return self::FALLBACK_JAVA;
        }
    }
}
