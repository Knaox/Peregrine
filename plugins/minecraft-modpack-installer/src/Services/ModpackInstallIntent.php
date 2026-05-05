<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;

final readonly class ModpackInstallIntent
{
    public function __construct(
        public ModpackProvider $provider,
        public string $modpackId,
        public string $versionId,
        public bool $purgeFiles,
    ) {}
}
