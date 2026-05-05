<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use InvalidArgumentException;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Services\Providers\Contracts\ModpackProviderInterface;

final class ModpackProviderRegistry
{
    /** @var array<string, ModpackProviderInterface> */
    private array $providers = [];

    public function register(ModpackProviderInterface $provider): void
    {
        $this->providers[$provider->id()->value] = $provider;
    }

    /** @return list<ModpackProviderInterface> */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function has(ModpackProvider|string $id): bool
    {
        $key = $id instanceof ModpackProvider ? $id->value : $id;

        return isset($this->providers[$key]);
    }

    public function get(ModpackProvider|string $id): ModpackProviderInterface
    {
        $key = $id instanceof ModpackProvider ? $id->value : $id;
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("Unknown modpack provider: {$key}");
        }

        return $this->providers[$key];
    }
}
