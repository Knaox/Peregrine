# Bridge — Webhook orchestrator (Paymenter, WHMCS, …)

This page is the operator-facing companion to **Bridge — Webhook orchestrator
mode** in Peregrine. Pick this mode whenever your front-shop is **a third-party
billing system that drives Pelican via its own module** and Pelican forwards
its native events back to Peregrine. The same wiring works for several
orchestrators :

| Orchestrator | Pelican integration | Status |
|---|---|---|
| **Paymenter** | [Pelican-Paymenter extension](https://builtbybit.com) (paid extension) | ✅ Tested |
| **WHMCS** | [`pelican-dev/whmcs`](https://github.com/pelican-dev/whmcs) module (official) | ✅ Tested |
| Any other system | Must call Pelican Application API + emit native Pelican webhook events | ⚠️ Should work — Peregrine is agnostic |

If you instead drive provisioning from a SaaSykit-style shop with Stripe
talking directly to Peregrine, use **Bridge Shop + Stripe** instead — see
`/docs/bridge-api`. The two modes are mutually exclusive.

## Architecture in one diagram

```
[Customer] --buys plan--> [Orchestrator] --provisions--> [Pelican Panel]
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

- The orchestrator is the **single source of truth** for billing, plans,
  customer comms.
- Peregrine **never** sends a "server ready" / "server suspended" email in
  this mode — the orchestrator already does it.
- Peregrine's **Plans** admin page is hidden in this mode (the orchestrator
  manages the catalogue).
- The Peregrine receiver is **agnostic** to the orchestrator brand : it
  consumes raw Pelican events, regardless of who triggered them upstream.

## Compatibility

### Paymenter

Free OAuth identity provider option also available (see
`/docs/authentication`). The Pelican-Paymenter extension lives on
builtbybit.com — install it on Paymenter and configure your Pelican base URL
+ admin API key in the extension settings.

The extension creates Pelican users on first service activation, then
provisions servers under that user. `external_id` on the Pelican server is
set to Paymenter's service id.

### WHMCS

WHMCS uses the [official `pelican-dev/whmcs` module](https://github.com/pelican-dev/whmcs).
Install it as a server module under **Setup → Products/Services**, configure
your Pelican base URL + Application API token (`papp_…`) + default node + egg.

Lifecycle :

| WHMCS action | Pelican outcome | Peregrine sees |
|---|---|---|
| Service activated (customer purchases) | Server created | `created: Server` (and `created: User` if the user didn't exist yet) |
| Service suspended (non-payment) | Server suspended | `updated: Server` (status → suspended) |
| Service unsuspended (payment recovered) | Server unsuspended | `updated: Server` (status → active) |
| Service terminated | Server deleted | `deleted: Server` |

`external_id` on the Pelican server is set to the **WHMCS service id**. It
mirrors locally as `paymenter_service_id` (legacy name kept for
backward-compat — sémantique = "external service ID").

OAuth single sign-on for WHMCS is also available via WHMCS's native
OpenID Connect provider — see `/docs/whmcs-oauth-setup`.

### Other systems

The receiver is fully agnostic. Any system that uses the Pelican Application
API to create / suspend / delete servers will trigger the same native Pelican
events, which Peregrine consumes identically. The only orchestrator-specific
parts are the prerequisites (the integration plugin on the orchestrator
side) — nothing in Peregrine itself.

## Prerequisites

| Component | Minimum version | Why |
|---|---|---|
| Pelican Panel | 0.46+ | Native outgoing webhooks (`/admin/webhooks`) |
| Orchestrator integration | latest stable | Creates / suspends servers via Pelican on lifecycle events |
| Peregrine queue worker | running | Webhook handler dispatches jobs ; without a worker, nothing gets mirrored |

If your Pelican is older than 0.46, **the webhooks UI doesn't exist yet** —
upgrade Pelican before enabling this mode. The fallback polling
reconciliation alone cannot replace webhooks (5-minute lag is too high for a
production checkout flow).

## 1. Generate the Peregrine token

Open Peregrine admin at `/admin/bridge-settings` :

1. Set **Active bridge backend** to **Webhook-orchestrated (Paymenter,
   WHMCS, …)**.
2. Expand the **Bridge — Webhook orchestrator** section.
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
| **Description** | `Peregrine mirror — webhook orchestrator` (free text, only shown in the Pelican admin) |
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
| `created: Server` | The orchestrator activated a service → Pelican created the server | Local `Server` row created, status `provisioning` |
| `updated: Server` | Suspend / unsuspend / rename / build limits change / install-finished | Status synced (`provisioning` → `active`, `active` → `suspended`, etc.), name updated |
| `deleted: Server` | The orchestrator terminated / cancelled the service | Local row deleted |
| `created: User` | The orchestrator created a new Pelican customer | Local `User` mirror row created (no password) |
| `event: Server\Installed` | End-of-install signal | Status flips from `provisioning` to `active` instantly |

> ℹ️ **Both `event: Server\Installed` AND `updated: Server` should be
> ticked.** `event: Server\Installed` is the canonical end-of-install
> signal Pelican fires the moment the install script finishes ;
> `updated: Server` is a secondary signal (Pelican flips the
> `status` column from `"installing"` to `null` at the same time) and
> acts as a safety net if the first one is dropped for any reason.

Click **Save**. Pelican will start delivering events on the next eligible
state change.

## 3. Verify the wiring

The cleanest test : create a service in your orchestrator from a real plan,
watch the round-trip.

1. From your customer-facing UI (Paymenter / WHMCS / …), order a server plan
   and complete the checkout flow.
2. The orchestrator activates the service → its Pelican module creates the
   server in Pelican → Pelican fires `eloquent.created: App\Models\Server`.
3. Within a few seconds, `/admin/servers` in Peregrine shows the new server
   with status `provisioning`.
4. Once the install finishes, Pelican fires `App\Events\Server\Installed` →
   status flips to `active`.
5. Suspend the service from your orchestrator → status goes to `suspended`.
6. Unsuspend → status returns to `active`.
7. Terminate the service → the local row is removed.

Audit each round-trip in `/admin/pelican-webhook-logs` (Filament resource
visible only in webhook orchestrator mode).

## 4. Limits & operational notes

### Pelican does NOT retry

Pelican fires its outgoing webhooks **once**. If Peregrine returns non-2xx
or is unreachable, the event is lost. To compensate, Peregrine runs a
**reconciliation pass every 5 minutes** : the existing `SyncServerStatusJob`
diffs Pelican's full server list against the local table and patches up
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

The webhook orchestrator mode never fires `ServerProvisioned` or
`ServerSuspended` events. The Bridge email templates
(`bridge_server_ready_*`, `bridge_server_suspended`) are filtered out of
`/admin/email-templates` in this mode. Your orchestrator (Paymenter, WHMCS,
…) sends every customer-facing email itself.

### `external_id` mirroring

Pelican stores the orchestrator's service id in the `external_id` field on
each Server. Peregrine surfaces it locally as `paymenter_service_id` (legacy
column name — semantically it is *External service ID*) for audit and support
flows. It is **never** used as a functional key — the canonical identifier
remains `pelican_server_id`.

For Paymenter : the Paymenter service id.
For WHMCS : the WHMCS service id (`tblhosting.id`).

## 5. Troubleshooting

| Symptom | Diagnosis | Fix |
|---|---|---|
| `/api/pelican/webhook` returns 401 | Token mismatch | Re-paste the token from `/admin/bridge-settings` into the Pelican webhook headers. |
| `/api/pelican/webhook` returns 503 | `bridge_mode !== paymenter` (the legacy enum value, displayed as "Webhook orchestrator") | Switch the radio in `/admin/bridge-settings` to the webhook orchestrator option and save. |
| Servers don't appear in Peregrine | Queue worker is down | `php artisan queue:work` (see `docs/operations/queue-worker.md`). The reconciliation cron will catch up after the worker restarts. |
| Same event mirrored twice | Pelican re-emitted on webhook reconfigure | Idempotency is keyed on `sha256(event\|model_id\|updated_at\|body)` — duplicates short-circuit silently. Nothing to do. |
| Deleted server still visible | Pelican never fired the delete event | The reconciliation cron drops orphan local rows within 5 min. |
| WHMCS server orphaned (no matching customer) | `created: User` ticking missed the user creation event | Either tick `created: User` and trigger a manual sync from WHMCS, or match by email after the fact via `/admin/users`. |

To manually inspect every webhook Peregrine has accepted :

- UI : `/admin/pelican-webhook-logs`
- DB : `select event_type, pelican_model_id, response_status, processed_at, error_message from pelican_processed_events order by processed_at desc limit 50;`

## 6. Switching back to Bridge Shop + Stripe

If you ever migrate away from the orchestrator back to a SaaSykit /
direct-Stripe setup :

1. In `/admin/bridge-settings`, change **Active bridge backend** to
   **Shop + Stripe**.
2. Save. The Pelican webhook will start returning 503 (correct behaviour —
   you're no longer in webhook orchestrator mode).
3. Disable or delete the Pelican webhook config in `/admin/webhooks` to
   stop the doomed delivery attempts.
4. Configure the Shop + Stripe section as documented in
   `/docs/bridge-api`.
5. Servers already mirrored remain in your DB but no longer
   receive lifecycle updates from Pelican webhooks ; you can clean them up
   manually if needed.
