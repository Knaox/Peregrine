# Pelican Webhook receiver — Setup Guide

This page is the operator-facing documentation for the `/api/pelican/webhook`
endpoint. The receiver is **decoupled from Bridge mode** — it works in any
mode (Shop+Stripe, Paymenter, or even Bridge disabled), as long as you flip
the toggle and configure the bearer token.

> Admin UI path : `/admin/pelican-webhook-settings`

## What it does

The receiver listens to **Pelican's outgoing webhook system** (`/admin/webhooks`
on the Pelican panel) and mirrors a curated subset of Server / User / Node /
Egg changes into Peregrine's local database. Two main use cases :

| Bridge mode    | What the webhook gives you                                                |
|----------------|---------------------------------------------------------------------------|
| `shop_stripe`  | Install completion → server flips from `provisioning` to `active` and the "your server is playable" email goes out. **Required** since the polling fallback was removed. |
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

## Critical : configuration obligatoire pour Shop+Stripe

> ⚠️ Since Phase 1, the `MonitorServerInstallationJob` polling has been
> **removed**. The Pelican webhook is now the **only** signal that flips a
> newly-provisioned server from `provisioning` to `active` and triggers the
> "your server is playable" email.
>
> If you operate in `shop_stripe` mode and don't configure the webhook with
> at least `updated: Server` ticked, your customers' servers will stay
> stuck in `provisioning` forever. The `/admin/servers` page surfaces
> stuck servers (>30 min in `provisioning`) with a red badge — but the
> right fix is always to configure the webhook.

## 1. Enable the receiver

`/admin/pelican-webhook-settings` → toggle "Enable Pelican webhook receiver" → click 🔑 to generate a 64-char token → Save.

The token is encrypted at rest. Saving an empty field keeps the existing value.

## 2. Configure Pelican (`/admin/webhooks` → Create Webhook)

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

### Comment cocher les events

