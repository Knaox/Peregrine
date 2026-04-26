# Pelican Webhook receiver — Setup Guide

This page is the operator-facing documentation for the `/api/pelican/webhook`
endpoint. The receiver is **decoupled from Bridge mode** — it works in any
mode (Shop+Stripe, Paymenter, or even Bridge disabled), as long as you flip
the toggle and configure the bearer token.

> Admin UI path : `/admin/pelican-webhook-settings`

## What it does

The receiver listens to **Pelican's outgoing webhook system** (`/admin/webhooks`
on the Pelican panel) and mirrors a curated subset of Server / User changes
into Peregrine's local database. Two main use cases :

| Bridge mode    | What the webhook gives you                                                |
|----------------|---------------------------------------------------------------------------|
| `shop_stripe`  | Install completion → server flips from `provisioning` to `active` and the "your server is playable" email goes out. Replaces the 30-second polling with a near-realtime signal. |
| `paymenter`    | Full mirror of Pelican state — Paymenter creates/suspends/deletes servers in Pelican, Pelican forwards every change here. |
| `disabled`     | Same as Paymenter. Useful for admin-imported servers.                     |

In **all modes**, the **Shop is always source of truth for ownership and
billing**. Pelican is only allowed to write fields the Shop doesn't have :

- `pelican_server_id` (already set during provisioning)
- `identifier` (Pelican's short UUID)
- `egg_id` (mirror)
- `paymenter_service_id` (Paymenter-mode only)
- The install transition `provisioning` → `active` / `provisioning_failed`

Pelican will **never** overwrite `user_id`, `name`, `plan_id`,
`stripe_subscription_id`, or billing-status (`suspended` / `terminated`) on a
Shop-owned server.

## 1. Enable the receiver

`/admin/pelican-webhook-settings` → toggle "Enable Pelican webhook receiver" → click 🔑 to generate a 64-char token → Save.

The token is encrypted at rest. Saving an empty field keeps the existing value.

## 2. Configure Pelican

Pelican panel → `/admin/webhooks` → **Create Webhook** :

| Field          | Value                                                          |
|----------------|----------------------------------------------------------------|
| Type           | Regular                                                        |
| Description    | `Peregrine — Pelican webhook receiver`                         |
| Endpoint       | `https://<your-peregrine-host>/api/pelican/webhook`            |

### Headers

Keep Pelican's default `X-Webhook-Event: {{event}}` row, then add :

```
Authorization: Bearer <the token from /admin/pelican-webhook-settings>
```

### Events to tick

In the search bar, tick :

- `created: Server`
- `updated: Server` ← this one fires when an install finishes
- `deleted: Server`
- `created: User`

> ⚠️ **Do NOT tick `event: Server\Installed`.** In some Pelican releases this
> event crashes Pelican's own queue with `Cannot use object as array`
> (in `ProcessWebhook.php`). The `updated: Server` event already covers the
> install-finished case (Pelican flips `status` from `installing` to `null`).

## 3. Verify

`/admin/pelican-webhook-logs` shows every accepted event with its HTTP
status, error message, and idempotency hash. Hit "Save" on the Pelican
webhook page — you should see a heartbeat row appear within a few seconds.

## How install completion works in shop_stripe mode

```
1. Customer pays → Stripe webhook → ProvisionServerJob
2. Peregrine creates the local Server row (status = provisioning)
3. Peregrine calls Pelican createServer (server is now installing)
4. Peregrine schedules MonitorServerInstallationJob in SHORT mode
   (3 attempts : +30s, +2min, +5min — safety net only)
5. Pelican finishes the install script
6. Pelican fires `updated: Server` with status flipping from "installing" → null
7. Peregrine's webhook receiver :
   - Updates Server.status from `provisioning` to `active`
   - Fires `ServerInstalled` event
   - SendServerInstalledNotification mails the customer
8. The next safety-net poll sees Server.status === 'active' and exits silently
   (no double-email)
```

If the webhook never arrives (admin misconfigured Pelican-side, network
issue, etc.), the safety-net poll still resolves the install within ~5 min.
If you disable the webhook entirely, polling automatically falls back to the
LONG mode (20 attempts × 30s = ~10 min cap) — same behaviour as before.

## Idempotency

Pelican does **not** retry on failure and does **not** provide an event id.
Peregrine derives a hash :

```
sha256( event_type | model_id | updated_at | sha256(body) )
```

Each accepted event is recorded in `pelican_processed_events`. Re-emitted
identical events are deduplicated and the second hit returns
`{"received": true, "idempotent": true}`. Rows older than 2 days are pruned
daily by the `pelican:clean-processed-events` command (Pelican never retries,
so a short retention window suffices).

## Troubleshooting

- **503 `pelican.webhook_disabled`** : the toggle is off in
  `/admin/pelican-webhook-settings`.
- **503 `pelican.token_not_configured`** : enable the toggle, generate a
  token, save.
- **401 `pelican.invalid_token`** : the token in Pelican's `Authorization`
  header doesn't match the one stored in Peregrine. Click 🔑 to regenerate
  in Peregrine, then update Pelican's webhook headers in lockstep.
- **429** : the throttle limit was hit. Pelican fired more events than the
  `pelican-webhook` rate limiter allows in the time window. Investigate
  Pelican-side — usually a misconfigured event spam.
- **Pelican queue crash on `Cannot use object as array`** : you ticked
  `event: Server\Installed`. Untick it. `updated: Server` covers the case.
- **Server stuck in `provisioning`** : check `/admin/pelican-webhook-logs`
  for the matching event. If it's missing, the webhook didn't reach Peregrine
  (Pelican-side network or config issue). The safety-net poll will resolve
  it within ~5 min in any case (or ~10 min if the webhook is disabled).

## Filament admin map

| Page                                      | When visible                                  |
|-------------------------------------------|-----------------------------------------------|
| `/admin/pelican-webhook-settings`         | Always                                        |
| `/admin/pelican-webhook-logs`             | When `pelican_webhook_enabled` is `true`      |
| `/admin/bridge-settings`                  | Always (Bridge has its own settings)          |
| `/admin/bridge-sync-logs`                 | Only in `shop_stripe` mode                    |

## Settings keys reference

| Key                              | Type    | Notes                                                            |
|----------------------------------|---------|------------------------------------------------------------------|
| `pelican_webhook_enabled`        | string  | `'true'` / `'false'`. Gates the middleware and the logs page.    |
| `pelican_webhook_token`          | string  | Encrypted via `Crypt::encryptString`. 64-char base64 recommended.|
| `bridge_pelican_webhook_token`   | string  | **Legacy fallback** for installs that haven't run the extract migration. Read by the middleware if `pelican_webhook_token` is missing. Will be removed in a future release. |
