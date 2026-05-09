# Integrating a shop with Peregrine

This guide walks through the four steps a third-party shop takes to
sell game-server hosting via Peregrine :

1. Get an API key + Shop ID from the Peregrine admin.
2. Read the catalog (`GET /api/v1/configurations`).
3. Create Stripe products with `peregrine_*` metadata.
4. Receive signed outbound webhooks for catalog changes.

## 1. Provision credentials

The Peregrine admin creates a `Shop` row in `/admin/shops`, then mints
one or more API keys via the `Generate API key` action. The plaintext
token is shown ONCE in a modal — copy it immediately, never displayed
again. Format : `psk_<env>_<48 hex>`.

The admin also attaches `ServerConfiguration` rows to your shop via
the `Server configurations` relation manager. Configurations not
attached to your shop are invisible to your API key and webhook fan-out.

## 2. Read the catalog

```http
GET /api/v1/configurations
Authorization: Bearer psk_live_…
```

```json
{
  "data": [
    {
      "id": 42,
      "internal_name": "mc-vanilla-medium",
      "ram": 4096, "cpu": 200, "disk": 20480,
      "egg_id": 5, "nest_id": 1,
      "feature_limits": { "allocations": 1, "backups": 3, "databases": 0 },
      "pivot": { "shop_external_id": "plan-mc-medium", "is_visible": true, "sort_order": 0 }
    }
  ],
  "meta": { "next_cursor": null, "prev_cursor": null }
}
```

Cursor pagination : `?cursor=<opaque>` reuses the value from
`meta.next_cursor` verbatim.

## 3. Tag your Stripe products

Every Stripe Checkout Session, Subscription and Payment must carry the
following metadata :

| Key | Type | Value |
|---|---|---|
| `peregrine_configuration_id` | int | The **upstream** `id` from step 2's `GET /api/v1/configurations` response (NOT a shop-side mirror PK) |
| `peregrine_shop_id` | int | Your `Shop.id` from step 1 |
| `peregrine_user_email` | string | The buyer's email (lowercased + trimmed) |
| `peregrine_external_order_id` | string | Your own opaque order reference |

Optional :

| Key | Type | Value |
|---|---|---|
| `peregrine_server_id` | int | Set on resubscribe to revive an existing server |
| `peregrine_metadata` | JSON string | Free-form bag persisted on the resulting `Server` |

The SDK provides a builder :

```php
use Peregrine\ShopSdk\Stripe\MetadataBuilder;

'metadata' => MetadataBuilder::create()
    ->configuration(42)->shop(7)
    ->user($buyer->email)->order('shop-order-1234')
    ->build(),
```

Peregrine will reject the inbound Stripe event with `200 + audit log + admin notification` if any of the four required keys is missing or
if the shop is not authorised for the configuration.

## 4. Receive signed outbound webhooks

The Peregrine admin creates a `WebhookEndpoint` in `/admin/webhook-endpoints`
pointing at your URL. The signing secret is shown once.

You can also create endpoints via the API :

```http
POST /api/v1/webhooks/endpoints
Authorization: Bearer psk_live_…  (requires webhooks:write ability)

{
  "name": "Catalog sync",
  "url": "https://my-shop.example.com/webhooks/peregrine",
  "subscribed_events": ["configuration.created", "configuration.updated", "configuration.deleted"]
}
```

The response includes `meta.signing_secret` ONCE. Store it.

Every event Peregrine pushes carries three Standard Webhooks headers :

```
webhook-id        : <UUID>
webhook-timestamp : <unix seconds>
webhook-signature : v1,<base64 hmac-sha256>
```

Verification (PHP, via the SDK) :

```php
use Peregrine\ShopSdk\Webhooks\StandardWebhookVerifier;

if (! (new StandardWebhookVerifier())->verify(
    $request->header('webhook-id'),
    $request->header('webhook-timestamp'),
    $request->getContent(),
    $request->header('webhook-signature'),
    $endpoint_signing_secret,
)) abort(401);
```

Receivers MUST :
- reject events older than 5 minutes (`abs(now - timestamp) > 300`)
- dedupe on `webhook-id`

## 5. Track order state

After Stripe checkout completes, Peregrine asynchronously provisions the
server on Pelican. Poll your order :

```http
GET /api/v1/orders/{external_order_id}
Authorization: Bearer psk_live_…  (requires orders:read ability)
```

```json
{
  "data": {
    "external_order_id": "shop-order-1234",
    "status": "active",
    "configuration_id": 42,
    "server": { "id": 17, "identifier": "abc12345", "pelican_server_id": 99, "name": "buyer-mc-vanilla-medium" },
    "scheduled_deletion_at": null,
    "created_at": "2026-05-09T14:30:00+00:00"
  }
}
```

Server statuses : `provisioning`, `active`, `suspended`, `provisioning_failed`.

## Reference

Full OpenAPI spec is at `https://<your-peregrine>/docs` (Swagger UI).