1. Go to `/admin/webhooks` in your Pelican panel
2. Click your Peregrine webhook (or "Create Webhook" if it doesn't exist yet)
3. Open the "Events" tab
4. Use the search bar to find events one by one
5. Tick each event from the lists below
6. Save

## 3. Liste complète des events supportés (par priorité)

### Required — install completion + lifecycle

All five are mandatory. `event: Server\Installed` is the canonical
end-of-install signal — without it, freshly-provisioned servers never
transition to `active`.

| Event                    | Effect locally                                                                            | If unticked                                                |
|--------------------------|-------------------------------------------------------------------------------------------|------------------------------------------------------------|
| `created: Server`        | Mirrors a new Pelican server into the local DB (or fills in `identifier` / `egg_id` for Shop-owned rows). | Servers visible in Pelican but not in `/admin/servers`.    |
| `updated: Server`        | Detects install completion (`installing` → `null`), flips `provisioning` → `active`. **Also covers** suspend/unsuspend/rename/build update. Acts as a safety-net signal for `Server\Installed`. | Status drifts (rename / suspend / unsuspend not mirrored). |
| `deleted: Server`        | Removes the local row when Pelican deletes a server (orchestrator-mode only — Shop-owned rows stay for admin review). | Deleted-on-Pelican servers linger in `/admin/servers`.     |
| `created: User`          | Mirrors a new Pelican user into `users` (skipped in `shop_stripe` mode — Shop owns user creation). | New orchestrator-created users not visible in `/admin/users`. |
| `event: Server\Installed`| Canonical end-of-install signal — fires the "server is playable" email and flips `provisioning` → `active` instantly. | Stripe-paid servers stuck in `provisioning` until `updated: Server` fires (badge "stuck" in `/admin/servers` after 30 min). |

### Recommended — Phase 1 (cuts manual sync)

Replaces the manual `sync:users / sync:nodes / sync:eggs` admin commands.

| Event                    | Effect locally                                                       | If unticked                                                       |
|--------------------------|----------------------------------------------------------------------|-------------------------------------------------------------------|
| `updated: User`          | Mirrors email/name change made in Pelican panel (all bridge modes). | Manual `php artisan sync:users` to pick up changes.               |
| `deleted: User`          | Detaches `pelican_user_id` (never hard-deletes — local user keeps Stripe sub + OAuth). | Drift : local user keeps a stale `pelican_user_id`.              |
| `created: Node`          | Adds a new Pelican node to `/admin/nodes`.                           | Run `sync:nodes` after each node addition.                        |
| `updated: Node`          | Mirrors fqdn / name / memory / disk changes.                         | Local node row drifts from Pelican.                               |
| `deleted: Node`          | Removes node (refused if any plan still references it via `default_node_id` / `allowed_node_ids`). | Stale node row.                                                   |
| `created: Egg`           | Adds a new egg (Minecraft, ARK, Rust…) to `/admin/eggs`.             | Run `sync:eggs` after each egg import.                            |
| `updated: Egg`           | Mirrors docker_image / startup / description / tags changes.         | Local egg drifts from Pelican.                                    |
| `deleted: Egg`           | Removes egg (refused if any server or plan still uses it).           | Stale egg row.                                                    |
| `created: EggVariable`   | Resyncs the parent egg with its full variable list.                  | Plan provisioning may break if a new variable is required.        |
| `updated: EggVariable`   | Same as above.                                                       | Default values drift.                                             |
| `deleted: EggVariable`   | Same as above.                                                       | Local egg keeps obsolete variables.                               |

### À NE PAS cocher

Allocation / Backup / Database / DatabaseHost / ServerTransfer / Subuser
events have **no handler in Peregrine** anymore — the SPA reads these
resources live from Pelican when the user opens `/network /databases
/backups /sub-users`. Ticking them in Pelican's UI just feeds nothing
on our side : the receiver records the call as `ignored` for audit
and that's it.

| Event                                     | Why                                                                              |
|-------------------------------------------|----------------------------------------------------------------------------------|
| `created/updated/deleted: Backup`         | No local table — `/backups` page reads Pelican live.                             |
| `created/updated/deleted: Allocation`     | Same — `/network` reads live.                                                    |
| `created/updated/deleted: Database`       | Same — `/databases` reads live.                                                  |
| `created/updated/deleted: DatabaseHost`   | No handler.                                                                      |
| `created/updated/deleted: ServerTransfer` | No handler.                                                                      |
| `eloquent.created/deleted: Subuser`       | No handler — invitations plugin reads sub-users live from the API.              |
| `event: Server\SubUserAdded/Removed`      | Same.                                                                            |
| `event: ActivityLogged`                   | Fires on every user action (power.start, command, file.download, etc.). Volume too high — would flood the rate limiter. |
| `created/updated/deleted: ActivityLog`    | Same as above.                                                                   |
| `created/updated: Schedule`               | Fires on every cron tick (each schedule run = 2 webhooks). Flood guaranteed.     |
| `created/updated: Task`                   | Same flood pattern as Schedule.                                                  |
| `created/updated: ApiKey`                 | `last_used_at` updated on every Pelican API call → constant noise.               |
| `created/updated: Webhook`                | The webhook log table itself — would create infinite loops if not blacklisted.   |
| `created/updated: WebhookConfiguration`   | Pelican's own webhook config — meta-data, not useful to mirror.                  |
| `created/updated: Role` / `NodeRole`      | Pelican RBAC — Peregrine has its own admin model, no value in mirroring.         |

## 4. Tableau récap par effet

| If you want…                                          | Tick these events                                                              |
|-------------------------------------------------------|--------------------------------------------------------------------------------|
| Stripe payments to provision servers correctly        | `created: Server`, `updated: Server`, `deleted: Server`, `created: User`       |
| Real-time email / name changes from Pelican           | `updated: User`, `deleted: User`                                               |
| Auto-discovery of new nodes you add in Pelican        | `created: Node`, `updated: Node`, `deleted: Node`                              |
| Auto-discovery of new eggs (game types)               | `created/updated/deleted: Egg` + `created/updated/deleted: EggVariable`        |

## 5. Verify

`/admin/pelican-webhook-logs` shows every accepted event with its HTTP
status, error message, and idempotency hash. Hit "Save" on the Pelican
webhook page — you should see a heartbeat row appear within a few seconds.

## How install completion works in shop_stripe mode

```
1. Customer pays → Stripe webhook → ProvisionServerJob
2. Peregrine creates the local Server row (status = provisioning)
3. Peregrine calls Pelican createServer (server is now installing)
4. Local Server row STAYS in `provisioning` — no preemptive flip to `active`
5. Pelican finishes the install script
6. Pelican fires `updated: Server` with status flipping from "installing" → null
7. Peregrine's webhook receiver :
   - Updates Server.status from `provisioning` to `active`
   - Fires `ServerInstalled` event
   - SendServerInstalledNotification mails the customer
```

If the webhook never arrives (admin forgot to tick `updated: Server`,
network issue, etc.), the server stays in `provisioning`. The
`/admin/servers` page shows a red "stuck" badge after 30 min with a
tooltip pointing back here. **There is no automatic polling fallback** —
the explicit failure is intentional, so admins notice misconfiguration
immediately rather than a silent rescue masking the real problem.

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
  Pelican-side — usually a flood-prone event ticked by mistake (Schedule,
  ActivityLog, ApiKey).
- **Server stuck in `provisioning`** : check `/admin/pelican-webhook-logs`
  for the matching `event: Server\Installed` event. If it's missing, the
  webhook didn't reach Peregrine (most likely the event isn't ticked in
  Pelican). Tick **both** `event: Server\Installed` (primary install signal)
  and `updated: Server` (safety net) in Pelican's webhook events tab — the
  next install will flip correctly.
