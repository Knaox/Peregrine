# Webhook orchestrator (Paymenter, WHMCS, …)

Some operators don't run a Stripe-driven shop ; instead a third-party
billing system (Paymenter, WHMCS, …) provisions the Pelican panel
directly via the panel's Application API. In that setup, **Peregrine
just mirrors Pelican state** so admins still get a unified server
dashboard.

This page explains how to wire the orchestrator's outbound webhooks so
Peregrine stays in sync.

| Orchestrator | Pelican integration | Tested |
|---|---|---|
| **Paymenter** | [Pelican-Paymenter extension](https://builtbybit.com) | ✅ |
| **WHMCS** | [`pelican-dev/whmcs`](https://github.com/pelican-dev/whmcs) | ✅ |
| Anything else | Anything that calls the Pelican Application API and emits Pelican-native events | ⚠ Should work — Peregrine is agnostic |

If your provisioning is Stripe-driven instead (Peregrine receives
`checkout.session.completed`), see [`/docs/shops`](/docs/shops). The
two flows can coexist : a Pelican panel can have some servers created
by Peregrine's Stripe flow and others by your billing system — each
class of server stays untouched by the other.

---

## Architecture

```
[Customer] --buys--> [Orchestrator] --provisions--> [Pelican Panel]
                          |                              |
                          | (emails, billing,            | (webhooks: created,
                          |  upgrades, suspensions)      |  updated, deleted)
                          v                              v
                    Customer mailbox            POST /api/pelican/webhook
                                                       |
                                                       v
                                                [Peregrine]
                                              mirrors local DB,
                                              no email is sent.
```

The orchestrator is the **single source of truth** for billing, plans,
and customer communication. Peregrine **never** sends a "server ready"
or "server suspended" email for these servers — the orchestrator
already does it. Peregrine's job is :

- Mirror the server row locally so it shows up in `/admin/servers` and
  in the player's `/dashboard`.
- Reflect status transitions (installing → active → suspended → deleted)
  via the Pelican webhook.
- Surface Pelican install / outage events to the player UI.

---

## Wiring (every orchestrator)

The flow is identical regardless of which orchestrator you use :

### 1. Enable Peregrine's Pelican webhook receiver

Peregrine's Pelican webhook endpoint is **always active** — it does
not depend on any "mode" or shop configuration. To enable it :

1. Go to `/admin/pelican-webhook-settings` (Filament).
2. Toggle **Enabled** = on.
3. Click **Generate token** (or paste an existing one).
4. Copy the token shown — it's never displayed again. Store it in
   your password manager.

The endpoint URL is :

```
POST https://your-peregrine.example.com/api/pelican/webhook
```

### 2. Configure Pelican to forward events

In the Pelican panel, **Application API → Webhooks** :

| Field | Value |
|---|---|
| URL | `https://your-peregrine.example.com/api/pelican/webhook` |
| Events | `eloquent.created: App\Models\Server`, `eloquent.updated: App\Models\Server`, `eloquent.deleted: App\Models\Server` (and friends — see Pelican docs) |
| Authentication | `Bearer <the token from step 1>` |

### 3. Configure the orchestrator

Each orchestrator has its own admin module. Point it at your Pelican
panel as you would for any new install — there's nothing
Peregrine-specific to configure on the orchestrator side. Peregrine
listens to Pelican, not to the orchestrator.

---

## What if I have BOTH a Stripe-driven shop AND an orchestrator ?

That's a supported setup. Each server's lifecycle is driven by
exactly one of them :

- A server provisioned by Peregrine's Stripe flow carries a
  `stripe_subscription_id` and a `server_configuration_id` — its
  lifecycle (suspend, terminate, refund) is driven by Stripe events.
- A server provisioned by an orchestrator has neither of those — its
  lifecycle is driven by the orchestrator (which calls the Pelican
  Application API directly, and Pelican emits the `eloquent.*` events
  that Peregrine mirrors).

The Pelican webhook receiver checks both fields before reacting, so
neither flow steps on the other.

---

## What about the "Bridge mode" radio I used to see ?

Removed. The legacy `Disabled / ShopStripe / Paymenter` radio at
`/admin/bridge-settings` doesn't exist any more :

- Stripe webhooks are wired the moment you set the secret in
  `/admin/stripe-settings`.
- Pelican webhooks are wired the moment you toggle them in
  `/admin/pelican-webhook-settings`.
- Multi-shop is wired in `/admin/shops`.
- Each is independent. There's no single mode you have to pick.

If you previously ran Peregrine in `Paymenter` mode, the only thing
to do is verify that your Pelican webhook is enabled — which it
already was.

---

## Reference

- [Pelican webhook receiver setup](/docs/pelican-webhook) — token
  generation, security model, event types.
- [Multi-shop integration guide](/docs/shops) — for the parallel
  Stripe-driven flow.
- [Standard Webhooks spec](/docs/standard-webhooks) — applies to
  Peregrine's OUTBOUND webhooks (catalog sync to shops), not Pelican's
  inbound payload format.
