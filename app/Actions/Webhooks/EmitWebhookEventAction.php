<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Jobs\Webhooks\DispatchWebhookDeliveryJob;
use App\Models\ServerConfiguration;
use App\Models\Shop;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Single entry point for emitting an outbound webhook from inside the
 * application. Persists the immutable `WebhookEvent`, then fans out one
 * `WebhookDelivery` per (authorised shop × subscribed endpoint) pair
 * and queues the dispatcher for each.
 *
 * Authorisation rule for fan-out :
 *   1. The aggregate (currently always `ServerConfiguration`) MUST be
 *      attached to at least one `Shop` via the pivot. Orphan
 *      configurations emit no webhook (they're admin-only templates).
 *   2. The Shop MUST be `active`.
 *   3. The Endpoint MUST be `active` AND subscribe to the event type.
 *
 * Idempotency : `idempotency_key` (UUID v7 — sortable) is unique per
 * emission. Receivers dedupe on this value (Standard Webhooks `webhook-id`
 * header). Re-running EmitWebhookEventAction for the same logical change
 * produces a NEW key — dedup of "same change emitted twice" is the
 * caller's responsibility (typically the model observer).
 *
 * Shops override : the optional `$shopsOverride` parameter lets the caller
 * supply a pre-resolved list of recipient shops instead of letting this
 * action read them from the pivot. The delete path needs it because the
 * pivot rows are wiped by `cascadeOnDelete` *before* the `deleted`
 * observer fires, so a fresh `shops()` query at that point would always
 * return an empty collection (and silently swallow `configuration.deleted`).
 */
final class EmitWebhookEventAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, Shop>|null  $shopsOverride  pre-resolved recipients ; bypasses the pivot lookup when provided
     */
    public function __invoke(
        string $eventType,
        Model $aggregate,
        array $payload,
        ?Collection $shopsOverride = null,
    ): WebhookEvent {
        $event = WebhookEvent::create([
            'event_type' => $eventType,
            'idempotency_key' => (string) Str::uuid(),
            'aggregate_type' => class_basename($aggregate),
            'aggregate_id' => $aggregate->getKey(),
            'payload' => $payload,
            'emitted_at' => now(),
            'created_at' => now(),
        ]);

        // Resolve the shops authorised to receive this aggregate. Currently
        // only ServerConfiguration is wired ; future aggregates plug in via
        // an `aggregate_type` switch when needed. The caller may provide a
        // pre-resolved list (delete path — see class docblock).
        if ($shopsOverride !== null) {
            $shops = $shopsOverride->filter(
                fn ($shop) => $shop instanceof Shop && $shop->status === 'active'
            )->values();
        } else {
            $shops = $aggregate instanceof ServerConfiguration
                ? $aggregate->shops()->where('shops.status', 'active')->get()
                : collect();
        }

        foreach ($shops as $shop) {
            $endpoints = WebhookEndpoint::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'active')
                ->get()
                ->filter(fn (WebhookEndpoint $e) => $e->subscribesTo($eventType));

            foreach ($endpoints as $endpoint) {
                $delivery = WebhookDelivery::create([
                    'webhook_endpoint_id' => $endpoint->id,
                    'webhook_event_id' => $event->id,
                    'status' => 'pending',
                    'attempt_count' => 0,
                ]);
                DispatchWebhookDeliveryJob::dispatch($delivery->id);
            }
        }

        $event->forceFill(['processed_at' => now()])->save();

        return $event->fresh();
    }
}
