<?php

declare(strict_types=1);

namespace App\Bridge\Stripe\Exceptions;

/**
 * Thrown by `ResolveStripeMetadataAction` when the Stripe event metadata
 * cannot be resolved to a fully-valid `ResolvedStripeContext`. Callers
 * MUST catch this and respond with HTTP 200 + audit log + admin
 * notification — the alternative (4xx) would trigger Stripe retries for
 * misconfigured shops, multiplying the noise.
 */
final class BridgeMetadataException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $details = [],
    ) {
        parent::__construct("Bridge metadata resolution failed: {$reason}");
    }
}
