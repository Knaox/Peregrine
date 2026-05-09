# Stripe metadata convention

Peregrine resolves every inbound Stripe event by reading metadata off
the event's `data.object`. The shop is responsible for setting the
following keys on every `Checkout Session`, `Subscription` and
`PaymentIntent` it creates :

## Required

| Key | Type | Source |
|---|---|---|
| `peregrine_configuration_id` | int (string) | The **upstream** `ServerConfiguration.id` returned in `data[].id` by `GET /api/v1/configurations`. **NOT** the primary key of any local mirror table you may keep on the shop side. |
| `peregrine_shop_id` | int (string) | Your `Shop.id` returned by `GET /api/v1/shop/me` |
| `peregrine_user_email` | string | Buyer email (lowercased + trimmed by convention) |
| `peregrine_external_order_id` | string | Your own opaque order reference (used for `GET /api/v1/orders/{id}`) |

> **Common pitfall** : shops that mirror the catalog locally (recommended)
> often pick the wrong column. If your local table looks like
> `peregrine_configurations(id BIGINT PK AUTO_INCREMENT, peregrine_id BIGINT UNIQUE, …)`,
> the value Peregrine expects is **`peregrine_id`**, never your local `id`.
> Sending the local PK is the #1 cause of `skipped: unknown_configuration`
> in production logs.

## Optional

| Key | Type | Notes |
|---|---|---|
| `peregrine_server_id` | int (string) | Set + `is_resubscribe=true` to revive an existing `Server` instead of creating a new one |
| `is_resubscribe` | "true" | Required alongside `peregrine_server_id` |
| `peregrine_metadata` | JSON string | Free-form bag persisted to `Server.metadata` for cross-system audit |

## Validation rules

When the inbound webhook (`checkout.session.completed`) arrives,
Peregrine's `ResolveStripeMetadataAction` enforces :

1. All four required keys MUST be present and non-empty → otherwise
   `skipped: missing_required_metadata` (HTTP 200 + admin notification).
2. `peregrine_shop_id` MUST refer to an active Shop → otherwise
   `skipped: unknown_shop` or `skipped: shop_suspended`.
3. `peregrine_configuration_id` MUST refer to an existing configuration
   AND the shop MUST be authorised via the `shop_server_configuration`
   pivot → otherwise `skipped: configuration_not_authorised_for_shop`.

Peregrine NEVER returns 4xx to Stripe on validation failure — this
prevents Stripe from retrying a misconfigured shop's events for hours.
The rejection is recorded in `stripe_processed_events` and surfaces in
the Filament admin audit page.

## Convention rationale

Stripe metadata values must be strings. Integer IDs are sent as
stringified ints (`"42"`) and parsed back on the Peregrine side. The
SDK's `MetadataBuilder` handles the conversion :

```php
MetadataBuilder::create()
    ->configuration(42)            // int input
    ->shop(7)
    ->user('buyer@example.com')
    ->order('shop-order-1234')
    ->build();
// → ["peregrine_configuration_id" => "42", "peregrine_shop_id" => "7", ...]
```
