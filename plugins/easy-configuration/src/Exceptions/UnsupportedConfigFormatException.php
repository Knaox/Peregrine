<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Exceptions;

use RuntimeException;

final class UnsupportedConfigFormatException extends RuntimeException
{
    public static function for(string $format): self
    {
        return new self("Unsupported configuration file format: {$format}");
    }
}
