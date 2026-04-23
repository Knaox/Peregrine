# Bridge Paymenter — Pelican webhook setup

This page is the operator-facing companion to **Bridge Paymenter mode** in
Peregrine. Bridge Paymenter is the right choice when **Paymenter** is your
front-shop : Paymenter runs the catalogue, takes payments, sends every
customer email, and orchestrates Pelican via the Pelican-Paymenter extension.
Peregrine's only job in this mode is to mirror Pelican's server state into
the local DB so the customer's panel dashboard reflects what they own.

If you instead drive provisioning from a SaaSykit-style shop with Stripe
talking directly to Peregrine, use **Bridge Shop + Stripe** instead — see
`/docs/bridge-api`. The two modes are mutually exclusive.

## Architecture in one diagram

```
[Customer] --buys plan--> [Paymenter] --provisions--> [Pelican Panel]
                              |                              |
                              | (emails customer, billing,   | (webhooks: created /
                              |  upgrades, suspensions)      |  updated / deleted)
                              v                              v
                        Customer mailbox            POST /api/pelican/webhook
                                                          |
                                                          v
                                                    [Peregrine]
                                                  mirrors local DB,
                                                  no email is sent.
```

Notes :

- Paymenter is the **single source of truth** for billing, plans, customer
  comms.
- Peregrine **never** sends a "server ready" / "server suspended" email in
  this mode — Paymenter already does it.
- Peregrine's **Plans** admin page is hidden in this mode (Paymenter manages
  the catalogue).

## Prerequisites

| Component | Minimum version | Why |
|---|---|---|
| Pelican Panel | 0.46+ | Native outgoing webhooks (`/admin/webhooks`) |
| Paymenter | latest | Stable Pelican-Paymenter extension |
| Pelican-Paymenter extension | installed & configured | Paymenter creates / suspends servers via Pelican when a service is activated / cancelled |
| Peregrine queue worker | running | Webhook handler dispatches jobs ; without a worker, nothing gets mirrored |

If your Pelican is older than 0.46, **the webhooks UI doesn't exist yet** —
upgrade Pelican before enabling Bridge Paymenter. The fallback polling
reconciliation alone cannot replace webhooks (5-minute lag is too high for a
production checkout flow).

## 1. Generate the Peregrine token

Open Peregrine admin at `/admin/bridge-settings` :

1. Set **Active bridge backend** to **Paymenter (Pelican webhook driven)**.
2. Expand the **Bridge Paymenter** section.
3. Click the 🔑 icon next to **Pelican webhook authentication token** to
   generate a fresh 64-char random token.
4. Copy the displayed value into your clipboard.
5. Click **Save Settings**.

Notes :

- The token is encrypted at rest (`Crypt::encryptString`).
- Saving an empty value keeps the existing token. To rotate, generate a new
  one and update both Peregrine *and* Pelican headers in lockstep.
- **Don't lose the token mid-rotation** : Peregrine doesn't display the
  stored value back, only encrypts new input.

## 2. Configure the Pelican outgoing webhook

Open Pelican admin at `/admin/webhooks` and click **Create Webhook**.

### Top fields

| Field | Value |
|---|---|
| **Type** | `Regular` (the other option, *Discord*, would format the body as a Discord message — wrong for us) |
| **Description** | `Peregrine mirror — Bridge Paymenter` (free text, only shown in the Pelican admin) |
| **Endpoint** | `https://YOUR-PEREGRINE-DOMAIN/api/pelican/webhook` (no trailing slash) |

### Headers

Pelican pre-fills one row by default — **keep it as-is**, then add a second
row for the bearer token :

| Key | Value | Why |
|---|---|---|
| `X-Webhook-Event` | `{{event}}` | Pelican template — substitutes the event name (e.g. `created: Server`). Peregrine reads this header as the canonical event identifier. |
| `Authorization` | `Bearer <token from step 1>` | Authentication — Peregrine rejects with 401 if missing or wrong. |

You don't need to set `Content-Type` — Pelican always sends
`application/json`.

### Events

Use the search bar to find each item, then tick the checkbox. The **five**
events to enable :

| Pelican label | When it fires | Effect on Peregrine |
|---|---|---|
| `created: Server` | Paymenter activated a service → Pelican-Paymenter created the server | Local `Server` row created, status `provisioning` |
| `updated: Server` | Suspend / unsuspend / rename / build limits change / install-finished | Status synced (`provisioning` → `active`, `active` → `suspended`, etc.), name updated |
| `deleted: Server` | Paymenter terminated / cancelled the service | Local row deleted |
| `created: User` | Paymenter created a new Pelican customer | Local `User` mirror row created (no password) |
| `event: Server\Installed` | End-of-install signal | Status flips from `provisioning` to `active` instantly |

