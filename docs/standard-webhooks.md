# Standard Webhooks — Peregrine implementation

Peregrine emits outbound webhooks compliant with the
[Standard Webhooks](https://www.standardwebhooks.com/) spec.

## Headers

```
webhook-id        : <UUID v7>
webhook-timestamp : <unix seconds>
webhook-signature : v1,<base64 hmac-sha256>
content-type      : application/json
user-agent        : Peregrine-Webhooks/1.0
```

## Signed content

```
{webhook-id}.{webhook-timestamp}.{raw_body}
```

Signed with HMAC-SHA256 using the endpoint's `signing_secret` (32 bytes
of entropy, prefixed `whsec_`). Base64-encoded.

## Verification (any language)

1. Reject if `abs(now - timestamp) > 300` (5-minute anti-replay window).
2. Recompute the signed content + HMAC + base64.
3. Constant-time compare against EACH space-separated signature in the
   `webhook-signature` header (multiple sigs supported during rotation).
4. Dedupe on `webhook-id` (your own table — Peregrine never replays the
   same id, even on retries).

### PHP via the SDK

```php
use Peregrine\ShopSdk\Webhooks\StandardWebhookVerifier;

$ok = (new StandardWebhookVerifier())->verify(
    $id, $timestamp, $body, $headerSignature, $endpointSecret,
);
```

### Manual (any language)

```python
import hmac, hashlib, base64, time

def verify(id, ts, body, header_sig, secret, tolerance=300):
    if abs(int(time.time()) - int(ts)) > tolerance:
        return False
    expected = "v1," + base64.b64encode(
        hmac.new(secret.encode(), f"{id}.{ts}.{body}".encode(), hashlib.sha256).digest()
    ).decode()
    return any(hmac.compare_digest(expected, c.strip()) for c in header_sig.split(" "))
```

## Event types (catalog-only)

| Type | Trigger |
|---|---|
| `configuration.created` | Admin creates a `ServerConfiguration` |
| `configuration.updated` | Admin edits an existing configuration |
| `configuration.deleted` | Admin deletes a configuration |

No lifecycle events flow via Peregrine webhooks — server provisioning
state is observed via Stripe directly + the `/api/v1/orders/{id}` poll
endpoint.

## Payload shape

```json
{
  "type": "configuration.updated",
  "id": "0192d2cb-…",
  "timestamp": "2026-05-09T14:30:00+00:00",
  "data": {
    "id": 42,
    "internal_name": "mc-vanilla-medium",
    "ram": 4096, "cpu": 200, "disk": 20480,
    "egg_id": 5, "nest_id": 1,
    "feature_limits": { "allocations": 1, "backups": 3, "databases": 0 }
  }
}
```

The `data` field mirrors the `GET /api/v1/configurations/{id}` payload.
Receivers SHOULD treat it as an authoritative state snapshot ; Peregrine
may add fields without bumping the event version.

## Retry policy

| Attempt | Delay |
|---|---|
| 1 | immediate |
| 2 | +60 s |
| 3 | +5 min |
| 4 | +15 min |
| 5 | +30 min |
| 6+ | +60 min (capped) |

After `endpoint.max_retries` exhaustion, the delivery is marked
`expired` and surfaces in `/admin/webhook-deliveries` for manual replay.
The endpoint's `consecutive_failures` counter increments on each failure
and resets to 0 on first success — admins watch this for flaky targets.

## Secret rotation

Rotating the signing_secret keeps deliveries flowing :

1. Call `POST /api/v1/webhooks/endpoints/{id}/rotate-secret` (or use the
   Filament action). The response contains the new secret.
2. Update your verifier to try the new secret first, the old secret
   second (the verifier supports multiple space-separated signatures
   in a single header for this).
3. Once all in-flight deliveries (max retry window = 1h) have settled,
   remove the old secret from your verifier.
