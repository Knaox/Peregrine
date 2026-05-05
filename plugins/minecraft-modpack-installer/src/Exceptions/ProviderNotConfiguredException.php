<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Exceptions;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use RuntimeException;

class ProviderNotConfiguredException extends RuntimeException
{
    public function __construct(public readonly ModpackProvider $provider)
    {
        parent::__construct(sprintf('Provider %s is not configured.', $provider->value));
    }
}