> ℹ️ **Known Pelican bug** — in some Pelican releases, ticking
> `event: Server\Installed` makes Pelican's own queue worker crash with
> `Cannot use object of type App\Events\Server\Installed as array` (in
> `app/Jobs/ProcessWebhook.php`). If you see that exception in Pelican's
> `failed_jobs`, untick that event only — `updated: Server` already covers
> install-finished because Pelican flips `status` from `"installing"` to
> `null` once the install completes, and that fires an `updated: Server`
> event Peregrine handles correctly.

Click **Save**. Pelican will start delivering events on the next eligible
state change.

## 3. Verify the wiring

The cleanest test : create a service in Paymenter from a real plan, watch
the round-trip.

1. From your customer-facing Paymenter UI, order a server plan and complete
   the checkout flow.
2. Paymenter activates the service → Pelican-Paymenter extension creates
   the server in Pelican → Pelican fires `eloquent.created: App\Models\Server`.
3. Within a few seconds, `/admin/servers` in Peregrine shows the new server
   with status `provisioning`.
4. Once the install finishes, Pelican fires `App\Events\Server\Installed` →
   status flips to `active`.
5. Suspend the service from Paymenter → status goes to `suspended`.
6. Unsuspend → status returns to `active`.
7. Terminate the service → the local row is removed.

Audit each round-trip in `/admin/pelican-webhook-logs` (Filament resource
visible only in Paymenter mode).

## 4. Limits & operational notes

### Pelican does NOT retry

Pelican fires its outgoing webhooks **once**. If Peregrine returns non-2xx
or is unreachable, the event is lost. To compensate, Peregrine runs a
**reconciliation pass every 5 minutes** : the existing `SyncServerStatusJob`
now diffs Pelican's full server list against the local table and patches up
any divergence (creates missing rows, removes orphans).

That means worst-case lag is 5 minutes — acceptable for production
provisioning, but not great UX. If you see frequent reconciliations creating
servers, investigate why your webhooks are failing (token mismatch ? queue
worker dead ? Peregrine outage ?).

### Pelican does NOT sign payloads

There is no native HMAC scheme. Authentication relies entirely on the
bearer token. Treat it like a password :

- Rotate it after any leak.
- Never paste it in chat / tickets.
- The route is rate-limited at 240 req/min/IP — slow brute-forcing is also
  expensive in queue work.

### No email is sent from Peregrine

Bridge Paymenter never fires `ServerProvisioned` or `ServerSuspended`
events. The Bridge email templates (`bridge_server_ready_*`,
`bridge_server_suspended`) are filtered out of `/admin/email-templates` in
this mode. Paymenter sends every customer-facing email itself.

### `external_id` mirroring

Pelican stores Paymenter's service id in the `external_id` field on each
Server. Peregrine surfaces it locally as `paymenter_service_id` for audit
and support flows. It is **never** used as a functional key — the canonical
identifier remains `pelican_server_id`.

## 5. Troubleshooting

| Symptom | Diagnosis | Fix |
|---|---|---|
| `/api/pelican/webhook` returns 401 | Token mismatch | Re-paste the token from `/admin/bridge-settings` into the Pelican webhook headers. |
| `/api/pelican/webhook` returns 503 | `bridge_mode !== paymenter` | Switch the radio in `/admin/bridge-settings` to Paymenter and save. |
| Servers don't appear in Peregrine | Queue worker is down | `php artisan queue:work` (see `docs/operations/queue-worker.md`). The reconciliation cron will catch up after the worker restarts. |
| Same event mirrored twice | Pelican re-emitted on webhook reconfigure | Idempotency is keyed on `sha256(event\|model_id\|updated_at\|body)` — duplicates short-circuit silently. Nothing to do. |
| Deleted server still visible | Pelican never fired the delete event | The reconciliation cron drops orphan local rows within 5 min. |

To manually inspect every webhook Peregrine has accepted :

- UI : `/admin/pelican-webhook-logs`
- DB : `select event_type, pelican_model_id, response_status, processed_at, error_message from pelican_processed_events order by processed_at desc limit 50;`

## 6. Switching back to Bridge Shop + Stripe

If you ever migrate away from Paymenter back to a SaaSykit / direct-Stripe
setup :

1. In `/admin/bridge-settings`, change **Active bridge backend** to
   **Shop + Stripe**.
2. Save. The Pelican webhook will start returning 503 (correct behaviour —
   you're no longer in Paymenter mode).
3. Disable or delete the Pelican webhook config in `/admin/webhooks` to
   stop the doomed delivery attempts.
4. Configure the Shop + Stripe section as documented in
   `/docs/bridge-api`.
5. Servers already mirrored by Paymenter remain in your DB but no longer
   receive lifecycle updates from Pelican webhooks ; you can clean them up
   manually if needed.
