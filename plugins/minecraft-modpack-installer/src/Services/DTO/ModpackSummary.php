<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;

final readonly class ModpackSummary
{
    public function __construct(
        public ModpackProvider $provider,
        public string $modpackId,
        public string $name,
        public ?string $slug,
        public ?string $description,
        public ?string $iconUrl,
        public ?string $externalUrl,
        public ?bool $isServerCompatible,
    ) {}
}
