<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Exceptions;

use RuntimeException;

class InstallationConflictException extends RuntimeException
{
    public function __construct(string $reason = 'A modpack operation is already in progress on this server.')
    {
        parent::__construct($reason);
    }
}