- **`/admin/users` doesn't reflect a recent email change** : tick
  `updated: User` in Pelican. Until then, run `php artisan sync:users` to
  catch up manually.
- **`/admin/nodes` missing a node you added** : tick `created: Node` in
  Pelican. Until then, run `php artisan sync:nodes`.

## Tables locales mirorées

Seules **quatre** tables locales mirorent Pelican : `users`, `nodes`,
`eggs`, `servers`. Elles servent au panel admin Filament (listes,
filtres, sync flows) et à la résolution rapide owner/egg sur la fiche
serveur. Les autres ressources Pelican (allocations, backups, databases,
sub-users) ne sont **pas** stockées localement — la SPA les lit en
direct sur Pelican à chaque ouverture de page.

### Bootstrap manuel

```bash
# Idempotent — peuple users/nodes/eggs/servers depuis Pelican.
php artisan pelican:backfill-mirrors

# Resume après interruption (préserve la progression par ressource).
php artisan pelican:backfill-mirrors --resume

# Reset + redémarrer.
php artisan pelican:backfill-mirrors --fresh

# Une ressource à la fois.
php artisan pelican:backfill-mirrors --only=users
```

Pour les installs non configurées avec le webhook receiver, c'est aussi
le mécanisme de mise à jour : exécuter cette commande après un changement
côté Pelican (nouveau node, nouvel egg, etc.). Avec le webhook activé,
les events `created/updated/deleted: User|Node|Egg|Server` font le travail
en temps réel.

## Filament admin map

| Page                                      | When visible                                  |
|-------------------------------------------|-----------------------------------------------|
| `/admin/pelican-webhook-settings`         | Always                                        |
| `/admin/pelican-webhook-logs`             | When `pelican_webhook_enabled` is `true`      |
| `/admin/bridge-settings`                  | Always (Bridge has its own settings)          |
| `/admin/bridge-sync-logs`                 | Only in `shop_stripe` mode                    |
| `/admin/servers` (with stuck badge)       | Always — badge appears on `provisioning` rows older than 30 min |

## Settings keys reference

| Key                              | Type    | Notes                                                            |
|----------------------------------|---------|------------------------------------------------------------------|
| `pelican_webhook_enabled`        | string  | `'true'` / `'false'`. Gates the middleware and the logs page.    |
| `pelican_webhook_token`          | string  | Encrypted via `Crypt::encryptString`. 64-char base64 recommended.|
| `bridge_pelican_webhook_token`   | string  | **Legacy fallback** for installs that haven't run the extract migration. Read by the middleware if `pelican_webhook_token` is missing. Will be removed in a future release. |
