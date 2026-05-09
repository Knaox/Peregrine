# Stripe integration settings

This page documents `/admin/stripe-settings` — the **only** place where
Peregrine reads its Stripe credentials and customer-facing URLs. Everything
else (multi-shop registry, Pelican webhooks, third-party orchestrators)
lives on dedicated pages.

---

## What this page configures

```
┌─────────────────────────────────────────────────────────────┐
│  Stripe inbound (Peregrine receives events)                 │
│   • Webhook signing secret    ← REQUIRED for Stripe to fire │
│   • API secret                ← optional, used for outbound │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│  Customer-facing URLs (Peregrine sends emails)              │
│   • Billing portal fallback URL                             │
│   • Resubscribe URL template                                │
│   • Suspension grace period (days)                          │
└─────────────────────────────────────────────────────────────┘
```

When the **webhook signing secret** is set, Peregrine starts processing
inbound Stripe events at `POST /api/stripe/webhook` :
`checkout.session.completed`, `customer.subscription.{updated,deleted,trial_will_end}`,
`invoice.{paid,payment_failed}`, `charge.{refunded,dispute.created}`.

When the **API secret** is set, Peregrine can call the Stripe API outbound
to fetch invoice URLs (in receipt emails) and create Customer Portal
sessions (used in lifecycle emails).

---

## Step-by-step setup

### 1. Get your webhook signing secret

In your **Stripe Dashboard** :

1. Go to **Developers → Webhooks**.
2. Click **Add endpoint**.
3. URL : `https://your-peregrine.example.com/api/stripe/webhook`
4. Select **Listen to** → **Events on Connected accounts** if you use
   Connect, otherwise **Events on your account**.
5. Pick the following events (or "All events" if you don't mind the
   noise — Peregrine ignores unsupported types) :
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `customer.subscription.trial_will_end`
   - `invoice.paid`
   - `invoice.payment_failed`
   - `charge.refunded`
   - `charge.dispute.created`
6. Click **Add endpoint**.
7. On the endpoint detail page, click **Reveal** under **Signing secret**.
   The value starts with `whsec_…`.
8. Copy it and paste it into the **Stripe webhook signing secret**
   field on `/admin/stripe-settings`. Save.

Verify by sending a test event from the Stripe Dashboard — it should
land in `/admin/stripe-processed-events` with status 200.

### 2. (Optional) Set the API secret

The API secret enables outbound calls. Required if you want :

- Real `hosted_invoice_url` links in payment-receipt emails (instead
  of falling back to the panel URL).
- Per-customer Customer Portal sessions in `ServerSuspended` emails
  (instead of falling back to the static URL below).

In your **Stripe Dashboard** : **Developers → API keys → Secret key
(reveal)**. Format : `sk_live_…` (or `sk_test_…`).

Paste into **Stripe API secret**. Save.

### 3. Customer-facing URLs

| Field | What it does | Default behaviour without it |
|---|---|---|
| Stripe Billing Portal fallback URL | Static URL pointing at your hosted Customer Portal. Used in emails when the API session creation fails or no API secret is set. | Email omits the "Manage billing" CTA |
| Resubscribe URL template | URL template used in the "your server is suspended" email so the customer can buy a new subscription. Placeholders : `{server_id}`, `{configuration}`, `{configuration_id}`, `{ts}`, `{signature}`. | Email omits the "Reactivate" CTA |
| Suspension grace period (days) | Days kept between Stripe sending `customer.subscription.deleted` and Peregrine actually deleting the server. Customer can resubscribe during this window. | 14 days (default) |

The signature in the resubscribe URL is HMAC-SHA256 over
`{server_id}|{configuration}|{ts}` keyed with the legacy
`bridge_shop_shared_secret` setting. If you used the legacy Bridge,
this is already populated. Otherwise the link goes out without a
signature — your shop's resubscribe page can ignore the param.

---

## Field-by-field reference

### Stripe webhook signing secret (required)

- **Where it's used** : `app/Http/Middleware/VerifyStripeSignature.php`
  passes inbound payloads through `Stripe\Webhook::constructEvent()`.
- **Storage** : encrypted at rest via `Crypt::encryptString`. Empty
  field on the form = keep current value (admin types a fresh value
  to rotate).
- **Without it** : `/api/stripe/webhook` rejects every call with 401.

### Stripe API secret (optional)

- **Where it's used** :
  - `app/Notifications/Bridge/PaymentConfirmedNotification.php`
    fetches the `hosted_invoice_url` for the receipt CTA.
  - `app/Services/Bridge/Stripe/StripeBillingPortalLinker.php` creates
    a Stripe Customer Portal session per user.
