<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Enums;

enum ModpackLoader: string
{
    case Forge = 'forge';
    case Fabric = 'fabric';
    case Quilt = 'quilt';
    case NeoForge = 'neoforge';

    public function displayName(): string
    {
        return match ($this) {
            self::Forge => 'Forge',
            self::Fabric => 'Fabric',
            self::Quilt => 'Quilt',
            self::NeoForge => 'NeoForge',
        };
    }

    public static function tryFromAny(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }
}
