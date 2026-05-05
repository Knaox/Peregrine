<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Enums;

enum ModpackInstallationStatus: string
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Uninstalling = 'uninstalling';

    public function isActive(): bool
    {
        return $this === self::Pending
            || $this === self::Installing
            || $this === self::Uninstalling;
    }
}
