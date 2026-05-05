<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Exceptions;

use RuntimeException;

class ServerNotEligibleException extends RuntimeException
{
    public function __construct(string $reason = 'Server is not eligible for modpack installation.')
    {
        parent::__construct($reason);
    }
}