- **Storage** : encrypted at rest. Empty = keep current.
- **Without it** : the email helpers fall back to the static URLs
  below (no API call).

### Stripe Billing Portal fallback URL (optional)

- **Where it's used** : `StripeBillingPortalLinker::urlFor()` returns
  this when no API secret is set or the session creation fails.
- **Format** : `https://billing.stripe.com/p/login/...` (your hosted
  portal URL).
- **Without it** : the email omits the "Manage billing" link entirely.

### Resubscribe URL template (optional)

- **Where it's used** :
  `StripeBillingPortalLinker::resubscribeUrlFor()` interpolates the
  placeholders and signs the payload, called from
  `ServerSuspendedNotification`.
- **Placeholders** :
  - `{server_id}` — Peregrine `Server.id`
  - `{configuration}` — `ServerConfiguration.internal_name`
  - `{configuration_id}` — `ServerConfiguration.id`
  - `{ts}` — unix seconds at link generation time
  - `{signature}` — HMAC-SHA256 over `{server_id}|{configuration}|{ts}`
    keyed with `bridge_shop_shared_secret`
- **Without it** : the email omits the "Reactivate" CTA.

### Suspension grace period (days, default 14)

- **Where it's used** : `app/Jobs/SuspendServerJob.php` reads it when
  it receives a `customer.subscription.deleted` Stripe event ; the
  server is suspended immediately + `scheduled_deletion_at` is set
  to `now() + grace_period_days`. The daily cron
  `PurgeScheduledServerDeletionsJob` deletes servers past their grace.
- **Effect** : during the grace, the customer can `resubscribe` and
  the deletion is cancelled.
- **0** : immediate deletion at next cron run.

---

## Status badges on the page

| Badge | Meaning |
|---|---|
| **Stripe webhook configured** (green) | `bridge_stripe_webhook_secret` is set. Peregrine accepts inbound events. |
| **Stripe webhook missing** (orange) | Secret missing. Stripe events are rejected with 401. |
| **Active shop(s)** (green) | At least one `Shop` row in the registry has `status='active'`. |
| **No active shop** (gray) | No shop set up — multi-shop API surface returns 401 to all keys. |

---

## What this page does NOT configure

| Topic | Where to go |
|---|---|
| Multi-shop registry, API keys, webhook endpoints | [/admin/shops](/admin/shops) — see [/docs/shops](/docs/shops) |
| Pelican webhook receiver token | [/admin/pelican-webhook-settings](/admin/pelican-webhook-settings) — see [/docs/pelican-webhook](/docs/pelican-webhook) |
| Outbound webhook subscriptions | [/admin/webhook-endpoints](/admin/webhook-endpoints) — see [/docs/standard-webhooks](/docs/standard-webhooks) |
| Audit of inbound Stripe events | [/admin/stripe-processed-events](/admin/stripe-processed-events) (coming soon) |
| Audit of inbound Bridge sync calls (legacy) | [/admin/bridge-sync-logs](/admin/bridge-sync-logs) |

---

## Testing inbound webhooks

### Stripe CLI

```bash
stripe listen --forward-to https://your-peregrine.example.com/api/stripe/webhook
# Then in another terminal:
stripe trigger checkout.session.completed \
    --add metadata.peregrine_configuration_id=42 \
    --add metadata.peregrine_shop_id=7 \
    --add metadata.peregrine_user_email=test@example.com \
    --add metadata.peregrine_external_order_id=test-order-1
```

The triggered event lands at `/api/stripe/webhook`, signature verified,
metadata resolved, `LinkPelicanAccountJob → ProvisionServerJob` chain
dispatched. Watch your queue worker logs.

### Common rejection reasons

When the metadata isn't compliant, Peregrine returns `200` (so Stripe
doesn't retry) but logs the rejection in
`/admin/bridge-sync-logs` :

| `skipped` value | Cause |
|---|---|
| `missing_required_metadata` | One of the four `peregrine_*` keys is absent or empty |
| `unknown_shop` | `peregrine_shop_id` doesn't match a Shop row |
| `shop_suspended` | Shop status is `suspended` |
| `unknown_configuration` | `peregrine_configuration_id` doesn't match a row |
| `configuration_not_authorised_for_shop` | The shop is not attached to this configuration via the pivot |

See [/docs/stripe-metadata](/docs/stripe-metadata) for the full
metadata convention.

---

## Reference

- [Multi-shop integration guide](/docs/shops)
- [Stripe metadata convention](/docs/stripe-metadata)
- [Standard Webhooks (outbound spec)](/docs/standard-webhooks)
- [Pelican webhook receiver](/docs/pelican-webhook)
- [Webhook orchestrator (WHMCS / Paymenter)](/docs/bridge-webhook-orchestrator)
