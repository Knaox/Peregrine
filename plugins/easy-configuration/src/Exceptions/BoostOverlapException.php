<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Exceptions;

use Plugins\EasyConfiguration\Models\BoostSchedule;
use RuntimeException;

/**
 * Thrown when a new boost would touch a parameter already covered by a pending
 * or active boost. Carries the conflicting boost so the API can tell the user
 * which window to cancel first.
 */
final class BoostOverlapException extends RuntimeException
{
    public function __construct(public readonly BoostSchedule $conflict)
    {
        parent::__construct('A boost already covers one of these parameters.');
    }
}
