<?php

namespace App\Exceptions\Bridge;

use RuntimeException;

class PortAllocationException extends RuntimeException
{
    public static function noConsecutiveBlock(int $nodeId, int $count, ?array $preferredRange = null): self
    {
        $rangeMessage = $preferredRange !== null
            ? sprintf(' (after fallback outside preferred range %d-%d)', $preferredRange[0], $preferredRange[1])
            : '';

        return new self(sprintf(
            'No block of %d consecutive free ports found on Pelican node %d%s',
            $count,
            $nodeId,
            $rangeMessage,
        ));
    }
}
