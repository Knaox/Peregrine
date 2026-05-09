<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Constants for the outbound event types Peregrine emits. Catalog-only
 * by design ; lifecycle events flow via Stripe (the bus universel).
 *
 * Adding an event here requires :
 *  1. Updating the observer (or wherever the emission happens).
 *  2. Updating the SDK consumer doc / `docs/standard-webhooks.md`.
 *  3. Filament Resource for `WebhookEndpoint` exposes the new value
 *     as a checkbox in the `subscribed_events` form.
 */
final class WebhookEventTypes
{
    public const CONFIGURATION_CREATED = 'configuration.created';
    public const CONFIGURATION_UPDATED = 'configuration.updated';
    public const CONFIGURATION_DELETED = 'configuration.deleted';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CONFIGURATION_CREATED,
            self::CONFIGURATION_UPDATED,
            self::CONFIGURATION_DELETED,
        ];
    }
}
