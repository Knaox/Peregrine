<?php

declare(strict_types=1);

namespace App\Bridge\Stripe\DTOs;

use App\Models\ServerConfiguration;
use App\Models\Shop;

/**
 * Validated bundle of identities resolved from the Stripe metadata bag
 * on a `checkout.session.completed` event. Every required field on this
 * DTO must be non-null — callers expect a fully-resolved context, not
 * partial data. Validation lives in `ResolveStripeMetadataAction`.
 *
 * Optional fields (`serverIdForResubscribe`, `extraMetadata`) carry
 * operational hints rather than identity — handlers branch on their
 * presence but the DTO is still considered "valid" without them.
 */
final readonly class ResolvedStripeContext
{
    /**
     * @param  array<string, mixed>  $extraMetadata
     */
    public function __construct(
        public Shop $shop,
        public ServerConfiguration $configuration,
        public string $userEmail,
        public string $externalOrderId,
        public ?int $serverIdForResubscribe = null,
        public array $extraMetadata = [],
    ) {}
}
