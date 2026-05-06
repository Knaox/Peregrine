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
    /**
     * Phase 2 of the uninstall flow: the installer egg has finished wiping
     * /mnt/server and we've swapped the server back onto the user's
     * original egg with skip_scripts=false. We're now polling the
     * resulting reinstall to completion before deleting the row.
     */
    case Reinstalling = 'reinstalling';

    public function isActive(): bool
    {
        return $this === self::Pending
            || $this === self::Installing
            || $this === self::Uninstalling
            || $this === self::Reinstalling;
    }
}
