<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Exceptions;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use RuntimeException;
use Throwable;

class ProviderRequestException extends RuntimeException
{
    public function __construct(
        public readonly ModpackProvider $provider,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('[%s] %s', $provider->value, $message), 0, $previous);
    }
}
